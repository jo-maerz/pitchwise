import * as pdfjs from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { ScoreView } from '../practice/score-view.js';

pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;

/**
 * Draws the score as fixed pages to annotate on. Each page is a positioned element of a known size;
 * marks are stored relative to it, so they stay put whatever the screen width.
 *
 * @typedef {{ el: HTMLElement, width: number, height: number, picture?: () => Promise<HTMLCanvasElement> }} Page
 */

const pageElement = (width, height, label) => {
    const el = document.createElement('div');
    el.className = 'pi-annotate-page relative mx-auto bg-white shadow-sm';
    el.style.width = `${width}px`;
    el.style.height = `${height}px`;
    el.setAttribute('role', 'group');
    el.setAttribute('aria-label', label);
    return el;
};

/** @returns {Promise<{ pages: Page[], bytes: Uint8Array }>} */
export async function renderPdfPages(url, container) {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`Could not load the PDF (${response.status}).`);
    const bytes = new Uint8Array(await response.arrayBuffer());
    // pdf.js takes over the buffer it is given; the export needs the original bytes.
    const pdf = await pdfjs.getDocument({ data: bytes.slice() }).promise;
    const width = Math.floor(container.clientWidth);
    const ratio = window.devicePixelRatio || 1;
    const pages = [];
    for (let n = 1; n <= pdf.numPages; n++) {
        const page = await pdf.getPage(n);
        const scale = width / page.getViewport({ scale: 1 }).width;
        const viewport = page.getViewport({ scale });
        const el = pageElement(width, Math.floor(viewport.height), `Page ${n} of ${pdf.numPages}`);
        const canvas = document.createElement('canvas');
        canvas.width = Math.floor(viewport.width * ratio);
        canvas.height = Math.floor(viewport.height * ratio);
        canvas.className = 'absolute inset-0 h-full w-full';
        el.append(canvas);
        container.append(el);
        await page.render({ canvas, viewport: page.getViewport({ scale: scale * ratio }) }).promise;
        pages.push({ el, width, height: Math.floor(viewport.height) });
    }
    return { pages, bytes };
}

/** MusicXML drawn by OSMD on A4 pages, one SVG each. @returns {Promise<{ pages: Page[] }>} */
export async function renderScorePages(url, container) {
    const view = new ScoreView(container);
    await view.load(url);
    view.osmd.setOptions({ pageFormat: 'A4_P', followCursor: false, pageBackgroundColor: '#FFFFFF' });
    view.osmd.zoom = view.fixedPageZoom();
    view.osmd.render();

    const pages = view.pages().map(({ el, svg, width, height }, i, all) => {
        el.classList.add('pi-annotate-page', 'relative', 'mx-auto', 'shadow-sm');
        el.style.width = `${width}px`;
        el.style.height = `${height}px`;
        el.setAttribute('role', 'group');
        el.setAttribute('aria-label', `Page ${i + 1} of ${all.length}`);
        return { el, width, height, picture: (scale) => svgToCanvas(svg, width, height, scale) };
    });
    return { pages };
}

async function svgToCanvas(svg, width, height, scale) {
    const copy = svg.cloneNode(true);
    copy.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    copy.setAttribute('width', width);
    copy.setAttribute('height', height);
    const blob = new Blob([new XMLSerializer().serializeToString(copy)], { type: 'image/svg+xml' });
    const url = URL.createObjectURL(blob);
    try {
        const image = new Image();
        image.src = url;
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
        return canvas;
    } finally {
        URL.revokeObjectURL(url);
    }
}
