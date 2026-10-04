import { judge, nearestMidi, noteName } from './pitch-math.js';
import { median } from './timeline.js';

/**
 * Turns a stream of pitch frames into discrete played notes, with no score to compare against.
 * The "right" note is simply the nearest one on the keyboard, so a note played a quarter-tone
 * off, or a wrong note that happens to be near another, is judged against that neighbour.
 * Used on the PDF page, where the app has no notes to follow.
 */

/** A different nearest note must last this many frames before it counts as a new note (ignores glitches and octave jumps). */
export const SWITCH_FRAMES = 4;
/** Silence this long ends a note. */
export const GAP_MS = 100;
/** Sounds shorter than this are bow noise or a stray overtone, not notes. */
export const MIN_NOTE_MS = 90;
const MIN_CLARITY = 0.9;

export class NoteSegmenter {
    constructor({ referenceHz = 440, mode = 'cents', tolerance = 30 } = {}) {
        this.referenceHz = referenceHz;
        this.mode = mode;
        this.tolerance = tolerance;
        this.current = null;   // { midi, frames: [{t, hz}] }
        this.pending = null;   // a different note that has not lasted long enough yet
        this.lastHeard = 0;
    }

    /** @param {{t:number, hz:number, clarity:number}} frame  t in milliseconds. @returns the note that just ended, or null */
    feed({ t, hz, clarity }) {
        if (!(hz > 0) || clarity < MIN_CLARITY) {
            if (this.current && t - this.lastHeard >= GAP_MS) return this.#end();
            return null;
        }

        this.lastHeard = t;
        const frame = { t, hz };
        const midi = nearestMidi(hz, this.referenceHz);

        if (!this.current) {
            this.current = { midi, frames: [frame] };
            return null;
        }
        if (midi === this.current.midi) {
            this.current.frames.push(frame);
            this.pending = null;
            return null;
        }

        if (this.pending?.midi === midi) this.pending.frames.push(frame);
        else this.pending = { midi, frames: [frame] };
        if (this.pending.frames.length < SWITCH_FRAMES) return null;

        const finished = this.#end();
        this.current = this.pending;
        this.pending = null;
        return finished;
    }

    /** Call when listening stops, so the last note is not lost. */
    flush() {
        return this.current ? this.#end() : null;
    }

    #end() {
        const { midi, frames } = this.current;
        this.current = null;
        this.pending = null;

        const startedAt = frames[0].t;
        const durationMs = frames[frames.length - 1].t - startedAt;
        if (durationMs < MIN_NOTE_MS) return null;

        const hz = median(frames.map((f) => f.hz));
        const j = judge(midi, hz, null, this.mode, this.tolerance, this.referenceHz);
        return { midi, name: noteName(midi), hz, cents: j.cents, verdict: j.verdict, startedAt, durationMs };
    }
}
