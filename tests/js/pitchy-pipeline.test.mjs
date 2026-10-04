/*
 * End-to-end check of the detection path on synthetic violin-like tones:
 * pitchy (McLeod) frames → median of clear frames → verdict.
 * Needs the npm dependencies installed (npm install).
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PitchDetector } from 'pitchy';
import { judge, expectedHz } from '../../resources/js/practice/pitch-math.js';
import { summarizeFrames } from '../../resources/js/practice/timeline.js';

const RATE = 48000;
const N = 2048;

/** A sawtooth-ish tone (rich in harmonics, like a bowed string) with vibrato and a little noise. */
function bowedTone(hz, ms, { vibratoCents = 15, vibratoHz = 5.5, noise = 0.02, seed = 1 } = {}) {
    const out = new Float32Array(Math.round((RATE * ms) / 1000));
    let phase = 0;
    let s = seed;
    const rand = () => ((s = (s * 16807) % 2147483647) / 2147483647) * 2 - 1;
    for (let i = 0; i < out.length; i++) {
        const t = i / RATE;
        const f = hz * 2 ** ((vibratoCents * Math.sin(2 * Math.PI * vibratoHz * t)) / 1200);
        phase += (2 * Math.PI * f) / RATE;
        let v = 0;
        for (let h = 1; h <= 8; h++) v += Math.sin(h * phase) / h;
        out[i] = 0.25 * v + noise * rand();
    }
    return out;
}

function framesOf(signal) {
    const detector = PitchDetector.forFloat32Array(N);
    detector.minVolumeDecibels = -30;
    const frames = [];
    for (let start = 0; start + N <= signal.length; start += 800) { // ~60 frames/s like requestAnimationFrame
        const [hz, clarity] = detector.findPitch(signal.subarray(start, start + N), RATE);
        frames.push({ hz, clarity });
    }
    return frames;
}

const cases = [
    ['open G string, in tune', 55, 0, 'in_tune'],
    ['open A string 40 cents sharp', 69, 40, 'sharp'],
    ['E5 35 cents flat', 76, -35, 'flat'],
    ['C♯5 played a semitone high', 73, 100, 'wrong_note'],
    ['high A6 in tune', 93, 10, 'in_tune'],
];

for (const [name, midi, offset, verdict] of cases) {
    test(`pitchy pipeline: ${name}`, () => {
        const hz = expectedHz(midi) * 2 ** (offset / 1200);
        const heard = summarizeFrames(framesOf(bowedTone(hz, 600, { seed: midi })));
        assert.ok(heard.hz, 'some clear frames');
        const j = judge(midi, heard.hz, heard.clarity);
        assert.equal(j.verdict, verdict, `${name}: heard ${heard.hz} Hz, ${j.cents} cents`);
        assert.ok(Math.abs(j.cents - offset) < 6, `${name}: measured ${j.cents} cents, played ${offset}`);
    });
}

test('pitchy pipeline: silence is missed', () => {
    const heard = summarizeFrames(framesOf(new Float32Array(RATE / 2)));
    assert.equal(judge(69, heard.hz, heard.clarity).verdict, 'missed');
});
