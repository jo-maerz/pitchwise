import { OpenSheetMusicDisplay } from 'opensheetmusicdisplay';

/**
 * Wraps OpenSheetMusicDisplay: draws the score, moves its cursor, colours noteheads.
 *
 * Which notes count must match the server's parser (app/Services/MusicXml/MusicXmlParser.php):
 * first instrument, its lowest voice id (voice 1), pitched notes, first note of a chord,
 * no grace or cue notes, tied continuations skipped. buildNoteMap() walks the cursor once
 * and records, for every counted note, the cursor step it sits on and its page.
 */
export class ScoreView {
    constructor(container) {
        this.container = container;
        this.osmd = new OpenSheetMusicDisplay(container, {
            autoResize: false, // a re-render would drop the note colours mid-run
            backend: 'svg',
            drawTitle: true,
            followCursor: true,
            pageFormat: 'Endless',
        });
        this.map = [];
        this.step = 0;
        this.colours = new Map();
    }

    async load(url) {
        const response = await fetch(url, { credentials: 'same-origin' });
        if (!response.ok) throw new Error(`Could not load the score (${response.status}).`);
        const bytes = new Uint8Array(await response.arrayBuffer());
        if (bytes[0] === 0x50 && bytes[1] === 0x4b) {
            // Compressed .mxl (a zip): OSMD wants it as a binary string, and text() would corrupt the bytes.
            let binary = '';
            for (let i = 0; i < bytes.length; i += 0x8000) {
                binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
            }
            await this.osmd.load(binary);
        } else {
            await this.osmd.load(new TextDecoder().decode(bytes));
        }
    }

    /** @param {'pages'|'continuous'} layout */
    render(layout = 'pages') {
        this.osmd.setOptions({ pageFormat: layout === 'pages' ? 'A4_P' : 'Endless' });
        this.osmd.render();
        this.buildNoteMap();
        for (const [index, outcome] of this.colours) this.paint(index, outcome);
    }

    buildNoteMap() {
        const cursor = this.osmd.cursor;
        const instrument = this.osmd.Sheet.Instruments[0];
        const voiceIds = (instrument.Voices ?? []).map((v) => v.VoiceId);
        const melodyVoice = voiceIds.length ? Math.min(...voiceIds) : 1;

        cursor.show();
        cursor.reset();
        const map = [];
        let step = 0;
        let lastTime = -1;
        while (!cursor.Iterator.EndReached) {
            const entries = cursor.VoicesUnderCursor(instrument)
                .filter((ve) => ve.ParentVoice?.VoiceId === melodyVoice && !ve.IsGrace);
            for (const ve of entries) {
                const note = ve.Notes.find((n) => !n.isRest() && n.Pitch && !n.IsCueNote);
                if (!note) continue;
                const tie = note.NoteTie;
                if (tie && tie.StartNote && tie.StartNote !== note) continue; // continuation of a tied note
                const gnote = this.osmd.EngravingRules.GNote(note);
                // Written time went backwards: a repeat (or D.C./D.S.) sent the cursor back here.
                const time = cursor.Iterator.CurrentSourceTimestamp?.RealValue ?? 0;
                const jumpBack = time < lastTime;
                lastTime = time;
                map.push({
                    step,
                    jumpBack,
                    midi: note.halfTone + 12,
                    gnote,
                    page: gnote?.ParentMusicPage?.PageNumber ?? 1,
                });
            }
            cursor.next();
            step++;
        }
        cursor.reset();
        this.step = 0;
        this.map = map;
        return map;
    }

    get pageCount() {
        return this.osmd.GraphicSheet?.MusicPages?.length ?? 1;
    }

    layoutPages() {
        const pages = [...new Set(this.map.map((m) => m.page))].sort((a, b) => a - b);
        return pages.length ? pages : [1];
    }

    pageOf(index) {
        return this.map[index]?.page ?? 1;
    }

    /** Move the cursor onto note `index` (counted notes, as in piece_notes.note_index). */
    moveTo(index) {
        const target = this.map[index]?.step;
        if (target == null) return;
        if (this.map[index].jumpBack) this.forgetColoursFrom(index);
        const cursor = this.osmd.cursor;
        if (target < this.step) {
            cursor.reset();
            this.step = 0;
        }
        while (this.step < target && !cursor.Iterator.EndReached) {
            cursor.next();
            this.step++;
        }
    }

    /**
     * The cursor jumped back to `index`: every written note from here on is about to be played
     * again, so it must not keep the colour of the previous pass. Notes the run will not reach
     * again (e.g. a first ending) keep theirs.
     */
    forgetColoursFrom(index) {
        const again = new Set(this.map.slice(index).map((m) => m.gnote));
        for (let i = 0; i < index; i++) {
            if (this.colours.has(i) && again.has(this.map[i]?.gnote)) this.paint(i, null);
        }
    }

    /** The SVG group of a counted note, or null (not drawn / not found). */
    noteElement(index) {
        return this.map[index]?.gnote?.getSVGGElement?.() ?? null;
    }

    /** Pixel box (in the score SVG) of the whole bar that holds counted note `index`, all staves. */
    barRect(index) {
        const measure = this.map[index]?.gnote?.parentVoiceEntry?.parentStaffEntry?.parentMeasure;
        const row = this.osmd.GraphicSheet?.MeasureList?.[measure?.parentSourceMeasure?.measureListIndex];
        if (!row) return null;
        const unit = 10 * this.osmd.zoom; // OSMD engraving units are 10 px at zoom 1
        let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
        for (const gm of row) {
            const box = gm?.PositionAndShape;
            if (!box) continue;
            x0 = Math.min(x0, box.AbsolutePosition.x);
            y0 = Math.min(y0, box.AbsolutePosition.y);
            x1 = Math.max(x1, box.AbsolutePosition.x + box.Size.width);
            y1 = Math.max(y1, box.AbsolutePosition.y + box.Size.height);
        }
        if (!Number.isFinite(x0)) return null;
        return { x: x0 * unit, y: y0 * unit, width: (x1 - x0) * unit, height: (y1 - y0) * unit };
    }

    hideCursor() {
        this.osmd.cursor.hide();
    }

    resetCursor() {
        this.osmd.cursor.reset();
        this.step = 0;
    }

    colour(index, outcome) {
        this.colours.set(index, outcome);
        this.paint(index, outcome);
    }

    clearColours() {
        for (const index of this.colours.keys()) this.paint(index, null);
        this.colours.clear();
    }

    paint(index, outcome) {
        const group = this.map[index]?.gnote?.getSVGGElement?.();
        if (!group) return;
        const heads = group.querySelectorAll('.vf-notehead path, .vf-notehead');
        const targets = heads.length ? heads : [group];
        targets.forEach((el) => {
            if (outcome) {
                el.setAttribute('data-outcome', outcome);
            } else {
                el.removeAttribute('data-outcome');
            }
        });
        group.classList.toggle('pi-note-marked', Boolean(outcome));
        if (outcome) group.setAttribute('data-outcome', outcome);
        else group.removeAttribute('data-outcome');
    }
}
