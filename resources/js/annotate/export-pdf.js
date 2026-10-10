import { PDFDocument, degrees } from 'pdf-lib';
import { overlayPlacement } from './placement.js';

const EXPORT_SCALE = 3; // ≈ 250 dpi for a page about 800 px wide on screen
const A4_WIDTH = 595.28;

const pngBytes = async (canvas) => new Uint8Array(await (await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'))).arrayBuffer());

/**
 * The original PDF, its music untouched, with each page's visible marks laid on top as a transparent picture.
 *
 * @param {Uint8Array} bytes the original PDF
 * @param {(index: number, scale: number) => HTMLCanvasElement | null} overlay the marks on one page
 */
export async function annotatedPdf(bytes, pageCount, overlay) {
    const doc = await PDFDocument.load(bytes, { ignoreEncryption: true });
    const pages = doc.getPages();
    for (let i = 0; i < Math.min(pages.length, pageCount); i++) {
        const marks = overlay(i, EXPORT_SCALE);
        if (!marks) continue;
        const page = pages[i];
        const { rotate, ...at } = overlayPlacement(page.getRotation().angle, page.getCropBox());
        page.drawImage(await doc.embedPng(await pngBytes(marks)), { ...at, rotate: degrees(rotate) });
    }
    return doc.save();
}

/**
 * A new PDF from the drawn MusicXML pages: each page is the score with its marks, as one picture.
 *
 * @param {{ width: number, height: number, picture: (scale: number) => Promise<HTMLCanvasElement> }[]} pages
 */
export async function scorePdf(pages, overlay, title) {
    const doc = await PDFDocument.create();
    doc.setTitle(title);
    for (const [i, page] of pages.entries()) {
        const canvas = await page.picture(EXPORT_SCALE);
        const marks = overlay(i, EXPORT_SCALE);
        if (marks) canvas.getContext('2d').drawImage(marks, 0, 0, canvas.width, canvas.height);
        const height = (A4_WIDTH * page.height) / page.width;
        doc.addPage([A4_WIDTH, height])
            .drawImage(await doc.embedPng(await pngBytes(canvas)), { x: 0, y: 0, width: A4_WIDTH, height });
    }
    return doc.save();
}

export function download(bytes, fileName) {
    const url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
    const link = Object.assign(document.createElement('a'), { href: url, download: fileName });
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 10_000);
}
