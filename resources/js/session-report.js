import { renderPitchChart } from './pitch-chart.js';
import { OUTCOMES, OUTCOME_LABELS } from './practice/pitch-math.js';
import { ScoreView } from './practice/score-view.js';

/*
 * The run report: the score with every note coloured by its outcome, the offset written under
 * each note, a detail card for the note you click, and the intonation-by-note chart.
 * If a bar is played twice (repeats), the note shows the last pass; the table lists both.
 */

const $ = (sel) => document.querySelector(sel);
const SVG = 'http://www.w3.org/2000/svg';
const MINUS = '\u2212';

const dataEl = document.getElementById('report-data');

if (dataEl) {
    const data = JSON.parse(dataEl.textContent);

    const pitchEl = $('#chart-run-pitches');
    if (pitchEl && data.pitches.length) {
        const compare = data.piecePitches.length ? data.piecePitches : null;
        renderPitchChart(pitchEl, data.pitches, { compare, compareLabel: 'All your runs of this piece' });
    }

    showScore(data).catch((e) => {
        $('#score-message').textContent = `The score could not be drawn (${e.message}). The table below has every note.`;
        $('#score-message').hidden = false;
    });
}

function offsetLabel(n) {
    if (n.outcome === 'missed') return null;
    if (n.outcome === 'wrong_note') return `\u2192${n.detected ?? '?'}`;
    const c = Math.round(n.cents);
    return c === 0 ? '0' : `${c > 0 ? '+' : MINUS}${Math.abs(c)}`;
}

async function showScore(data) {
    const score = new ScoreView($('#score'));
    await score.load(data.scoreUrl);

    const byIndex = new Map();
    for (const n of data.notes) byIndex.set(n.index, n); // a repeated bar: the last pass wins
    for (const [index, n] of byIndex) score.colour(index, n.outcome);
    score.render('continuous');
    score.hideCursor();

    if (score.map.length !== byIndex.size && data.notes.length && score.map.length < data.notes.length) {
        $('#score-message').textContent = 'The drawn score has fewer notes than were checked, so some colours may be missing.';
        $('#score-message').hidden = false;
    }

    const outlineBar = (index) => {
        $('#score svg .pi-bar-outline')?.remove();
        const box = score.barRect(index);
        const svg = $('#score svg');
        if (!box || !svg) return null;
        const pad = 10;
        const rect = document.createElementNS(SVG, 'rect');
        rect.setAttribute('x', String(box.x - 2));
        rect.setAttribute('y', String(box.y - pad));
        rect.setAttribute('width', String(box.width + 4));
        rect.setAttribute('height', String(box.height + 2 * pad));
        rect.setAttribute('rx', '4');
        rect.setAttribute('class', 'pi-bar-outline');
        svg.appendChild(rect);
        return box;
    };

    // Bring the bar into view: scroll the score sideways only, and the page just enough to see the score card.
    const revealBar = (box) => {
        const scroller = $('#score');
        if (box) scroller.scrollTo({ left: Math.max(0, box.x + box.width / 2 - scroller.clientWidth / 2), behavior: 'smooth' });
        const card = scroller.parentElement.getBoundingClientRect();
        if (card.top < 0 || card.bottom > window.innerHeight) {
            scroller.parentElement.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    };

    const labels = [];
    const select = (index, { scroll = false } = {}) => {
        const n = byIndex.get(index);
        if (!n) return;
        for (const el of document.querySelectorAll('.is-selected')) el.classList.remove('is-selected');
        score.noteElement(index)?.classList.add('is-selected');
        document.querySelectorAll(`tr[data-index="${index}"]`).forEach((tr) => tr.classList.add('is-selected'));
        showDetail(n, data);
        if (scroll) score.noteElement(index)?.scrollIntoView({ block: 'center', inline: 'center', behavior: 'smooth' });
    };

    for (const [index, n] of byIndex) {
        const group = score.noteElement(index);
        if (!group) continue;
        group.classList.add('pi-note-clickable');
        group.addEventListener('click', () => select(index));

        const text = offsetLabel(n);
        if (text === null) continue;
        const box = group.getBBox();
        const label = document.createElementNS(SVG, 'text');
        label.textContent = text;
        label.setAttribute('x', String(box.x + box.width / 2));
        label.setAttribute('y', String(box.y + box.height + 13));
        label.setAttribute('text-anchor', 'middle');
        label.setAttribute('class', 'pi-offset-label');
        label.setAttribute('data-outcome', n.outcome);
        group.appendChild(label);
        labels.push(label);
    }

    const toggle = $('#toggle-offsets');
    const apply = () => labels.forEach((l) => l.setAttribute('display', toggle.checked ? 'inline' : 'none'));
    toggle.addEventListener('change', apply);
    apply();

    for (const bar of document.querySelectorAll('[data-bar]')) {
        const first = data.notes.filter((n) => n.measure === Number(bar.dataset.bar)).sort((a, b) => a.index - b.index)[0];
        if (!first) continue;
        const go = () => {
            select(first.index);
            for (const el of document.querySelectorAll('[data-bar].is-selected-bar')) el.classList.remove('is-selected-bar');
            bar.classList.add('is-selected-bar');
            revealBar(outlineBar(first.index));
        };
        bar.classList.add('cursor-pointer');
        bar.addEventListener('click', go);
        bar.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
        });
    }

    for (const tr of document.querySelectorAll('tr[data-index]')) {
        tr.addEventListener('click', () => select(Number(tr.dataset.index), { scroll: true }));
    }

    // Start on the worst note, so the card is never empty.
    const worst = [...byIndex.values()].filter((n) => n.outcome !== 'in_tune')
        .sort((a, b) => Math.abs(b.cents ?? 99) - Math.abs(a.cents ?? 99))[0];
    if (worst) select(worst.index);
}

function showDetail(n, data) {
    const card = $('#note-detail');
    card.hidden = false;
    card.dataset.outcome = n.outcome;
    card.querySelector('[data-detail-title]').textContent = `Bar ${n.measure}, note ${n.index + 1}: ${n.expected}`;
    card.querySelector('[data-detail-outcome]').textContent = OUTCOME_LABELS[n.outcome] ?? n.outcome;
    const c = n.cents;
    card.querySelector('[data-detail-text]').textContent = n.hz
        ? `Heard ${n.detected} at ${Number(n.hz).toFixed(1)} Hz`
            + (c === null ? '' : `: ${c > 0 ? '+' : c < 0 ? MINUS : ''}${Math.abs(Math.round(c))} cents`)
        : 'No clear pitch was heard for this note.';

    // A small ruler from -50 to +50 cents with the in-tune band implied by the outcome colours.
    const ruler = card.querySelector('.pi-ruler');
    const band = data.tolerance.mode === 'cents' ? Math.min(50, data.tolerance.value) : 0; // Hz rules have no fixed band
    ruler.style.setProperty('--lo', `${50 - band}%`);
    ruler.style.setProperty('--hi', `${50 - band}%`);
    const marker = card.querySelector('[data-detail-marker]');
    marker.hidden = c === null || n.outcome === 'missed';
    if (!marker.hidden) marker.style.left = `${Math.max(0, Math.min(100, c + 50))}%`;
}
