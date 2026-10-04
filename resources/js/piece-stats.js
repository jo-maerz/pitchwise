import { renderPitchChart } from './pitch-chart.js';

const dataEl = document.getElementById('piece-stats-data');
const canvas = document.getElementById('chart-piece-pitches');

if (dataEl && canvas) {
    const pitches = JSON.parse(dataEl.textContent);
    if (pitches.length) renderPitchChart(canvas, pitches);
}
