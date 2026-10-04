import { Chart, chartTheme, css, renderPitchChart } from './pitch-chart.js';

const dataEl = document.getElementById('dashboard-data');

if (dataEl) {
    const data = JSON.parse(dataEl.textContent);
    const { grid, tooltip } = chartTheme();

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

    const pitchEl = document.getElementById('chart-pitches');
    if (pitchEl && data.pitches.length) renderPitchChart(pitchEl, data.pitches);
}
