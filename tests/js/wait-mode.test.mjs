import { test } from 'node:test';
import assert from 'node:assert/strict';
import { WaitFollower, HOLD_MS, GAP_MS } from '../../resources/js/practice/wait-mode.js';
import { expectedHz } from '../../resources/js/practice/pitch-math.js';

const events = [{ index: 0, midi: 55 }, { index: 1, midi: 62 }, { index: 2, midi: 62 }];
const STEP = 16;

/** Feed `ms` of a steady tone (or silence when hz is 0), starting at `start`; returns [acceptedOrNull, nextT]. */
function play(follower, hz, start, ms, level = -20) {
    let accepted = null;
    let t = start;
    for (; t < start + ms; t += STEP) {
        accepted = follower.feed({ t, hz, clarity: hz ? 0.97 : 0, level }) ?? accepted;
        if (accepted) break;
    }
    return [accepted, t + STEP];
}

test('the cursor waits until the right note has been held', () => {
    const f = new WaitFollower(events);
    let [a, t] = play(f, expectedHz(55), 0, HOLD_MS - 2 * STEP);
    assert.equal(a, null);
    assert.equal(f.index, 0);
    [a, t] = play(f, expectedHz(55), t, 100);
    assert.equal(a.index, 0);
    assert.ok(a.frames.length >= 2);
    assert.equal(f.index, 1);
});

test('a wrong note never advances, and a flat-but-close note does (to be judged later)', () => {
    const f = new WaitFollower(events);
    let [a, t] = play(f, expectedHz(57), 0, 1000); // a tone too high
    assert.equal(a, null);
    [a, t] = play(f, 0, t, 200);
    [a] = play(f, expectedHz(55) * 2 ** (-40 / 1200), t, 400); // 40 cents flat
    assert.equal(a.index, 0);
});

test('a short dropout does not restart the hold, a long one does', () => {
    const f = new WaitFollower(events);
    let [a, t] = play(f, expectedHz(55), 0, 100);
    [a, t] = play(f, 0, t, 2 * STEP); // brief gap, under the grace period
    [a, t] = play(f, expectedHz(55), t, 100);
    assert.equal(a?.index, 0);

    const g = new WaitFollower(events);
    [a, t] = play(g, expectedHz(55), 0, 100);
    [a, t] = play(g, 0, t, 200); // long gap: start again
    [a, t] = play(g, expectedHz(55), t, HOLD_MS - 3 * STEP);
    assert.equal(a, null);
});

test('the same pitch twice in a row: the ringing note does not count, a new attack does', () => {
    const f = new WaitFollower(events);
    let [a, t] = play(f, expectedHz(55), 0, 400);
    [a, t] = play(f, expectedHz(62), t, 400);
    assert.equal(a.index, 1);
    [a, t] = play(f, expectedHz(62), t, 600); // still ringing at the same loudness
    assert.equal(a, null);
    assert.equal(f.lingering, true);
    [a, t] = play(f, expectedHz(62), t, 3 * STEP, -28); // bow change: loudness dips, pitch stays clear
    [a, t] = play(f, expectedHz(62), t, 400, -20); // and comes back
    assert.equal(a.index, 2);
    assert.equal(f.done, true);
});

test('the same pitch twice in a row also works across a clear pause', () => {
    const f = new WaitFollower(events);
    let [a, t] = play(f, expectedHz(55), 0, 400);
    [a, t] = play(f, expectedHz(62), t, 400);
    [a, t] = play(f, 0, t, GAP_MS + 2 * STEP);
    [a, t] = play(f, expectedHz(62), t, 400);
    assert.equal(a.index, 2);
});

test('a slow crescendo or fade on a held note is not mistaken for a new attack', () => {
    const f = new WaitFollower(events);
    let [a, t] = play(f, expectedHz(55), 0, 400);
    [a, t] = play(f, expectedHz(62), t, 400);
    for (let level = -20; level <= -10; level += 1) [a, t] = play(f, expectedHz(62), t, STEP, level); // swell
    for (let level = -10; level >= -25; level -= 1) [a, t] = play(f, expectedHz(62), t, STEP, level); // fade
    assert.equal(a, null);
    assert.equal(f.lingering, true);
});

test('while the old note rings, it is lingering; a different pitch or silence ends that', () => {
    const f = new WaitFollower(events);
    let [a, t] = play(f, expectedHz(55), 0, 400);
    assert.equal(a.index, 0);
    assert.equal(f.lingering, true);
    play(f, expectedHz(55), t, 200); // old note still sounding: nothing to judge against the next note
    assert.equal(f.lingering, true);
    [a, t] = play(f, expectedHz(57), t + 200, 3 * STEP); // some other pitch: judged as before
    assert.equal(f.lingering, false);
});

test('skip gives up on a note: no frames, and the cursor moves on', () => {
    const f = new WaitFollower(events);
    const a = f.skip();
    assert.deepEqual(a, { index: 0, frames: [] });
    assert.equal(f.index, 1);
});
