import { drawMarks } from './annotate/marks.js';
import { openMicrophone } from './practice/audio.js';
import { PracticeApi, ApiError } from './practice/api-client.js';
import { Gauge } from './practice/gauge.js';
import {
    IN_TUNE, MIN_CLARITY, OUTCOMES, OUTCOME_LABELS,
    expectedHz, classifyNote, noteName, toleranceBandCents,
} from './practice/pitch-math.js';
import { summarize } from './practice/report.js';
import { ScoreView } from './practice/score-view.js';
import { SETTING_LIMITS, clamp, loadSettings, saveSettings } from './practice/settings.js';
import { buildTimeline, eventAt, median, summarizeFrames } from './practice/timeline.js';
import { WaitFollower } from './practice/wait-mode.js';

/*
 * The practice player: score + live tuner + note-by-note outcomes.
 *
 * One audio clock drives everything. The run starts at t0 on the AudioContext clock;
 * the count-in clicks, the cursor, and every pitch frame are measured from t0, so the
 * timing never drifts from the sound.
 */

const $ = (sel) => document.querySelector(sel);
const LEAD_MS = 200;

class Player {
    constructor(config) {
        this.config = config;
        this.api = new PracticeApi(config.apiUrl, config.token);
        this.settings = { ...loadSettings(config.defaults), bpm: config.defaults.bpm, fromPage: 1, toPage: null };
        this.score = new ScoreView($('#score'));
        this.gauge = new Gauge($('#gauge'));
        this.form = $('#settings');
        this.state = 'loading';
        this.mic = null;
        this.notes = [];
    }

    async init() {
        this.bindForm();
        try {
            const [piece] = await Promise.all([this.api.getPiece(this.config.pieceId), this.score.load(this.config.scoreUrl)]);
            this.notes = piece.notes;
            this.renderScore();
        } catch (e) {
            this.fail(e instanceof ApiError && e.code === 'network'
                ? 'Could not reach the server. Check your connection and reload the page.'
                : `Could not load this piece: ${e.message}`);
            return;
        }
        this.setState('ready');
        this.showTarget(this.notes[0]?.midi_pitch ?? null);
        $('#btn-start').addEventListener('click', () => this.start());
        $('#btn-stop').addEventListener('click', () => this.stop());
        $('#btn-skip').addEventListener('click', () => this.skip());
        $('#show-annotations')?.addEventListener('change', (ev) => $('#score').classList.toggle('pi-marks-hidden', !ev.target.checked));
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'ArrowRight' && this.state === 'playing' && !ev.target.closest?.('input, select, textarea')) {
                ev.preventDefault();
                this.skip();
            }
        });
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.state === 'playing' && !this.waitFollower) this.warn('The tab was hidden, so the microphone could not be read; some notes may show as missed.');
        });
    }

    renderScore() {
        this.score.render(this.settings.layout);
        const mapped = this.score.map.length;
        if (mapped !== this.notes.length) {
            this.warn(`The score shows ${mapped} melody notes but the analysis has ${this.notes.length}. Colours may land on the wrong notes; the scoring itself is unaffected.`);
        }
        this.fillPageSelects();
        this.drawAnnotations();
    }

    /** Annotations are drawn on A4 pages, so they have nowhere to go in the continuous layout. */
    drawAnnotations() {
        const toggle = $('#show-annotations');
        if (!this.config.annotations || !toggle) return;
        const onPages = this.settings.layout === 'pages';
        toggle.disabled = !onPages;
        $('#annotations-hint').hidden = onPages;
        if (onPages) {
            drawMarks(this.score.pages(), this.config.annotations.layers)
                .catch((e) => this.warn(`The annotations could not be drawn: ${e.message}`));
        }
    }

    // ---------------------------------------------------------------- settings

    bindForm() {
        const f = this.form;
        f.bpm.value = this.settings.bpm;
        f.toleranceValue.value = this.settings.toleranceValue;
        f.referenceHz.value = this.settings.referenceHz;
        f.latencyMs.value = this.settings.latencyMs;
        f.noiseGateDb.value = String(this.settings.noiseGateDb);
        f.countIn.checked = this.settings.countIn;
        f.metronome.checked = this.settings.metronome;
        f.layout.value = this.settings.layout;
        f.playMode.value = this.settings.mode;
        for (const r of f.querySelectorAll('[name=toleranceMode]')) r.checked = r.value === this.settings.toleranceMode;

        f.addEventListener('change', (ev) => {
            this.readForm();
            if (ev.target.name === 'layout' && this.state === 'ready') this.renderScore();
            if (ev.target.name === 'playMode' && this.state === 'ready') this.setState('ready');
        });
        f.addEventListener('submit', (ev) => ev.preventDefault());
        this.readForm();
    }

    readForm() {
        const f = this.form;
        const s = this.settings;
        const mode = f.querySelector('[name=toleranceMode]:checked')?.value === 'hz' ? 'hz' : 'cents';
        s.bpm = Math.round(clamp(f.bpm.value, SETTING_LIMITS.bpm, this.config.defaults.bpm));
        s.toleranceMode = mode;
        s.toleranceValue = clamp(f.toleranceValue.value, mode === 'hz' ? SETTING_LIMITS.toleranceHz : SETTING_LIMITS.toleranceCents, 30);
        s.referenceHz = clamp(f.referenceHz.value, SETTING_LIMITS.referenceHz, 440);
        s.latencyMs = Math.round(clamp(f.latencyMs.value, SETTING_LIMITS.latencyMs, 80));
        s.noiseGateDb = Number(f.noiseGateDb.value) || -30;
        s.countIn = f.countIn.checked;
        s.metronome = f.metronome.checked;
        s.layout = f.layout.value === 'continuous' ? 'continuous' : 'pages';
        s.mode = f.playMode.value === 'wait' ? 'wait' : 'follow';
        for (const el of f.querySelectorAll('[data-follow-only]')) el.hidden = s.mode === 'wait';
        for (const el of f.querySelectorAll('[data-wait-only]')) el.hidden = s.mode !== 'wait';
        s.fromPage = Number(f.fromPage.value) || 1;
        s.toPage = Number(f.toPage.value) || null;
        if (s.toPage && s.toPage < s.fromPage) s.toPage = s.fromPage;
        $('[data-tolerance-unit]').textContent = mode === 'hz' ? 'Hz' : 'cents';
        this.updateToleranceHint();
        this.mic?.setNoiseGate(s.noiseGateDb);
        saveSettings(s);
        this.showTarget(this.currentTargetMidi ?? this.notes[0]?.midi_pitch ?? null);
    }

    updateToleranceHint() {
        const { toleranceMode: mode, toleranceValue: tol, referenceHz: ref } = this.settings;
        const hint = $('#tolerance-hint');
        if (mode === 'cents') {
            hint.textContent = `A note counts as in tune within ±${tol} cents, on every string alike.`;
            return;
        }
        // Show what a fixed Hz window means in cents on a low and a high violin note.
        const low = toleranceBandCents(55, 'hz', tol, ref); // G3, open G string
        const high = toleranceBandCents(76, 'hz', tol, ref); // E5, open E string
        const fmt = (b) => `${Math.round(Number.isFinite(b.low) ? -b.low : 9999)}/${Math.round(b.high)}`;
        hint.textContent = `±${tol} Hz is about ±${fmt(low)} cents on the open G but only ±${fmt(high)} cents on the open E. `
            + (low.high > 100 ? 'On low strings a neighbouring note can count as in tune.' : '');
    }

    fillPageSelects() {
        const pages = this.score.layoutPages();
        for (const name of ['fromPage', 'toPage']) {
            const select = this.form[name];
            select.innerHTML = pages.map((p) => `<option value="${p}">${p}</option>`).join('');
        }
        this.form.fromPage.value = String(pages[0]);
        this.form.toPage.value = String(pages[pages.length - 1]);
        this.form.querySelector('[data-page-range]').hidden = pages.length < 2;
        this.readForm();
    }

    // ---------------------------------------------------------------- run

    async start() {
        if (this.state !== 'ready' && this.state !== 'done') return;
        this.readForm();
        this.hideReport();
        this.clearWarning();
        const s = this.settings;

        const selected = this.notes.filter((n) => {
            const page = this.score.pageOf(n.note_index);
            return page >= s.fromPage && page <= (s.toPage ?? Infinity);
        });
        if (!selected.length) {
            this.warn('No notes in the chosen pages.');
            return;
        }

        this.setState('starting');
        try {
            this.mic ??= await openMicrophone({ noiseGateDb: s.noiseGateDb });
        } catch (e) {
            this.setState('ready');
            this.warn(e.name === 'NotAllowedError'
                ? 'Microphone access was blocked. Allow it in the address bar and press Start again.'
                : `Microphone problem: ${e.message}`);
            return;
        }

        this.session = null;
        try {
            this.session = await this.api.startSession({
                piece_id: this.config.pieceId,
                bpm: s.bpm,
                tolerance_mode: s.toleranceMode,
                tolerance_value: s.toleranceValue,
                reference_hz: s.referenceHz,
                latency_ms: s.latencyMs,
            });
        } catch (e) {
            this.warn(`This run will not be saved: ${e.message}`);
        }

        const waiting = s.mode === 'wait';
        const countInBeats = !waiting && s.countIn ? this.config.defaults.beatsPerMeasure : 0;
        this.timeline = buildTimeline(selected, { bpm: s.bpm, countInBeats, latencyMs: s.latencyMs });
        this.beatMs = 60000 / s.bpm;
        this.t0 = this.mic.nowMs() + LEAD_MS;
        this.nextClickBeat = 0;
        this.clickBeats = !waiting && s.metronome ? Math.ceil(this.timeline.endMs / this.beatMs) : countInBeats;
        this.waitFollower = waiting ? new WaitFollower(this.timeline.events, { referenceHz: s.referenceHz }) : null;

        this.buffers = this.timeline.events.map(() => []);
        this.listenPos = 0;
        this.cursorPos = -1;
        this.liveTarget = -1;
        this.recent = [];
        this.results = [];
        this.pending = [];
        this.sendChain = Promise.resolve();
        this.saveFailed = false;

        this.score.clearColours();
        this.score.moveTo(selected[0].note_index);
        this.updateTally();
        this.setState('playing');
        if (waiting) {
            this.showWaiting(0);
            this.waitLoop();
        } else {
            this.loop();
        }
    }

    // ---------------------------------------------------------------- wait-for-me mode

    waitLoop = () => {
        if (this.state !== 'playing') return;
        const frame = this.mic.read();
        const accepted = this.waitFollower.feed(frame);
        if (accepted) this.completeWaitNote(accepted);
        // The note just played is still ringing: don't show it as a wrong note against the next one.
        this.updateLive(this.waitFollower.lingering ? { ...frame, hz: 0, clarity: 0 } : frame, this.waitFollower.index);
        if (this.waitFollower.done) {
            this.finish();
            return;
        }
        requestAnimationFrame(this.waitLoop);
    };

    completeWaitNote({ index, frames }) {
        this.buffers[index] = frames;
        this.listenPos = index + 1;
        this.finalize(index);
        if (!this.waitFollower.done) this.showWaiting(this.waitFollower.index);
    }

    showWaiting(i) {
        const event = this.timeline.events[i];
        this.score.moveTo(event.index);
        this.updateProgress(i);
        $('#status').textContent = `Play ${noteName(event.midi)}. The cursor waits for you.`;
    }

    skip() {
        if (this.state !== 'playing' || !this.waitFollower || this.waitFollower.done) return;
        this.completeWaitNote(this.waitFollower.skip());
        if (this.waitFollower.done) this.finish();
    }

    loop = () => {
        if (this.state !== 'playing') return;
        const t = this.mic.nowMs() - this.t0;
        this.scheduleClicks(t);
        this.showCountdown(t);

        const frame = this.mic.read();
        const ft = frame.t - this.t0;
        const events = this.timeline.events;

        // 1. Cursor follows the written time.
        const ci = eventAt(events, t, Math.max(0, this.cursorPos));
        if (ci !== null && ci !== this.cursorPos) {
            this.cursorPos = ci;
            this.score.moveTo(events[ci].index);
            this.updateProgress(ci);
        }

        // 2. Close notes whose listening window has passed; give the frame to the open one.
        while (this.listenPos < events.length && ft > events[this.listenPos].listenToMs) {
            this.finalize(this.listenPos++);
        }
        const open = events[this.listenPos];
        if (open && ft >= open.listenFromMs) this.buffers[this.listenPos].push(frame);

        // 3. Live gauge, against the note being heard now (written time shifted by latency).
        this.updateLive(frame, eventAt(events, ft - this.settings.latencyMs, Math.max(0, this.liveTarget)));

        if (this.listenPos >= events.length) {
            this.finish();
            return;
        }
        requestAnimationFrame(this.loop);
    };

    scheduleClicks(t) {
        // Schedule clicks a little ahead on the audio clock, so they are exact even if a frame is late.
        while (this.nextClickBeat < this.clickBeats && this.nextClickBeat * this.beatMs < t + 250) {
            const beat = this.nextClickBeat++;
            const bpm = this.config.defaults.beatsPerMeasure;
            this.mic.click(this.t0 + beat * this.beatMs, beat % bpm === 0);
        }
    }

    showCountdown(t) {
        const el = $('#countdown');
        const left = this.timeline.countInMs - t;
        if (left > 0) {
            el.hidden = false;
            el.textContent = String(Math.ceil(left / this.beatMs));
        } else {
            el.hidden = true;
        }
    }

    updateLive(frame, targetIdx) {
        if (targetIdx === null) {
            this.recent = [];
            if (this.liveTarget !== -1) {
                this.liveTarget = -1;
            }
            this.gauge.show(null);
            return;
        }
        const event = this.timeline.events[targetIdx];
        if (targetIdx !== this.liveTarget) {
            this.liveTarget = targetIdx;
            this.recent = [];
            this.showTarget(event.midi);
        }
        if (frame.hz > 0 && frame.clarity >= MIN_CLARITY) {
            this.recent.push(frame.hz);
            if (this.recent.length > 3) this.recent.shift();
        }
        if (!this.recent.length) {
            this.gauge.show(null);
            return;
        }
        const s = this.settings;
        const hz = median(this.recent);
        const j = classifyNote(event.midi, hz, null, s.toleranceMode, s.toleranceValue, s.referenceHz);
        this.gauge.show({ hz, cents: j.cents, outcome: j.outcome, detectedMidi: j.detectedMidi });
    }

    showTarget(midi) {
        this.currentTargetMidi = midi;
        if (midi == null) {
            this.gauge.setTarget(null, null, 0);
            return;
        }
        const s = this.settings;
        this.gauge.setTarget(midi, toleranceBandCents(midi, s.toleranceMode, s.toleranceValue, s.referenceHz), expectedHz(midi, s.referenceHz));
    }

    finalize(i) {
        const s = this.settings;
        const event = this.timeline.events[i];
        const heard = summarizeFrames(this.buffers[i], MIN_CLARITY);
        this.buffers[i] = null;
        const j = classifyNote(event.midi, heard.hz, heard.clarity, s.toleranceMode, s.toleranceValue, s.referenceHz);
        const page = this.score.pageOf(event.index);
        const result = {
            note_index: event.index,
            expected_midi: event.midi,
            detected_hz: heard.hz,
            clarity: heard.clarity,
            outcome: j.outcome,
            cents: j.cents,
            measure: event.measure,
            page,
        };
        this.results.push(result);
        this.pending.push(result);
        this.score.colour(event.index, j.outcome);
        this.updateTally();

        const next = this.timeline.events[i + 1];
        if (!next || this.score.pageOf(next.index) !== page) {
            if (next) this.showPageToast(page);
            this.flush(false);
        }
    }

    flush(finished) {
        const batch = this.pending.map(({ note_index, expected_midi, detected_hz, clarity }) => ({ note_index, expected_midi, detected_hz, clarity }));
        this.pending = [];
        if (!this.session || (!batch.length && !finished)) return this.sendChain;
        const id = this.session.id;
        this.sendChain = this.sendChain
            .then(() => (this.saveFailed ? null : this.api.sendResults(id, batch, finished)))
            .then((res) => {
                for (const r of res?.results ?? []) {
                    const local = this.results.find((x) => x.note_index === r.note_index);
                    if (local && local.outcome !== r.outcome) {
                        // The server's outcome is the stored one; show that.
                        local.outcome = r.outcome;
                        local.cents = r.cents;
                        this.score.colour(r.note_index, r.outcome);
                    }
                }
                return res;
            })
            .catch((e) => {
                this.saveFailed = true;
                this.warn(`Results could not be saved (${e.message}). The report below is from this browser only.`);
                return null;
            });
        return this.sendChain;
    }

    async stop() {
        if (this.state !== 'playing') return;
        // Close the note being played if we have heard enough of it; drop the rest.
        const open = this.timeline.events[this.listenPos];
        if (open && this.buffers[this.listenPos]?.length >= 2) this.finalize(this.listenPos);
        await this.finish(true);
    }

    async finish(stoppedEarly = false) {
        this.setState('finishing');
        $('#countdown').hidden = true;
        this.gauge.show(null);
        const res = await this.flush(true);
        this.setState('done');
        this.showReport(res, stoppedEarly);
    }

    // ---------------------------------------------------------------- display

    setState(state) {
        this.state = state;
        document.body.dataset.playerState = state;
        $('#btn-start').disabled = !['ready', 'done'].includes(state);
        $('#btn-start').textContent = state === 'done' ? 'Play again' : 'Start';
        $('#btn-stop').disabled = state !== 'playing';
        $('#btn-skip').hidden = this.settings.mode !== 'wait';
        $('#btn-skip').disabled = state !== 'playing';
        for (const el of this.form.elements) el.disabled = state === 'playing' || state === 'starting' || state === 'finishing';
        $('#status').textContent = {
            loading: 'Loading the score…',
            ready: this.settings.mode === 'wait'
                ? 'Ready. Press Start, then play the first note. The cursor waits for you.'
                : 'Ready. Press Start, wait for the count-in, then play along with the cursor.',
            starting: 'Starting the microphone…',
            playing: 'Listening…',
            finishing: 'Saving your run…',
            done: 'Done.',
            failed: '',
        }[state] ?? '';
    }

    updateProgress(ci) {
        const e = this.timeline.events[ci];
        $('#progress').textContent = `Note ${ci + 1} of ${this.timeline.events.length} · bar ${e.measure} · page ${this.score.pageOf(e.index)}`;
    }

    updateTally() {
        const counts = Object.fromEntries(OUTCOMES.map((v) => [v, 0]));
        for (const r of this.results) counts[r.outcome]++;
        for (const v of OUTCOMES) {
            const el = document.querySelector(`[data-tally="${v}"]`);
            if (el) el.textContent = counts[v];
        }
        const pct = this.results.length ? Math.round((100 * counts[IN_TUNE]) / this.results.length) : null;
        $('[data-tally-score]').textContent = pct === null ? '–' : `${pct}%`;
    }

    showPageToast(page) {
        const s = summarize(this.results.filter((r) => r.page === page));
        const toast = $('#toast');
        toast.innerHTML = `<strong>Page ${page}: ${s.score}% in tune</strong> · ${s.counts.sharp} too high · ${s.counts.flat} too low`
            + (s.counts.wrong_note ? ` · ${s.counts.wrong_note} wrong` : '')
            + (s.counts.missed ? ` · ${s.counts.missed} missed` : '');
        toast.hidden = false;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => (toast.hidden = true), 5000);
    }

    showReport(serverResult, stoppedEarly) {
        const s = summarize(this.results);
        const panel = $('#report');
        const score = serverResult?.score_pct ?? s.score;
        const total = this.timeline.events.length;
        const esc = (x) => String(x).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

        panel.querySelector('[data-report-score]').textContent = `${Math.round(score)}%`;
        panel.querySelector('[data-report-sub]').textContent =
            `${s.counts.in_tune} of ${s.total} notes in tune`
            + (stoppedEarly && s.total < total ? ` (stopped after ${s.total} of ${total})` : '')
            + (s.avgCents !== null ? ` · average offset ${s.avgCents > 0 ? '+' : ''}${s.avgCents} cents` : '')
            + ` · rule ±${this.settings.toleranceValue} ${this.settings.toleranceMode === 'hz' ? 'Hz' : 'cents'}`;

        panel.querySelector('[data-report-counts]').innerHTML = OUTCOMES.map((v) =>
            `<li class="pi-chip" data-outcome="${v}"><span class="pi-dot"></span>${OUTCOME_LABELS[v]} <strong>${s.counts[v]}</strong></li>`).join('');

        panel.querySelector('[data-report-pages]').innerHTML = s.pages.length > 1
            ? `<h4>By page</h4><table class="pi-table"><thead><tr><th>Page</th><th>Notes</th><th>In tune</th></tr></thead><tbody>${
                s.pages.map((p) => `<tr><td>${p.page}</td><td>${p.notes}</td><td>${p.score}%</td></tr>`).join('')}</tbody></table>`
            : '';

        panel.querySelector('[data-report-weakest]').innerHTML = s.weakest.length
            ? `<h4>Bars to practise</h4><ol>${s.weakest.map((m) => `<li>Bar ${m.measure}: ${m.inTune} of ${m.notes} in tune</li>`).join('')}</ol>`
            : '<h4>Bars to practise</h4><p>None. Every bar was in tune.</p>';

        const offTune = this.results.filter((r) => r.outcome === 'sharp' || r.outcome === 'flat')
            .sort((a, b) => Math.abs(b.cents) - Math.abs(a.cents)).slice(0, 5);
        panel.querySelector('[data-report-notes]').innerHTML = offTune.length
            ? `<h4>Furthest off</h4><ul>${offTune.map((r) =>
                `<li>Bar ${r.measure}, ${esc(noteName(r.expected_midi))}: ${r.cents > 0 ? '+' : '−'}${Math.abs(Math.round(r.cents))} cents (${r.outcome === 'sharp' ? 'too high' : 'too low'})</li>`).join('')}</ul>`
            : '';

        const link = panel.querySelector('[data-report-link]');
        if (this.session && serverResult) {
            link.href = `${this.config.reportUrl}/${this.session.id}`;
            link.hidden = false;
        } else {
            link.hidden = true;
        }
        panel.hidden = false;
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    hideReport() {
        $('#report').hidden = true;
    }

    warn(message) {
        const el = $('#warning');
        el.textContent = message;
        el.hidden = false;
    }

    clearWarning() {
        $('#warning').hidden = true;
    }

    fail(message) {
        this.setState('failed');
        this.warn(message);
    }
}

const configEl = document.getElementById('player-config');
if (configEl) {
    new Player(JSON.parse(configEl.textContent)).init();
}
