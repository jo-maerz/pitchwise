import {
    BarController, BarElement, CategoryScale, Chart, Filler, LinearScale,
    LineController, LineElement, PointElement, Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, Filler, LinearScale, LineController, LineElement, PointElement, Tooltip);

const dataEl = document.getElementById('dashboard-data');

function css(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

if (dataEl) {
    const data = JSON.parse(dataEl.textContent);
    const ink = css('--pi-text-secondary') || '#52514e';
    const grid = css('--pi-grid') || '#e4e3df';
    Chart.defaults.color = ink;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.borderColor = grid;

    const tooltip = { displayColors: false, padding: 10, cornerRadius: 6 };

    // Score per run, one series: the title names it, so no legend.
    const historyEl = document.getElementById('chart-history');
    if (historyEl && data.history.length) {
        const fmt = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short' });
        new Chart(historyEl, {
            type: 'line',
            data: {
                labels: data.history.map((h) => fmt.format(new Date(h.date))),
                datasets: [{
                    data: data.history.map((h) => h.score),
                    borderColor: css('--pi-series-1') || '#2a78d6',
                    backgroundColor: css('--pi-series-1') || '#2a78d6',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBorderColor: css('--pi-surface') || '#fff',
                    pointBorderWidth: 2,
                    tension: 0.25,
                }],
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { min: 0, max: 100, ticks: { callback: (v) => `${v}%`, stepSize: 25 }, grid: { color: grid } },
                    x: { grid: { display: false } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tooltip,
                        callbacks: {
                            title: (items) => data.history[items[0].dataIndex].piece ?? '',
                            label: (item) => `${item.parsed.y}% in tune · ${item.label}`,
                        },
                    },
                },
                onClick: (_, els) => {
                    if (els.length) window.location.href = `/sessions/${data.history[els[0].index].id}`;
                },
            },
        });
    }

    // Average offset per pitch: diverging around 0 (sharp = red, flat = blue), one y-axis in cents.
    const pitchEl = document.getElementById('chart-pitches');
    if (pitchEl && data.pitches.length) {
        const sharp = css('--pi-sharp') || '#e34948';
        const flat = css('--pi-flat') || '#2a78d6';
        const neutral = css('--pi-neutral') || '#a8a7a2';
        const colour = (c) => (c === null || Math.abs(c) < 5 ? neutral : c > 0 ? sharp : flat);
        new Chart(pitchEl, {
            type: 'bar',
            data: {
                labels: data.pitches.map((p) => p.note),
                datasets: [{
                    data: data.pitches.map((p) => p.avgCents ?? 0),
                    backgroundColor: data.pitches.map((p) => colour(p.avgCents)),
                    borderRadius: 4,
                    borderSkipped: false,
                    maxBarThickness: 28,
                }],
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        suggestedMin: -40,
                        suggestedMax: 40,
                        ticks: { callback: (v) => `${v > 0 ? '+' : ''}${v}` },
                        title: { display: true, text: 'cents (↑ sharp, ↓ flat)' },
                        grid: { color: (ctx) => (ctx.tick.value === 0 ? ink : grid) },
                    },
                    x: { grid: { display: false } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tooltip,
                        callbacks: {
                            label: (item) => {
                                const p = data.pitches[item.dataIndex];
                                const c = p.avgCents === null ? 'no pitched attempts' : `${p.avgCents > 0 ? '+' : ''}${p.avgCents} cents on average`;
                                return [c, `${p.inTunePct}% in tune over ${p.attempts} notes`];
                            },
                        },
                    },
                },
            },
        });
    }
}
