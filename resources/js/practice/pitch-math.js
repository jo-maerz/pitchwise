/**
 * The pitch rule, shared by the live display and the end-of-run report.
 * Mirrors api/src/PitchRule.php; both are tested against tests/fixtures/pitch-rule-cases.json.
 *
 *   expected Hz = reference · 2^((midi − 69) / 12)
 *   cents       = 1200 · log2(played / expected)
 *
 * In tune: within the tolerance (±N cents, default 30, or ±N Hz — a setting).
 * Sharp / flat: outside the tolerance, up to ±50 cents. Wrong note: further than 50 cents,
 * i.e. closer to a neighbouring note than to the written one.
 * Missed: no clear pitch in the note's window.
 */

export const IN_TUNE = 'in_tune';
export const SHARP = 'sharp';
export const FLAT = 'flat';
export const WRONG_NOTE = 'wrong_note';
export const MISSED = 'missed';
export const OUTCOMES = [IN_TUNE, SHARP, FLAT, WRONG_NOTE, MISSED];

export const WRONG_NOTE_CENTS = 50;
export const MIN_CLARITY = 0.9;

export const OUTCOME_LABELS = {
    [IN_TUNE]: 'In tune',
    [SHARP]: 'Too high',
    [FLAT]: 'Too low',
    [WRONG_NOTE]: 'Wrong note',
    [MISSED]: 'Missed',
};

const NAMES = ['C', 'C♯', 'D', 'E♭', 'E', 'F', 'F♯', 'G', 'G♯', 'A', 'B♭', 'B'];

export function expectedHz(midi, referenceHz = 440) {
    return referenceHz * 2 ** ((midi - 69) / 12);
}

export function cents(playedHz, targetHz) {
    return 1200 * Math.log2(playedHz / targetHz);
}

export function nearestMidi(hz, referenceHz = 440) {
    return Math.max(0, Math.min(127, Math.round(69 + 12 * Math.log2(hz / referenceHz))));
}

export function noteName(midi) {
    return NAMES[((midi % 12) + 12) % 12] + (Math.floor(midi / 12) - 1);
}

/** Rounds half away from zero, like PHP's round(), so both sides store the same number. */
function round2(x) {
    return (Math.sign(x) * Math.round(Math.abs(x) * 100)) / 100;
}

/**
 * @returns {{outcome: string, cents: number|null, detectedMidi: number|null}}
 */
export function classifyNote(expectedMidi, detectedHz, clarity, mode = 'cents', tolerance = 30, referenceHz = 440) {
    if (detectedHz == null || !(detectedHz > 0) || (clarity != null && clarity < MIN_CLARITY)) {
        return { outcome: MISSED, cents: null, detectedMidi: null };
    }
    const target = expectedHz(expectedMidi, referenceHz);
    const c = cents(detectedHz, target);
    const inTune = mode === 'hz' ? Math.abs(detectedHz - target) <= tolerance : Math.abs(c) <= tolerance;

    let outcome;
    if (inTune) outcome = IN_TUNE;
    else if (Math.abs(c) > WRONG_NOTE_CENTS) outcome = WRONG_NOTE;
    else outcome = c > 0 ? SHARP : FLAT;

    return { outcome, cents: round2(c), detectedMidi: nearestMidi(detectedHz, referenceHz) };
}

/**
 * The in-tune band around a note, in cents, for drawing on the gauge.
 * In cents mode it is symmetric. In Hz mode it depends on the note: wide on low notes,
 * narrow on high ones (that is the reason cents is the default).
 */
export function toleranceBandCents(expectedMidi, mode = 'cents', tolerance = 30, referenceHz = 440) {
    if (mode !== 'hz') return { low: -tolerance, high: tolerance };
    const target = expectedHz(expectedMidi, referenceHz);
    const low = target - tolerance > 0 ? cents(target - tolerance, target) : -Infinity;
    return { low, high: cents(target + tolerance, target) };
}
