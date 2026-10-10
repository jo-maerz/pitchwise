/**
 * Where to draw a picture of a page, as it is seen on screen, onto the unrotated PDF page, for pdf-lib's
 * drawImage (which turns the image anticlockwise about its bottom-left corner).
 *
 * @param {number} rotation the page's /Rotate, clockwise: 0, 90, 180 or 270
 * @param {{x: number, y: number, width: number, height: number}} box the page's crop box
 */
export function overlayPlacement(rotation, box) {
    const { x, y, width: w, height: h } = box;
    switch (((rotation % 360) + 360) % 360) {
        case 90: return { x: x + w, y, width: h, height: w, rotate: 90 };
        case 180: return { x: x + w, y: y + h, width: w, height: h, rotate: 180 };
        case 270: return { x, y: y + h, width: h, height: w, rotate: 270 };
        default: return { x, y, width: w, height: h, rotate: 0 };
    }
}
