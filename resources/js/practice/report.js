import { IN_TUNE, VERDICTS } from './pitch-math.js';

/**
 * End-of-run (or end-of-page) summary from per-note results.
 * @param {Array<{verdict:string, measure:number, page:number, cents:number|null}>} results
 */
export function summarize(results) {
    const counts = Object.fromEntries(VERDICTS.map((v) => [v, 0]));
    const pages = new Map();
    const measures = new Map();
    let centsSum = 0;
    let centsN = 0;

    for (const r of results) {
        counts[r.verdict] = (counts[r.verdict] ?? 0) + 1;
        bump(pages, r.page ?? 1, r.verdict);
        bump(measures, r.measure, r.verdict);
        if (r.cents != null && ['in_tune', 'sharp', 'flat'].includes(r.verdict)) {
            centsSum += r.cents;
            centsN++;
        }
    }

    const total = results.length;
    const toRows = (map, key) =>
        [...map.entries()]
            .sort((a, b) => a[0] - b[0])
            .map(([k, v]) => ({ [key]: k, notes: v.notes, inTune: v.inTune, score: pct(v.inTune, v.notes) }));

    const measureRows = toRows(measures, 'measure');
    const weakest = measureRows
        .filter((m) => m.score < 100)
        .sort((a, b) => a.score - b.score || b.notes - a.notes)
        .slice(0, 3);

    return {
        total,
        counts,
        score: pct(counts[IN_TUNE], total),
        avgCents: centsN ? Math.round((centsSum / centsN) * 10) / 10 : null,
        pages: toRows(pages, 'page'),
        measures: measureRows,
        weakest,
    };
}

function bump(map, key, verdict) {
    const row = map.get(key) ?? { notes: 0, inTune: 0 };
    row.notes++;
    if (verdict === IN_TUNE) row.inTune++;
    map.set(key, row);
}

export function pct(part, whole) {
    return whole ? Math.round((1000 * part) / whole) / 10 : 0;
}
