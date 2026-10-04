/**
 * Timing: when each note should sound, and which part of that time we listen to.
 * All times are milliseconds from the moment the run starts (start of the count-in).
 */

/** Middle of the note we analyse: drop the first and last 20% (bow changes, slides). */
export const TRIM = 0.2;

/**
 * @param {Array<{note_index:number, midi_pitch:number, measure:number, onset_beats:number, duration_beats:number}>} notes
 *        the notes to play, in order (may be a slice of the piece, e.g. one page)
 * @param {{bpm:number, countInBeats:number, latencyMs:number}} opts
 */
export function buildTimeline(notes, { bpm, countInBeats = 4, latencyMs = 0 }) {
    if (!notes.length) return { events: [], countInMs: 0, endMs: 0 };
    const beatMs = 60000 / bpm;
    const countInMs = countInBeats * beatMs;
    const firstOnset = notes[0].onset_beats;

    const events = notes.map((n) => {
        const startMs = countInMs + (n.onset_beats - firstOnset) * beatMs;
        const durMs = n.duration_beats * beatMs;
        return {
            index: n.note_index,
            midi: n.midi_pitch,
            measure: n.measure,
            startMs,
            endMs: startMs + durMs,
            // what we listen to, shifted by the input latency setting
            listenFromMs: startMs + latencyMs + durMs * TRIM,
            listenToMs: startMs + latencyMs + durMs * (1 - TRIM),
        };
    });
    const last = events[events.length - 1];
    return { events, countInMs, endMs: Math.max(last.endMs, last.listenToMs) };
}

/** The note whose written time contains t (for the cursor and the live gauge), or null. */
export function eventAt(events, t, fromIndex = 0) {
    for (let i = Math.max(0, fromIndex); i < events.length; i++) {
        const e = events[i];
        if (t < e.startMs) return null;
        if (t < e.endMs) return i;
    }
    return null;
}

export function median(values) {
    if (!values.length) return null;
    const s = [...values].sort((a, b) => a - b);
    const mid = s.length >> 1;
    return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
}

/** Fewer clear frames than this inside the listening window counts as "missed". */
export const MIN_FRAMES = 2;

/**
 * Collapse the pitch frames heard during one note into one frequency.
 * Frames below the clarity threshold are dropped; the median resists vibrato
 * swings and the occasional octave jump.
 *
 * @param {Array<{hz:number, clarity:number}>} frames
 * @returns {{hz:number|null, clarity:number|null, frames:number}}
 */
export function summarizeFrames(frames, minClarity = 0.9) {
    const clear = frames.filter((f) => f.hz > 0 && f.clarity >= minClarity);
    if (clear.length < MIN_FRAMES) return { hz: null, clarity: null, frames: clear.length };
    return {
        hz: Math.round(median(clear.map((f) => f.hz)) * 100) / 100,
        clarity: Math.round(median(clear.map((f) => f.clarity)) * 1000) / 1000,
        frames: clear.length,
    };
}
