import { FabricText, Path, Polyline } from 'fabric';

/**
 * Musical marks to drop on a page, by the name the toolbar buttons use (data-sticker). Sizes are in page units (a page is 1000 wide).
 * Each sticker is a single shape, never a group, so a saved layer holds only plain shapes and text.
 */
const text = (content, style) => (color) => new FabricText(content, {
    fontFamily: '"Times New Roman", Times, serif',
    fontSize: 26,
    fill: color,
    ...style,
});

const dynamic = (content) => text(content, { fontStyle: 'italic', fontWeight: 'bold' });

const fingering = (n) => text(String(n), { fontWeight: 'bold', fontSize: 20 });

const stroke = (color, width = 2) => ({ stroke: color, strokeWidth: width, fill: '', strokeLineCap: 'round', strokeLineJoin: 'round', strokeUniform: true });

/** @type {Record<string, (color: string) => import('fabric').FabricObject>} */
export const STICKERS = {
    pp: dynamic('pp'),
    p: dynamic('p'),
    mp: dynamic('mp'),
    mf: dynamic('mf'),
    f: dynamic('f'),
    ff: dynamic('ff'),
    sfz: dynamic('sfz'),
    cresc: (color) => new Polyline([{ x: 90, y: 0 }, { x: 0, y: 9 }, { x: 90, y: 18 }], stroke(color)),
    dim: (color) => new Polyline([{ x: 0, y: 0 }, { x: 90, y: 9 }, { x: 0, y: 18 }], stroke(color)),
    rit: text('rit.', { fontStyle: 'italic' }),
    accent: (color) => new Polyline([{ x: 0, y: 0 }, { x: 14, y: 4.5 }, { x: 0, y: 9 }], stroke(color)),
    fermata: (color) => new Path('M 0 16 A 13 13 0 0 1 26 16 M 12 13 L 14 13', stroke(color, 2.5)),
    breath: dynamic('’'),
    downBow: (color) => new Path('M 0 15 L 0 0 L 17 0 L 17 15 M 0 1.5 L 17 1.5', stroke(color, 2.5)),
    upBow: (color) => new Polyline([{ x: 0, y: 0 }, { x: 7, y: 18 }, { x: 14, y: 0 }], stroke(color)),
    ...Object.fromEntries([0, 1, 2, 3, 4, 5].map((n) => [`finger${n}`, fingering(n)])),
};
