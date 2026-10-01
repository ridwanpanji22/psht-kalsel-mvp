const filters = [...document.querySelectorAll('[data-gallery-filter]')];
const cards = [...document.querySelectorAll('[data-gallery-card]')];
const count = document.querySelector('[data-gallery-count]');
const dialog = document.querySelector('#gallery-dialog');
const dialogImage = dialog?.querySelector('[data-gallery-dialog-image]');
const dialogCaption = dialog?.querySelector('[data-gallery-dialog-caption]');
const closeButton = dialog?.querySelector('[aria-label="Tutup foto"]');
let trigger = null;

filters.forEach((button) => {
    button.addEventListener('click', () => {
        const category = button.dataset.galleryFilter;
        let visible = 0;

        filters.forEach((item) => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
        cards.forEach((card) => {
            const show = category === 'all' || card.dataset.category === category;
            card.hidden = !show;
            if (show) visible += 1;
        });

        if (count) count.textContent = `${visible} album contoh`;
    });
});

cards.forEach((card) => {
    const button = card.querySelector('[data-gallery-open]');
    const image = card.querySelector('img');

    button?.addEventListener('click', () => {
        if (!(dialog instanceof HTMLDialogElement) || !(image instanceof HTMLImageElement)) return;
        trigger = button;
        if (dialogImage instanceof HTMLImageElement) {
            dialogImage.src = image.src;
            dialogImage.alt = image.alt;
        }
        if (dialogCaption) dialogCaption.textContent = card.dataset.title || image.alt;
        dialog.showModal();
        closeButton?.focus();
    });
});

dialog?.addEventListener('close', () => trigger?.focus());
dialog?.addEventListener('click', (event) => {
    if (event.target === dialog && dialog instanceof HTMLDialogElement) dialog.close();
});
