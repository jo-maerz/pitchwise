import { ScoreView } from './practice/score-view.js';

/** Draws the recognised score on the review step of a PDF upload, so the owner can compare it with their PDF. */
const container = document.getElementById('score-preview');

if (container) {
    const view = new ScoreView(container);
    view.load(container.dataset.url)
        .then(() => view.render('continuous'))
        .catch((error) => {
            const box = document.getElementById('score-preview-error');
            box.textContent = error.message || 'The score could not be drawn.';
            box.classList.remove('hidden');
        });
}
