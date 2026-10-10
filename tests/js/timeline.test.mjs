import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildTimeline, eventAt, median, summarizeFrames } from '../../resources/js/practice/timeline.js';
import { summarize } from '../../resources/js/practice/report.js';

const notes = [
    { note_index: 0, midi_pitch: 55, measure: 1, onset_beats: 0, duration_beats: 2 },
    { note_index: 1, midi_pitch: 62, measure: 1, onset_beats: 2, duration_beats: 1 },
    { note_index: 2, midi_pitch: 69, measure: 2, onset_beats: 4, duration_beats: 1 }, // a rest before it
];

test('timeline: count-in, tempo, listening window trimmed and shifted by latency', () => {
    const { events, countInMs, endMs } = buildTimeline(notes, { bpm: 60, countInBeats: 4, latencyMs: 100 });
    assert.equal(countInMs, 4000);
    assert.deepEqual(events[0], { index: 0, midi: 55, measure: 1, startMs: 4000, endMs: 6000, listenFromMs: 4500, listenToMs: 5700 });
    assert.equal(events[2].startMs, 8000);
    assert.equal(endMs, 9000);
});

test('timeline: a slice (one page) starts right after the count-in', () => {
    const { events } = buildTimeline(notes.slice(2), { bpm: 120, countInBeats: 2, latencyMs: 0 });
    assert.equal(events[0].startMs, 1000);
});

test('eventAt finds the written note, and null during rests and the count-in', () => {
    const { events } = buildTimeline(notes, { bpm: 60, countInBeats: 4 });
    assert.equal(eventAt(events, 3999), null);
    assert.equal(eventAt(events, 4000), 0);
    assert.equal(eventAt(events, 6500), 1);
    assert.equal(eventAt(events, 7500), null); // rest
    assert.equal(eventAt(events, 8100, 1), 2);
});

test('median and frame summary drop unclear frames and resist an octave jump', () => {
    assert.equal(median([3, 1, 2]), 2);
    assert.equal(median([4, 1, 2, 3]), 2.5);
    const frames = [
        { hz: 440.5, clarity: 0.97 }, { hz: 441, clarity: 0.95 }, { hz: 880, clarity: 0.92 },
        { hz: 439.8, clarity: 0.96 }, { hz: 300, clarity: 0.4 },
    ];
    const s = summarizeFrames(frames);
    assert.equal(s.frames, 4);
    assert.equal(s.hz, 440.75);
    assert.equal(summarizeFrames([{ hz: 440, clarity: 0.99 }]).hz, null, 'one frame is not enough');
});

test('report: score, pages and weakest bars', () => {
    const r = summarize([
        { outcome: 'in_tune', measure: 1, page: 1, cents: 4 },
        { outcome: 'sharp', measure: 1, page: 1, cents: 45 },
        { outcome: 'in_tune', measure: 2, page: 2, cents: -10 },
        { outcome: 'missed', measure: 3, page: 2, cents: null },
    ]);
    assert.equal(r.score, 50);
    assert.equal(r.counts.sharp, 1);
    assert.equal(r.avgCents, 13);
    assert.deepEqual(r.pages.map((p) => [p.page, p.score]), [[1, 50], [2, 50]]);
    assert.deepEqual(r.weakest.map((m) => m.measure), [3, 1]);
});
