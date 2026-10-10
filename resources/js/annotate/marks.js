/** Marks are stored in page units: a page is PAGE_UNITS wide, whatever its size on screen. */
export const PAGE_UNITS = 1000;

/**
 * Draws saved annotation layers, read only, over pages of the score: the first layer lowest.
 * Fabric is loaded only when there is something to draw.
 *
 * @param {{ el: HTMLElement, width: number, height: number }[]} pages
 * @param {object[][][]} layers per layer, per page, the saved Fabric objects
 */
export async function drawMarks(pages, layers) {
    if (!layers.some((layer) => layer.some((objects) => objects?.length))) return;
    const { StaticCanvas } = await import('fabric');
    for (const [index, page] of pages.entries()) {
        const objects = layers.flatMap((layer) => layer[index] ?? []);
        if (objects.length === 0) continue;
        const el = document.createElement('canvas');
        const canvas = new StaticCanvas(el, { width: page.width, height: page.height });
        canvas.setZoom(page.width / PAGE_UNITS);
        await canvas.loadFromJSON({ objects });
        canvas.requestRenderAll();
        el.classList.add('pi-marks');
        page.el.style.position = 'relative';
        page.el.append(el);
    }
}
