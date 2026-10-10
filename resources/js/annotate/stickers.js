import { FabricText, Path, Polyline } from 'fabric';

/**
 * Musical marks to drop on a page, by the name the toolbar buttons use (data-sticker). Sizes are in page units (a page is 1000 wide).
 * Each sticker is a single shape, never a group, so a saved layer holds only plain shapes and text.
 */
const dynamic = (text) => (color) => new FabricText(text, {
    fontFamily: '"Times New Roman", Times, serif',
    fontStyle: 'italic',
    fontWeight: 'bold',
    fontSize: 34,
    fill: color,
});

const stroke = (color, width = 2.5) => ({ stroke: color, strokeWidth: width, fill: '', strokeLineCap: 'round', strokeLineJoin: 'round', strokeUniform: true });

/** @type {Record<string, (color: string) => import('fabric').FabricObject>} */
export const STICKERS = {
    pp: dynamic('pp'),
    p: dynamic('p'),
    mp: dynamic('mp'),
    mf: dynamic('mf'),
    f: dynamic('f'),
    ff: dynamic('ff'),
    sfz: dynamic('sfz'),
    cresc: (color) => new Polyline([{ x: 120, y: 0 }, { x: 0, y: 12 }, { x: 120, y: 24 }], stroke(color)),
    dim: (color) => new Polyline([{ x: 0, y: 0 }, { x: 120, y: 12 }, { x: 0, y: 24 }], stroke(color)),
    accent: (color) => new Polyline([{ x: 0, y: 0 }, { x: 18, y: 6 }, { x: 0, y: 12 }], stroke(color)),
    fermata: (color) => new Path('M 0 22 A 18 18 0 0 1 36 22 M 17 18 L 19 18', stroke(color, 3)),
    breath: dynamic('’'),
    upBow: (color) => new Polyline([{ x: 0, y: 0 }, { x: 9, y: 24 }, { x: 18, y: 0 }], stroke(color)),
    downBow: (color) => new Path('M 0 20 L 0 0 L 22 0 L 22 20 M 0 1.5 L 22 1.5', stroke(color, 3)),
};
