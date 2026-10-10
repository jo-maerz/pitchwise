import { IN_TUNE, SHARP, FLAT, WRONG_NOTE, OUTCOME_LABELS, noteName } from './pitch-math.js';

const RANGE = 50;
const CX = 150;
const CY = 150;
const R = 120;

function point(c, r = R) {
    const a = (Math.PI * (Math.max(-RANGE, Math.min(RANGE, c)) + RANGE)) / (2 * RANGE); // 0..π, left to right
    return [CX - r * Math.cos(a), CY - r * Math.sin(a)];
}

function arc(from, to, r = R) {
    const [x1, y1] = point(from, r);
    const [x2, y2] = point(to, r);
    return `M ${x1.toFixed(1)} ${y1.toFixed(1)} A ${r} ${r} 0 0 1 ${x2.toFixed(1)} ${y2.toFixed(1)}`;
}

/**
 * The live tuner dial: ±50 cents around the target note, the in-tune band shaded,
 * a needle for what is being played, and a word for the outcome (never colour alone).
 */
export class Gauge {
    constructor(root) {
        this.root = root;
        root.classList.add('pi-gauge');
        root.innerHTML = `
            <svg viewBox="0 0 300 175" role="img" aria-label="Tuning dial">
                <path class="pi-gauge-track" d="${arc(-RANGE, RANGE)}" />
                <path class="pi-gauge-band" d="" />
                ${[-50, -25, 0, 25, 50].map((c) => {
                    const [x1, y1] = point(c, R - 14);
                    const [x2, y2] = point(c, R + 6);
                    const [tx, ty] = point(c, R + 20);
                    return `<line class="pi-gauge-tick" x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" />
                        <text class="pi-gauge-label" x="${tx}" y="${ty}" text-anchor="middle">${c > 0 ? '+' : ''}${c}</text>`;
                }).join('')}
                <line class="pi-gauge-needle" x1="${CX}" y1="${CY}" x2="${CX}" y2="${CY - R + 8}" />
                <circle class="pi-gauge-hub" cx="${CX}" cy="${CY}" r="6" />
            </svg>
            <div class="pi-gauge-readout">
                <div class="pi-gauge-target"><span class="pi-gauge-caption">Target</span> <strong data-target>–</strong></div>
                <div class="pi-gauge-outcome" data-outcome aria-live="polite">Waiting for sound</div>
                <div class="pi-gauge-detail" data-detail>&nbsp;</div>
            </div>`;
        this.needle = root.querySelector('.pi-gauge-needle');
        this.band = root.querySelector('.pi-gauge-band');
        this.targetEl = root.querySelector('[data-target]');
        this.outcomeEl = root.querySelector('[data-outcome]');
        this.detailEl = root.querySelector('[data-detail]');
        this.lastBand = '';
    }

    /** `transpose`: semitones from written to sounding pitch; non-zero adds the written note for B♭, E♭ and F instruments. */
    setTarget(midi, band, hz, transpose = 0) {
        const written = transpose ? ` (written ${noteName(midi - transpose)})` : '';
        this.targetEl.textContent = midi == null ? '–' : `${noteName(midi)}${written} · ${hz.toFixed(1)} Hz`;
        const key = band ? `${band.low}|${band.high}` : '';
        if (key !== this.lastBand) {
            this.lastBand = key;
            this.band.setAttribute('d', band ? arc(Math.max(-RANGE, band.low), Math.min(RANGE, band.high)) : '');
        }
    }

    /** @param {{cents:number, hz:number, outcome:string, detectedMidi:number}|null} reading */
    show(reading) {
        const state = reading ? reading.outcome : 'idle';
        this.root.dataset.state = state;
        if (!reading) {
            this.needle.style.opacity = '0.25';
            this.outcomeEl.textContent = 'Waiting for sound';
            this.detailEl.innerHTML = '&nbsp;';
            return;
        }
        const c = Math.max(-RANGE, Math.min(RANGE, reading.cents));
        this.needle.style.opacity = '1';
        this.needle.style.transform = `rotate(${(c / RANGE) * 90}deg)`;
        const sign = reading.cents > 0 ? '+' : reading.cents < 0 ? '−' : '';
        const label = {
            [IN_TUNE]: 'In tune',
            [SHARP]: 'Too high ↑',
            [FLAT]: 'Too low ↓',
            [WRONG_NOTE]: `Wrong note: ${noteName(reading.detectedMidi)}`,
        }[reading.outcome] ?? OUTCOME_LABELS[reading.outcome];
        this.outcomeEl.textContent = label;
        this.detailEl.textContent = `${sign}${Math.abs(Math.round(reading.cents))} cents · ${reading.hz.toFixed(1)} Hz`;
    }
}
