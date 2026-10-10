import { Canvas, IText, PencilBrush } from 'fabric';
import { annotatedPdf, download, scorePdf } from './annotate/export-pdf.js';
import { renderPdfPages, renderScorePages } from './annotate/pages.js';
import { STICKERS } from './annotate/stickers.js';

/**
 * The annotation page: two layers of marks over every page of the score, the organization's shared
 * one and the user's own. Marks are Fabric.js objects in page units (a page is PAGE_UNITS wide),
 * saved per layer as one list of objects per page.
 */
const PAGE_UNITS = 1000;
const LAYERS = ['shared', 'mine'];

const configEl = document.getElementById('annotate-config');
if (configEl) start(JSON.parse(configEl.textContent));

async function start(config) {
    const $ = (id) => document.getElementById(id);
    const statusEl = $('annotate-status');
    const warningEl = $('annotate-warning');
    const pagesEl = $('annotate-pages');
    const toolbar = $('annotate-toolbar');
    const colourInput = $('annotate-colour');
    const widthInput = $('annotate-width');
    const saveButton = $('annotate-save');
    const undoButton = $('annotate-undo');
    const exportButton = $('annotate-export');

    const layers = Object.fromEntries(LAYERS.map((name) => [name, { ...config.layers[name], canvases: [], dirty: false, visible: true }]));
    let active = 'mine';
    let tool = 'select';
    let sticker = null;
    let restoring = false;
    const undoStack = [];
    const lastState = new Map();

    const showWarning = (text) => {
        warningEl.textContent = text;
        warningEl.hidden = false;
    };
    const serialize = (canvas) => canvas.toObject().objects;
    const anyDirty = () => LAYERS.some((name) => layers[name].dirty);
    const updateStatus = (text) => {
        statusEl.textContent = text ?? (anyDirty() ? 'Unsaved changes' : 'All changes saved');
        saveButton.disabled = !anyDirty();
        undoButton.disabled = undoStack.length === 0;
    };

    // --- the score --------------------------------------------------------
    statusEl.textContent = 'Loading the score…';
    let rendered;
    try {
        rendered = config.source.type === 'pdf'
            ? await renderPdfPages(config.source.url, pagesEl)
            : await renderScorePages(config.source.url, pagesEl);
    } catch (e) {
        statusEl.textContent = '';
        showWarning(`The score could not be drawn: ${e.message}`);
        return;
    }
    const { pages } = rendered;

    // --- one Fabric canvas per layer and page -----------------------------
    const changed = (layer, canvas) => {
        if (restoring) return;
        undoStack.push({ layer, canvas, state: lastState.get(canvas) });
        lastState.set(canvas, serialize(canvas));
        layers[layer].dirty = true;
        updateStatus();
    };

    for (const [index, page] of pages.entries()) {
        const overlay = document.createElement('div');
        overlay.className = 'absolute inset-0';
        page.el.append(overlay);
        for (const name of LAYERS) {
            const el = document.createElement('canvas');
            overlay.append(el);
            const canvas = new Canvas(el, { width: page.width, height: page.height, preserveObjectStacking: true, selection: false });
            canvas.setZoom(page.width / PAGE_UNITS);
            Object.assign(canvas.wrapperEl.style, { position: 'absolute', inset: '0' });
            await canvas.loadFromJSON({ objects: layers[name].pages[index] ?? [] });
            lastState.set(canvas, serialize(canvas));
            layers[name].canvases.push(canvas);

            if (!layers[name].editable) continue;
            canvas.on('object:added', () => changed(name, canvas));
            canvas.on('object:modified', () => changed(name, canvas));
            canvas.on('object:removed', () => changed(name, canvas));
            canvas.on('text:editing:exited', ({ target }) => {
                if (target.text.trim() === '') canvas.remove(target);
                else changed(name, canvas);
            });
            canvas.on('mouse:down', (opt) => pointerDown(canvas, opt));
        }
    }

    // --- tools ------------------------------------------------------------
    function pointerDown(canvas, { e, target }) {
        const at = canvas.getScenePoint(e);
        if (tool === 'eraser' && target) {
            canvas.remove(target);
        } else if (tool === 'text') {
            if (target instanceof IText) {
                target.enterEditing();
                return;
            }
            const text = new IText('', { left: at.x, top: at.y, originX: 'left', originY: 'top', fontFamily: 'ui-sans-serif, system-ui, sans-serif', fontSize: 24, fill: colourInput.value });
            canvas.add(text);
            canvas.setActiveObject(text);
            text.enterEditing();
        } else if (tool === 'sticker' && sticker) {
            const mark = STICKERS[sticker](colourInput.value);
            mark.set({ left: at.x, top: at.y, originX: 'center', originY: 'center' });
            canvas.add(mark);
        }
    }

    const finishEditing = () => {
        for (const name of LAYERS) {
            for (const canvas of layers[name].canvases) {
                const object = canvas.getActiveObject();
                if (object?.isEditing) object.exitEditing();
                canvas.discardActiveObject();
                canvas.requestRenderAll();
            }
        }
    };

    const applyTool = () => {
        for (const name of LAYERS) {
            const layer = layers[name];
            const isActive = name === active;
            for (const canvas of layer.canvases) {
                Object.assign(canvas.wrapperEl.style, {
                    display: layer.visible ? '' : 'none',
                    zIndex: isActive ? '2' : '1',
                    pointerEvents: isActive ? 'auto' : 'none',
                });
                canvas.isDrawingMode = isActive && tool === 'pen';
                if (canvas.isDrawingMode) {
                    canvas.freeDrawingBrush = Object.assign(new PencilBrush(canvas), { color: colourInput.value, width: Number(widthInput.value) });
                }
                canvas.selection = isActive && tool === 'select';
                canvas.defaultCursor = { text: 'text', sticker: 'copy', eraser: 'cell' }[tool] ?? 'default';
                for (const object of canvas.getObjects()) {
                    const pickable = isActive && (tool === 'select' || tool === 'eraser' || (tool === 'text' && object instanceof IText));
                    object.set({ selectable: pickable && tool !== 'eraser', evented: pickable, hoverCursor: tool === 'eraser' ? 'pointer' : null });
                }
            }
        }
        for (const button of toolbar.querySelectorAll('[data-tool]')) {
            const on = button.dataset.tool === tool && (tool !== 'sticker' || button.dataset.sticker === sticker);
            button.setAttribute('aria-pressed', String(on));
        }
    };

    toolbar.addEventListener('click', (event) => {
        const button = event.target.closest('[data-tool]');
        if (!button) return;
        finishEditing();
        tool = button.dataset.tool;
        sticker = button.dataset.sticker ?? null;
        applyTool();
    });
    colourInput.addEventListener('change', applyTool);
    widthInput.addEventListener('change', applyTool);

    for (const input of document.querySelectorAll('[name=annotate-layer]')) {
        input.checked = input.value === active;
        input.addEventListener('change', () => {
            finishEditing();
            active = input.value;
            layers[active].visible = true;
            $(`annotate-show-${active}`).checked = true;
            applyTool();
        });
    }
    for (const name of LAYERS) {
        $(`annotate-show-${name}`).addEventListener('change', (event) => {
            layers[name].visible = event.target.checked;
            applyTool();
        });
    }

    // --- undo, delete -----------------------------------------------------
    const undo = async () => {
        const step = undoStack.pop();
        if (!step) return;
        finishEditing();
        restoring = true;
        await step.canvas.loadFromJSON({ objects: step.state });
        restoring = false;
        lastState.set(step.canvas, step.state);
        layers[step.layer].dirty = true;
        applyTool();
        step.canvas.requestRenderAll();
        updateStatus();
    };
    undoButton.addEventListener('click', undo);

    document.addEventListener('keydown', (event) => {
        if (event.target.closest('input, textarea, select')) return;
        if ((event.metaKey || event.ctrlKey) && event.key === 'z') {
            event.preventDefault();
            undo();
        } else if (event.key === 'Delete' || event.key === 'Backspace') {
            for (const canvas of layers[active].canvases) {
                const selected = canvas.getActiveObjects();
                if (selected.length === 0 || selected.some((o) => o.isEditing)) continue;
                event.preventDefault();
                canvas.discardActiveObject();
                canvas.remove(...selected);
            }
        }
    });

    // --- saving -----------------------------------------------------------
    const save = async () => {
        finishEditing();
        updateStatus('Saving…');
        for (const name of LAYERS) {
            const layer = layers[name];
            if (!layer.dirty || !layer.editable) continue;
            // Pages beyond this score's last (from an earlier, longer upload) are kept as they were.
            const pagesToSave = [...layer.canvases.map(serialize), ...layer.pages.slice(layer.canvases.length)];
            const response = await fetch(layer.saveUrl, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ pages: pagesToSave }),
            });
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                updateStatus(`Not saved: ${body.message ?? response.statusText}`);
                return;
            }
            layer.dirty = false;
        }
        updateStatus();
    };
    saveButton.addEventListener('click', save);
    window.addEventListener('beforeunload', (event) => {
        if (anyDirty()) event.preventDefault();
    });

    // --- PDF export -------------------------------------------------------
    const overlayFor = (index, scale) => {
        const page = pages[index];
        const visible = LAYERS.map((name) => layers[name]).filter((layer) => layer.visible && layer.canvases[index].getObjects().length > 0);
        if (visible.length === 0) return null;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(page.width * scale);
        canvas.height = Math.round(page.height * scale);
        const ctx = canvas.getContext('2d');
        for (const layer of visible) ctx.drawImage(layer.canvases[index].toCanvasElement(scale), 0, 0, canvas.width, canvas.height);
        return canvas;
    };

    exportButton.addEventListener('click', async () => {
        finishEditing();
        exportButton.disabled = true;
        updateStatus('Making the PDF…');
        try {
            const bytes = config.source.type === 'pdf'
                ? await annotatedPdf(rendered.bytes, pages.length, overlayFor)
                : await scorePdf(pages, overlayFor, config.title);
            download(bytes, `${config.title.replace(/[^\p{L}\p{N} _-]+/gu, '').trim() || 'score'} (annotated).pdf`);
            updateStatus();
        } catch (e) {
            updateStatus(`The PDF could not be made: ${e.message}`);
        } finally {
            exportButton.disabled = false;
        }
    });

    applyTool();
    updateStatus();
}
