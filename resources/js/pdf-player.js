import * as pdfjs from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { openMicrophone } from './practice/audio.js';
import { NoteSegmenter } from './practice/free-play.js';
import { Gauge } from './practice/gauge.js';
import { MIN_CLARITY, OUTCOME_LABELS, expectedHz, classifyNote, nearestMidi, noteName, toleranceBandCents } from './practice/pitch-math.js';
import { SETTING_LIMITS, clamp, loadSettings, saveSettings } from './practice/settings.js';
import { median } from './practice/timeline.js';

/**
 * The PDF page: the original sheet music, plus the live tuner. The app knows no notes here, so the
 * target is always the nearest note, and every played note is listed with its distance from it.
 * Nothing is saved: without notes to follow there is nothing to check a run against.
 */
pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;

const MAX_LOG = 60;

const configEl = document.getElementById('pdf-player-config');
if (configEl) {
    const config = JSON.parse(configEl.textContent);
    const settings = loadSettings(config.defaults);
    const $ = (id) => document.getElementById(id);

    const gauge = new Gauge($('gauge'));
    gauge.root.querySelector('.pi-gauge-caption').textContent = 'Nearest note';
    const form = $('tuner-settings');
    const button = $('btn-listen');
    const warning = $('warning');
    const logEl = $('note-log');
    const summaryEl = $('log-summary');
    const clearButton = $('btn-clear');

    let mic = null;
    let recent = [];
    let target = null;
    let segmenter = null;
    let played = [];

    const showWarning = (text) => {
        warning.textContent = text;
        warning.hidden = false;
    };

    // --- settings ---------------------------------------------------------
    form.referenceHz.value = settings.referenceHz;
    form.toleranceValue.value = settings.toleranceValue;
    for (const r of form.querySelectorAll('[name=toleranceMode]')) r.checked = r.value === settings.toleranceMode;

    const readSettings = () => {
        settings.referenceHz = clamp(form.referenceHz.value, SETTING_LIMITS.referenceHz, 440);
        settings.toleranceMode = form.querySelector('[name=toleranceMode]:checked')?.value === 'hz' ? 'hz' : 'cents';
        settings.toleranceValue = clamp(form.toleranceValue.value, SETTING_LIMITS.toleranceCents, 30);
        document.querySelector('[data-tolerance-unit]').textContent = settings.toleranceMode === 'hz' ? 'Hz' : 'cents';
        saveSettings(settings);
        target = null;
        if (mic) segmenter = newSegmenter();
    };
    const newSegmenter = () => new NoteSegmenter({ referenceHz: settings.referenceHz, mode: settings.toleranceMode, tolerance: settings.toleranceValue });
    form.addEventListener('change', readSettings);
    form.addEventListener('submit', (e) => e.preventDefault());
    readSettings();

    // --- the list of played notes ----------------------------------------
    const offset = (cents) => `${cents > 0 ? '+' : ''}${Math.round(cents)} cents`;

    const renderLog = () => {
        clearButton.hidden = played.length === 0;
        if (played.length === 0) {
            summaryEl.textContent = 'Nothing yet.';
            logEl.innerHTML = '';
            return;
        }
        const inTune = played.filter((n) => n.outcome === 'in_tune').length;
        const avg = played.reduce((sum, n) => sum + n.cents, 0) / played.length;
        summaryEl.textContent = `${inTune} of ${played.length} in tune · average ${offset(avg)}`;
        logEl.innerHTML = [...played].reverse().map((n) => `
            <li class="flex items-center justify-between gap-2 border-b border-gray-100 py-1.5" data-outcome="${n.outcome}">
                <span><span class="pi-dot"></span><strong>${n.name}</strong></span>
                <span class="tabular-nums">${offset(n.cents)}</span>
                <span class="w-16 text-right text-gray-600">${OUTCOME_LABELS[n.outcome] ?? n.outcome}</span>
            </li>`).join('');
    };

    const record = (note) => {
        if (!note) return;
        played.push(note);
        if (played.length > MAX_LOG) played.shift();
        renderLog();
    };

    clearButton.addEventListener('click', () => {
        played = [];
        renderLog();
    });

    // --- microphone -------------------------------------------------------
    const loop = () => {
        if (!mic) return;
        const frame = mic.read();
        const now = performance.now();
        record(segmenter.feed({ t: now, hz: frame.hz, clarity: frame.clarity }));

        if (frame.hz > 0 && frame.clarity >= MIN_CLARITY) {
            recent.push(frame.hz);
            if (recent.length > 4) recent.shift();
            const hz = median(recent);
            const midi = nearestMidi(hz, settings.referenceHz);
            if (midi !== target) {
                target = midi;
                gauge.setTarget(midi, toleranceBandCents(midi, settings.toleranceMode, settings.toleranceValue, settings.referenceHz), expectedHz(midi, settings.referenceHz));
            }
            const j = classifyNote(midi, hz, null, settings.toleranceMode, settings.toleranceValue, settings.referenceHz);
            gauge.show({ hz, cents: j.cents, outcome: j.outcome, detectedMidi: j.detectedMidi });
        } else if (frame.hz === 0) {
            recent = [];
            gauge.show(null);
        }
        requestAnimationFrame(loop);
    };

    button.addEventListener('click', async () => {
        if (mic) {
            record(segmenter.flush());
            await mic.close();
            mic = null;
            button.textContent = 'Start listening';
            gauge.show(null);
            return;
        }
        try {
            mic = await openMicrophone();
            segmenter = newSegmenter();
            button.textContent = 'Stop';
            loop();
        } catch (e) {
            showWarning(e.name === 'NotAllowedError'
                ? 'Microphone access was blocked. Allow it in the address bar and try again.'
                : `Microphone problem: ${e.message}`);
        }
    });

    // --- the PDF ----------------------------------------------------------
    const pagesEl = $('pdf-pages');
    const statusEl = $('pdf-status');
    let pdf = null;
    let renderedWidth = 0;
    let rendering = Promise.resolve();

    const renderPages = async () => {
        const width = Math.floor(pagesEl.clientWidth);
        if (!pdf || width < 100 || width === renderedWidth) return;
        renderedWidth = width;
        const ratio = window.devicePixelRatio || 1;
        pagesEl.replaceChildren();
        for (let n = 1; n <= pdf.numPages; n++) {
            const page = await pdf.getPage(n);
            const scale = width / page.getViewport({ scale: 1 }).width;
            const viewport = page.getViewport({ scale: scale * ratio });
            const canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.style.width = `${width}px`;
            canvas.className = 'bg-white shadow-sm';
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', `Page ${n} of ${pdf.numPages} of the score`);
            pagesEl.append(canvas);
            await page.render({ canvas, viewport }).promise;
        }
    };
    const scheduleRender = () => { rendering = rendering.then(renderPages).catch((e) => showWarning(`The PDF could not be drawn: ${e.message}`)); };

    pdfjs.getDocument({ url: config.pdfUrl, withCredentials: true }).promise
        .then((doc) => {
            pdf = doc;
            statusEl.textContent = `${doc.numPages} ${doc.numPages === 1 ? 'page' : 'pages'}`;
            scheduleRender();
            let timer;
            new ResizeObserver(() => { clearTimeout(timer); timer = setTimeout(scheduleRender, 250); }).observe(pagesEl);
        })
        .catch((e) => {
            statusEl.textContent = '';
            showWarning(`The PDF could not be loaded: ${e.message}`);
        });
}
