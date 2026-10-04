import { openMicrophone } from './practice/audio.js';
import { Gauge } from './practice/gauge.js';
import { MIN_CLARITY, expectedHz, judge, nearestMidi, noteName, toleranceBandCents } from './practice/pitch-math.js';
import { median } from './practice/timeline.js';
import { loadSettings, saveSettings, clamp, SETTING_LIMITS } from './practice/settings.js';

/** Standalone tuner: no score, the target is whichever note you are closest to. */
const STRINGS = {
    violin: [['G', 55], ['D', 62], ['A', 69], ['E', 76]],
    viola: [['C', 48], ['G', 55], ['D', 62], ['A', 69]],
    cello: [['C', 36], ['G', 43], ['D', 50], ['A', 57]],
    'double bass': [['E', 28], ['A', 33], ['D', 38], ['G', 43]],
};

const configEl = document.getElementById('tuner-config');
if (configEl) {
    const config = JSON.parse(configEl.textContent);
    const settings = loadSettings(config.defaults);
    const gauge = new Gauge(document.getElementById('gauge'));
    const form = document.getElementById('tuner-settings');
    const button = document.getElementById('btn-listen');
    const stringsEl = document.getElementById('strings');
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
        settings.instrument = STRINGS[form.instrument.value] ? form.instrument.value : 'violin';
        document.querySelector('[data-tolerance-unit]').textContent = settings.toleranceMode === 'hz' ? 'Hz' : 'cents';
        saveSettings(settings);
        stringsEl.innerHTML = STRINGS[settings.instrument].map(([name, midi]) =>
            `<li data-midi="${midi}"><strong>${name}</strong> <span>${noteName(midi)} · ${expectedHz(midi, settings.referenceHz).toFixed(1)} Hz</span></li>`).join('');
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
                gauge.setTarget(midi, toleranceBandCents(midi, settings.toleranceMode, settings.toleranceValue, settings.referenceHz), expectedHz(midi, settings.referenceHz));
                for (const li of stringsEl.children) li.classList.toggle('is-active', Number(li.dataset.midi) === midi);
            }
            const j = judge(midi, hz, null, settings.toleranceMode, settings.toleranceValue, settings.referenceHz);
            gauge.show({ hz, cents: j.cents, verdict: j.verdict, detectedMidi: j.detectedMidi });
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
