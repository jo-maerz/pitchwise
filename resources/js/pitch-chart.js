import {
    BarController, BarElement, CategoryScale, Chart, Filler, LinearScale,
    LineController, LineElement, PointElement, Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, Filler, LinearScale, LineController, LineElement, PointElement, Tooltip);

export { Chart };

export function css(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

/** Colours and fonts shared by every chart; call once per page. */
export function chartTheme() {
    const ink = css('--pi-text-secondary') || '#52514e';
    const grid = css('--pi-grid') || '#e4e3df';
    Chart.defaults.color = ink;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.borderColor = grid;

    return { ink, grid, tooltip: { displayColors: false, padding: 10, cornerRadius: 6 } };
}

const signed = (c) => `${c > 0 ? '+' : ''}${c}`;

/**
 * Average offset per pitch: diverging around 0 (sharp = red, flat = blue), one y-axis in cents.
 *
 * @param {HTMLCanvasElement} canvas
 * @param {Array<{note:string, midi:number, avgCents:number|null, attempts:number, inTunePct:number}>} pitches
 * @param {{compare?:Array|null, compareLabel?:string}} opts
 *        `compare`: a second set of the same rows (e.g. all runs of the piece), drawn as markers over the bars
 */
export function renderPitchChart(canvas, pitches, { compare = null, compareLabel = 'All runs' } = {}) {
    const { ink, grid, tooltip } = chartTheme();
    const sharp = css('--pi-sharp') || '#e34948';
    const flat = css('--pi-flat') || '#2a78d6';
    const neutral = css('--pi-neutral') || '#a8a7a2';
    const colour = (c) => (c === null || Math.abs(c) < 5 ? neutral : c > 0 ? sharp : flat);

    const mine = new Map(pitches.map((p) => [p.midi, p]));
    const other = new Map((compare ?? []).map((p) => [p.midi, p]));
    const midis = [...new Set([...mine.keys(), ...other.keys()])].sort((a, b) => a - b);
    const rows = midis.map((m) => mine.get(m) ?? null);
    const name = (m) => (mine.get(m) ?? other.get(m)).note;

    const datasets = [{
        type: 'bar',
        label: 'This run',
        data: rows.map((p) => (p ? p.avgCents ?? 0 : null)),
        backgroundColor: rows.map((p) => colour(p?.avgCents ?? null)),
        borderRadius: 4,
        borderSkipped: false,
        maxBarThickness: 28,
        order: 2,
    }];
    if (compare) {
        datasets.push({
            type: 'line',
            label: compareLabel,
            data: midis.map((m) => other.get(m)?.avgCents ?? null),
            showLine: false,
            pointStyle: 'rectRot',
            pointRadius: 6,
            pointHoverRadius: 8,
            pointBackgroundColor: css('--pi-surface') || '#fff',
            pointBorderColor: ink,
            pointBorderWidth: 2,
            order: 1,
        });
    }

    return new Chart(canvas, {
        data: { labels: midis.map(name), datasets },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    suggestedMin: -40,
                    suggestedMax: 40,
                    ticks: { callback: (v) => signed(v) },
                    title: { display: true, text: 'cents (↑ sharp, ↓ flat)' },
                    grid: { color: (ctx) => (ctx.tick.value === 0 ? ink : grid) },
                },
                x: { grid: { display: false } },
            },
            plugins: {
                legend: { display: Boolean(compare), labels: { usePointStyle: true } },
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        label: (item) => {
                            const p = (item.datasetIndex === 0 ? mine : other).get(midis[item.dataIndex]);
                            if (!p) return '';
                            const c = p.avgCents === null ? 'no pitched attempts' : `${signed(p.avgCents)} cents on average`;
                            const prefix = item.datasetIndex === 0 ? '' : `${compareLabel}: `;
                            return [`${prefix}${c}`, `${p.inTunePct}% in tune over ${p.attempts} ${p.attempts === 1 ? 'note' : 'notes'}`];
                        },
                    },
                },
            },
        },
    });
}
