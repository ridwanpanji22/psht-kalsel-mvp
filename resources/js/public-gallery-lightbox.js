/**
 * Public gallery lightbox.
 *
 * Binds a single shared native `<dialog>` to the media triggers that were
 * rendered on the current page/filter. Only media that the server actually
 * rendered (i.e. public media of a published album) can ever enter the
 * navigation list, so no private/filtered media id or path is exposed here.
 */
export function initGalleryLightbox(root = document) {
    const dialog = root.querySelector('#gallery-dialog');

    if (!(dialog instanceof HTMLDialogElement)) return;

    const image = dialog.querySelector('[data-gallery-dialog-image]');
    const caption = dialog.querySelector('[data-gallery-dialog-caption]');
    const closeButton = dialog.querySelector('[data-lightbox-close]');
    const previousButton = dialog.querySelector('[data-lightbox-prev]');
    const nextButton = dialog.querySelector('[data-lightbox-next]');
    const status = dialog.querySelector('[data-gallery-dialog-status]');

    // Each trigger carries the rendered media's src/alt/album label.
    const triggers = [...root.querySelectorAll('[data-gallery-open]')];

    if (triggers.length === 0) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let activeIndex = 0;
    let restoreFocusTo = null;

    const show = (index) => {
        activeIndex = (index + triggers.length) % triggers.length;
        const trigger = triggers[activeIndex];
        const source = trigger.querySelector('img');

        if (!(source instanceof HTMLImageElement)) return;

        if (image instanceof HTMLImageElement) {
            image.src = source.currentSrc || source.src;
            image.alt = source.alt;
        }

        if (caption) {
            caption.textContent = trigger.dataset.mediaLabel || source.alt;
        }

        if (status) {
            status.textContent = `Media ${activeIndex + 1} dari ${triggers.length}`;
        }
    };

    const open = (trigger) => {
        restoreFocusTo = trigger;
        show(triggers.indexOf(trigger));
        dialog.showModal();

        if (closeButton instanceof HTMLElement) {
            closeButton.focus();
        } else {
            dialog.focus();
        }
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            open(trigger);
        });
    });

    previousButton?.addEventListener('click', () => show(activeIndex - 1));
    nextButton?.addEventListener('click', () => show(activeIndex + 1));
    closeButton?.addEventListener('click', () => dialog.close());

    // Escape is handled natively by <dialog>; clicking the backdrop closes too.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

    // Native modal focus restoration: return focus to the trigger that opened it.
    dialog.addEventListener('close', () => {
        if (restoreFocusTo instanceof HTMLElement && document.contains(restoreFocusTo)) {
            restoreFocusTo.focus();
        }
    });

    // Arrow-key navigation while the dialog is open.
    dialog.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;

        event.preventDefault();
        show(activeIndex + (event.key === 'ArrowLeft' ? -1 : 1));
    });

    // Scroll-smooth inside the dialog is purely decorative; the dialog itself
    // is centred by CSS, so reduced motion only affects the optional transition.
    if (reducedMotion.matches) {
        dialog.style.scrollBehavior = 'auto';
    }
}
