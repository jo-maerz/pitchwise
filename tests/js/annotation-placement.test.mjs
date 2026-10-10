import { test } from 'node:test';
import assert from 'node:assert/strict';
import { overlayPlacement } from '../../resources/js/annotate/placement.js';

const box = { x: 10, y: 20, width: 600, height: 800 };

/** The rectangle pdf-lib covers for an image drawn at (x, y), turned anticlockwise by `rotate` degrees. */
const covered = ({ x, y, width, height, rotate }) => {
    const rad = (rotate * Math.PI) / 180;
    const right = [Math.cos(rad), Math.sin(rad)];
    const up = [-Math.sin(rad), Math.cos(rad)];
    const corners = [[0, 0], [width, 0], [0, height], [width, height]]
        .map(([a, b]) => [x + a * right[0] + b * up[0], y + a * right[1] + b * up[1]]);
    const xs = corners.map((c) => Math.round(c[0]));
    const ys = corners.map((c) => Math.round(c[1]));
    return { x: Math.min(...xs), y: Math.min(...ys), width: Math.max(...xs) - Math.min(...xs), height: Math.max(...ys) - Math.min(...ys) };
};

test('the overlay covers exactly the crop box at every page rotation', () => {
    for (const rotation of [0, 90, 180, 270, -90, 450]) {
        assert.deepEqual(covered(overlayPlacement(rotation, box)), box, `rotation ${rotation}`);
    }
});

test('a page shown sideways gets an overlay as wide as the page is tall', () => {
    const { width, height } = overlayPlacement(90, box);
    assert.deepEqual([width, height], [800, 600]);
});

test('the top-left of the picture lands on the corner the viewer sees top-left', () => {
    const expected = {
        0: [box.x, box.y + box.height],
        90: [box.x, box.y],
        180: [box.x + box.width, box.y],
        270: [box.x + box.width, box.y + box.height],
    };
    for (const [rotation, corner] of Object.entries(expected)) {
        const p = overlayPlacement(Number(rotation), box);
        const rad = (p.rotate * Math.PI) / 180;
        const topLeft = [p.x - Math.sin(rad) * p.height, p.y + Math.cos(rad) * p.height];
        assert.deepEqual(topLeft.map(Math.round), corner, `rotation ${rotation}`);
    }
});
