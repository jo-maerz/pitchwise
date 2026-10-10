import { openMicrophone } from './practice/audio.js';
import { Gauge } from './practice/gauge.js';
import { MIN_CLARITY, expectedHz, classifyNote, nearestMidi, noteName, toleranceBandCents } from './practice/pitch-math.js';
import { median } from './practice/timeline.js';
import { loadSettings, saveSettings, clamp, SETTING_LIMITS } from './practice/settings.js';

/** Standalone tuner: no score, the target is whichever note you are closest to. */
const configEl = document.getElementById('tuner-config');
if (configEl) {
    const config = JSON.parse(configEl.textContent);
    const instruments = Object.fromEntries(config.instruments.map((i) => [i.key, i]));
    const settings = loadSettings(config.defaults);
    const gauge = new Gauge(document.getElementById('gauge'));
    const form = document.getElementById('tuner-settings');
    const button = document.getElementById('btn-listen');
    const stringsEl = document.getElementById('strings');
    const stringsTitle = document.getElementById('strings-title');
    const transposeNote = document.getElementById('transpose-note');
    let mic = null;
    let recent = [];
    let target = null;

    form.referenceHz.value = settings.referenceHz;
    form.toleranceValue.value = settings.toleranceValue;
    for (const r of form.querySelectorAll('[name=toleranceMode]')) r.checked = r.value === settings.toleranceMode;
    form.instrument.value = settings.instrument ?? 'violin';

    const read = () => {
        settings.referenceHz = clamp(form.referenceHz.value, SETTING_LIMITS.referenceHz, 440);
        settings.toleranceMode = form.querySelector('[name=toleranceMode]:checked')?.value === 'hz' ? 'hz' : 'cents';
        settings.toleranceValue = clamp(form.toleranceValue.value, SETTING_LIMITS.toleranceCents, 30);
        settings.instrument = instruments[form.instrument.value] ? form.instrument.value : 'violin';
        document.querySelector('[data-tolerance-unit]').textContent = settings.toleranceMode === 'hz' ? 'Hz' : 'cents';
        saveSettings(settings);
        const instrument = instruments[settings.instrument];
        const written = (midi) => (instrument.transpose ? ` · written ${noteName(midi - instrument.transpose)}` : '');
        stringsTitle.textContent = instrument.tuning.length === 0 ? '' : instrument.family === 'Strings' ? 'Open strings' : 'Tuning notes (concert pitch)';
        stringsEl.innerHTML = instrument.tuning.map(([name, midi]) =>
            `<li data-midi="${midi}"><strong>${name}</strong> <span>${noteName(midi)}${written(midi)} · ${expectedHz(midi, settings.referenceHz).toFixed(1)} Hz</span></li>`).join('');
        transposeNote.hidden = !instrument.transpose;
        transposeNote.textContent = instrument.transpose
            ? `The dial names the sounding note and, in brackets, the note you read on a ${instrument.label} part.`
            : '';
        target = null;
    };
    form.addEventListener('change', read);
    form.addEventListener('submit', (e) => e.preventDefault());
    read();

    const loop = () => {
        if (!mic) return;
        const frame = mic.read();
        if (frame.hz > 0 && frame.clarity >= MIN_CLARITY) {
            recent.push(frame.hz);
            if (recent.length > 4) recent.shift();
            const hz = median(recent);
            const midi = nearestMidi(hz, settings.referenceHz);
            if (midi !== target) {
                target = midi;
                gauge.setTarget(midi, toleranceBandCents(midi, settings.toleranceMode, settings.toleranceValue, settings.referenceHz), expectedHz(midi, settings.referenceHz), instruments[settings.instrument].transpose);
                for (const li of stringsEl.children) li.classList.toggle('is-active', Number(li.dataset.midi) === midi);
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
            await mic.close();
            mic = null;
            button.textContent = 'Start listening';
            gauge.show(null);
            return;
        }
        try {
            mic = await openMicrophone();
            button.textContent = 'Stop';
            loop();
        } catch (e) {
            document.getElementById('warning').textContent = e.name === 'NotAllowedError'
                ? 'Microphone access was blocked. Allow it in the address bar and try again.'
                : `Microphone problem: ${e.message}`;
            document.getElementById('warning').hidden = false;
        }
    });
}
