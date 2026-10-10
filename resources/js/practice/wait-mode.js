import { MIN_CLARITY, WRONG_NOTE_CENTS, cents, expectedHz } from './pitch-math.js';

/**
 * "Wait for me" mode: no tempo. The cursor stays on a note until the player has held it,
 * then moves on. Pure logic (no DOM, no audio), so it can be tested with synthetic frames.
 *
 * A note is accepted when frames within ±50 cents of it (the same boundary as "wrong note")
 * have been heard for HOLD_MS. A wrong note therefore never advances the cursor; the player
 * just tries again. The accepted frames are handed back to be judged like any other note,
 * so a note that is 40 cents flat still advances, but shows up as "too low".
 *
 * Right after a note is accepted it is still sounding. While that lasts the follower is
 * `lingering`: frames at the old pitch are not judged against the next note (so the dial can
 * stay quiet instead of calling the old note "wrong"), and they cannot count towards a repeat
 * of the same pitch. Lingering ends when the player
 *   - plays a different pitch,
 *   - stops (no clear pitch for GAP_MS), or
 *   - plays the same pitch again: the loudness dips and comes back (a new bow or a new pluck).
 */

export const HOLD_MS = 150;
/** A dropout (unclear frame, brief slip) shorter than this does not restart the hold. */
export const GRACE_MS = 60;
/** No clear pitch for this long means the previous note has stopped. */
export const GAP_MS = 80;
/** A dip of this many dB followed by a rise of the same size is a new attack of the same pitch. */
export const ONSET_DB = 3;

export class WaitFollower {
    /**
     * @param {Array<{index:number, midi:number}>} events notes to play, in order
     * @param {{referenceHz?:number}} opts
     */
    constructor(events, { referenceHz = 440 } = {}) {
        this.events = events;
        this.referenceHz = referenceHz;
        this.i = 0;
        this.resetHold();
        this.lingering = false;
    }

    get index() {
        return this.i;
    }

    get done() {
        return this.i >= this.events.length;
    }

    resetHold() {
        this.holdSince = null;
        this.badSince = null;
        this.frames = [];
    }

    /**
     * @param {{t:number, hz:number, clarity:number, level?:number}} frame `level` is the loudness in dB
     * @returns {{index:number, frames:Array}|null} the accepted note (its position in `events`
     *          and the frames that matched), or null while still waiting
     */
    feed(frame) {
        if (this.done) return null;
        const clear = frame.hz > 0 && frame.clarity >= MIN_CLARITY;

        if (this.lingering && !this.stillLingering(frame, clear)) this.lingering = false;
        if (this.lingering) return null;

        const target = expectedHz(this.events[this.i].midi, this.referenceHz);
        if (clear && Math.abs(cents(frame.hz, target)) <= WRONG_NOTE_CENTS) {
            this.holdSince ??= frame.t;
            this.badSince = null;
            this.frames.push(frame);
            if (frame.t - this.holdSince >= HOLD_MS) return this.advance(this.frames);
            return null;
        }

        if (this.holdSince !== null) {
            this.badSince ??= frame.t;
            if (frame.t - this.badSince > GRACE_MS) this.resetHold();
        }
        return null;
    }

    stillLingering(frame, clear) {
        if (!clear) {
            this.silentSince ??= frame.t;
            return frame.t - this.silentSince < GAP_MS;
        }
        this.silentSince = null;
        if (Math.abs(cents(frame.hz, expectedHz(this.prevMidi, this.referenceHz))) > WRONG_NOTE_CENTS) return false; // another pitch
        if (!Number.isFinite(frame.level)) return true;

        // Same pitch: only a fresh attack (dip, then rise) counts as playing it again.
        if (frame.level > this.peak) {
            this.peak = this.trough = frame.level;
        } else if (frame.level < this.trough) {
            this.trough = frame.level;
        }
        return !(this.peak - this.trough >= ONSET_DB && frame.level - this.trough >= ONSET_DB);
    }

    /** Give up on the current note (it will count as missed). */
    skip() {
        if (this.done) return null;
        const accepted = this.advance([]);
        this.lingering = false;
        return accepted;
    }

    /** 0..1: how much of the hold has been heard, for a progress cue. */
    get holdProgress() {
        if (this.holdSince === null || !this.frames.length) return 0;
        const last = this.frames[this.frames.length - 1].t;
        return Math.min(1, (last - this.holdSince) / HOLD_MS);
    }

    advance(frames) {
        const accepted = { index: this.i, frames };
        this.prevMidi = this.events[this.i].midi;
        const lastLevel = frames.length ? frames[frames.length - 1].level : undefined;
        this.peak = this.trough = Number.isFinite(lastLevel) ? lastLevel : -Infinity;
        this.silentSince = null;
        this.lingering = true;
        this.i++;
        this.resetHold();
        return accepted;
    }
}
