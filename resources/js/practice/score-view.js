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
        for (const [index, verdict] of this.colours) this.paint(index, verdict);
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
        while (!cursor.Iterator.EndReached) {
            const entries = cursor.VoicesUnderCursor(instrument)
                .filter((ve) => ve.ParentVoice?.VoiceId === melodyVoice && !ve.IsGrace);
            for (const ve of entries) {
                const note = ve.Notes.find((n) => !n.isRest() && n.Pitch && !n.IsCueNote);
                if (!note) continue;
                const tie = note.NoteTie;
                if (tie && tie.StartNote && tie.StartNote !== note) continue; // continuation of a tied note
                const gnote = this.osmd.EngravingRules.GNote(note);
                map.push({
                    step,
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

    /** Page numbers that contain melody notes, in order. */
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

    resetCursor() {
        this.osmd.cursor.reset();
        this.step = 0;
    }

    colour(index, verdict) {
        this.colours.set(index, verdict);
        this.paint(index, verdict);
    }

    clearColours() {
        for (const index of this.colours.keys()) this.paint(index, null);
        this.colours.clear();
    }

    paint(index, verdict) {
        const group = this.map[index]?.gnote?.getSVGGElement?.();
        if (!group) return;
        const heads = group.querySelectorAll('.vf-notehead path, .vf-notehead');
        const targets = heads.length ? heads : [group];
        targets.forEach((el) => {
            if (verdict) {
                el.setAttribute('data-verdict', verdict);
            } else {
                el.removeAttribute('data-verdict');
            }
        });
        group.classList.toggle('pi-note-marked', Boolean(verdict));
        if (verdict) group.setAttribute('data-verdict', verdict);
        else group.removeAttribute('data-verdict');
    }
}
