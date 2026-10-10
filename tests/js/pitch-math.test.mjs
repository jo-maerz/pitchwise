import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { classifyNote, noteName, toleranceBandCents, expectedHz } from '../../resources/js/practice/pitch-math.js';

const cases = JSON.parse(readFileSync(new URL('../fixtures/pitch-rule-cases.json', import.meta.url)));

test('the browser judges every shared case exactly like the PHP API', () => {
    for (const c of cases) {
        const got = classifyNote(c.expected_midi, c.detected_hz, c.clarity, c.mode, c.tolerance, c.reference_hz);
        assert.equal(got.outcome, c.outcome, c.name);
        assert.equal(got.cents, c.cents, `${c.name} (cents)`);
        assert.equal(got.detectedMidi, c.detected_midi, `${c.name} (detected midi)`);
    }
});

test('note names and frequencies', () => {
    assert.equal(noteName(69), 'A4');
    assert.equal(noteName(55), 'G3');
    assert.equal(noteName(73), 'C♯5');
    assert.equal(expectedHz(69, 442), 442);
    assert.ok(Math.abs(expectedHz(55) - 196) < 0.01);
});

test('a Hz tolerance is wide on low notes and narrow on high ones', () => {
    const g3 = toleranceBandCents(55, 'hz', 30);
    const e6 = toleranceBandCents(88, 'hz', 30);
    assert.ok(g3.high > 100, `G3 +30 Hz is more than a semitone (${g3.high})`);
    assert.ok(e6.high < 40, `E6 +30 Hz is under 40 cents (${e6.high})`);
    assert.deepEqual(toleranceBandCents(55, 'cents', 30), { low: -30, high: 30 });
});
