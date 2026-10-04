/** Player settings, remembered per browser. Storage can be unavailable (private mode), so every access is guarded. */
const KEY = 'pitchwise.settings.v1';

export const SETTING_LIMITS = {
    bpm: [20, 300],
    toleranceCents: [1, 100],
    toleranceHz: [1, 100],
    referenceHz: [400, 480],
    latencyMs: [-500, 1000],
};

export function loadSettings(defaults) {
    let saved = {};
    try {
        saved = JSON.parse(localStorage.getItem(KEY) ?? '{}') ?? {};
    } catch {
        saved = {};
    }
    return {
        toleranceMode: defaults.toleranceMode ?? 'cents',
        toleranceValue: defaults.toleranceValue ?? 30,
        referenceHz: defaults.referenceHz ?? 440,
        latencyMs: 80,
        noiseGateDb: -30,
        countIn: true,
        metronome: false,
        layout: 'pages',
        ...saved,
    };
}

export function saveSettings(settings) {
    const { bpm, fromPage, toPage, ...persisted } = settings; // tempo and range belong to the piece, not the browser
    try {
        localStorage.setItem(KEY, JSON.stringify(persisted));
    } catch {
        /* not fatal: settings just won't be remembered */
    }
}

export function clamp(value, [min, max], fallback) {
    const n = Number(value);
    return Number.isFinite(n) ? Math.min(max, Math.max(min, n)) : fallback;
}
