const filters = [...document.querySelectorAll('[data-gallery-filter]')];
const cards = [...document.querySelectorAll('[data-gallery-card]')];
const count = document.querySelector('[data-gallery-count]');
const pageLabel = document.querySelector('[data-gallery-page]');
const previousPage = document.querySelector('[data-gallery-prev]');
const nextPage = document.querySelector('[data-gallery-next]');
const dialog = document.querySelector('#gallery-dialog');
const dialogImage = dialog?.querySelector('[data-gallery-dialog-image]');
const dialogCaption = dialog?.querySelector('[data-gallery-dialog-caption]');
const closeButton = dialog?.querySelector('[aria-label="Tutup foto"]');
const previousPhoto = dialog?.querySelector('[data-lightbox-prev]');
const nextPhoto = dialog?.querySelector('[data-lightbox-next]');
const pageSize = 6;
let currentPage = 1;
let activeCategory = 'all';
let filteredCards = cards;
let activePhotoIndex = 0;
let trigger = null;

function renderPage() {
    filteredCards = cards.filter((card) => activeCategory === 'all' || card.dataset.category === activeCategory);
    const totalPages = Math.max(1, Math.ceil(filteredCards.length / pageSize));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * pageSize;
    const visiblePage = filteredCards.slice(start, start + pageSize);

    cards.forEach((card) => { card.hidden = !visiblePage.includes(card); });
    if (count) count.textContent = `${filteredCards.length} album contoh`;
    if (pageLabel) pageLabel.textContent = `Halaman ${currentPage} dari ${totalPages}`;
    if (previousPage instanceof HTMLButtonElement) previousPage.disabled = currentPage === 1;
    if (nextPage instanceof HTMLButtonElement) nextPage.disabled = currentPage === totalPages;
}

filters.forEach((button) => {
    button.addEventListener('click', () => {
        activeCategory = button.dataset.galleryFilter;
        currentPage = 1;
        filters.forEach((item) => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
        renderPage();
    });
});

previousPage?.addEventListener('click', () => {
    currentPage = Math.max(1, currentPage - 1);
    renderPage();
});

nextPage?.addEventListener('click', () => {
    currentPage += 1;
    renderPage();
});

function showPhoto(index) {
    if (!(dialog instanceof HTMLDialogElement) || filteredCards.length === 0) return;
    activePhotoIndex = (index + filteredCards.length) % filteredCards.length;
    const card = filteredCards[activePhotoIndex];
    const image = card.querySelector('img');
    if (!(image instanceof HTMLImageElement)) return;

    if (dialogImage instanceof HTMLImageElement) {
        dialogImage.src = image.src;
        dialogImage.alt = image.alt;
    }
    if (dialogCaption) dialogCaption.textContent = card.dataset.title || image.alt;
}

cards.forEach((card) => {
    card.querySelector('[data-gallery-open]')?.addEventListener('click', (event) => {
        if (!(dialog instanceof HTMLDialogElement)) return;
        trigger = event.currentTarget;
        activePhotoIndex = filteredCards.indexOf(card);
        showPhoto(activePhotoIndex);
        dialog.showModal();
        closeButton?.focus();
    });
});

previousPhoto?.addEventListener('click', () => showPhoto(activePhotoIndex - 1));
nextPhoto?.addEventListener('click', () => showPhoto(activePhotoIndex + 1));
dialog?.addEventListener('close', () => trigger?.focus());
dialog?.addEventListener('click', (event) => {
    if (event.target === dialog && dialog instanceof HTMLDialogElement) dialog.close();
});

renderPage();
