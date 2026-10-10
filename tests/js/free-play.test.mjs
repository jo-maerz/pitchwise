import { test } from 'node:test';
import assert from 'node:assert/strict';
import { NoteSegmenter, GAP_MS, MIN_NOTE_MS, SWITCH_FRAMES } from '../../resources/js/practice/free-play.js';
import { expectedHz } from '../../resources/js/practice/pitch-math.js';

const STEP = 16;
const A4 = expectedHz(69);

/** Feed `ms` of a steady tone (0 = silence); returns [notes finished meanwhile, next t]. */
function play(seg, hz, start, ms, clarity = 0.97) {
    const done = [];
    let t = start;
    for (; t < start + ms; t += STEP) {
        const n = seg.feed({ t, hz, clarity: hz ? clarity : 0 });
        if (n) done.push(n);
    }
    return [done, t];
}

test('the nearest note is the target and the offset is measured against it', () => {
    const seg = new NoteSegmenter();
    const sharpA = A4 * 2 ** (40 / 1200);
    let [, t] = play(seg, sharpA, 0, 400);
    const [done] = play(seg, 0, t, GAP_MS + 2 * STEP);
    assert.equal(done.length, 1);
    assert.equal(done[0].name, 'A4');
    assert.ok(Math.abs(done[0].cents - 40) < 0.5, `cents ${done[0].cents}`);
    assert.equal(done[0].outcome, 'sharp');
});

test('a note inside the tolerance is in tune; flat is negative', () => {
    const seg = new NoteSegmenter({ tolerance: 30 });
    let [, t] = play(seg, A4 * 2 ** (-10 / 1200), 0, 300);
    let [done] = play(seg, 0, t, GAP_MS + STEP);
    assert.equal(done[0].outcome, 'in_tune');
    [, t] = play(seg, A4 * 2 ** (-40 / 1200), t + 200, 300);
    [done] = play(seg, 0, t, GAP_MS + STEP);
    assert.equal(done[0].outcome, 'flat');
    assert.ok(done[0].cents < -39);
});

test('a change of pitch ends one note and starts the next, without a gap', () => {
    const seg = new NoteSegmenter();
    const notes = [];
    let t = 0;
    for (const midi of [69, 71, 72]) {
        const [done, next] = play(seg, expectedHz(midi), t, 300);
        notes.push(...done);
        t = next;
    }
    notes.push(seg.flush());
    assert.deepEqual(notes.map((n) => n.name), ['A4', 'B4', 'C5']);
});

test('a brief glitch on another pitch does not split a note', () => {
    const seg = new NoteSegmenter();
    let [, t] = play(seg, A4, 0, 200);
    [, t] = play(seg, A4 * 2, t, (SWITCH_FRAMES - 1) * STEP); // a short octave error
    [, t] = play(seg, A4, t, 200);
    const note = seg.flush();
    assert.equal(note.name, 'A4');
    assert.equal(seg.flush(), null);
});

test('sounds that are too short or unclear are not notes', () => {
    const seg = new NoteSegmenter();
    let [, t] = play(seg, A4, 0, MIN_NOTE_MS - 2 * STEP);
    let [done] = play(seg, 0, t, GAP_MS + STEP);
    assert.equal(done.length, 0);
    [, t] = play(seg, A4, t + 100, 400, 0.5); // unclear
    assert.equal(seg.flush(), null);
});

test('a short dropout inside a note does not end it', () => {
    const seg = new NoteSegmenter();
    let [, t] = play(seg, A4, 0, 200);
    let [done] = play(seg, 0, t, GAP_MS - 3 * STEP);
    assert.equal(done.length, 0);
    [, t] = play(seg, A4, t + GAP_MS, 200);
    assert.equal(seg.flush().name, 'A4');
});
