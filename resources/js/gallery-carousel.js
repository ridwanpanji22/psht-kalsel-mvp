export function initGalleryCarousels(root = document) {
    const carousels = root.querySelectorAll('[data-gallery-carousel]');

    carousels.forEach((carousel) => {
        const scroller = carousel.querySelector('[data-carousel-scroller]');
        const items = carousel.querySelectorAll('[data-carousel-item]');
        const dots = carousel.querySelectorAll('[data-carousel-dot]');
        const prevBtn = carousel.parentElement?.querySelector('[data-carousel-prev]') || carousel.querySelector('[data-carousel-prev]');
        const nextBtn = carousel.parentElement?.querySelector('[data-carousel-next]') || carousel.querySelector('[data-carousel-next]');
        const statusEl = carousel.querySelector('[data-carousel-status]');

        if (!scroller || items.length <= 1) return;

        let currentIndex = 0;
        let isPaused = false;
        let isStopped = false;
        let intervalId = null;

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        const updateDots = (newIndex) => {
            dots.forEach((dot, idx) => {
                const isCurrent = idx === newIndex;
                dot.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
                const visual = dot.querySelector('span') ?? dot;
                visual.classList.toggle('w-6', isCurrent);
                visual.classList.toggle('bg-[#2162ed]', isCurrent);
                visual.classList.toggle('w-2', !isCurrent);
                visual.classList.toggle('bg-white/30', !isCurrent);
            });
        };

        const scrollToIndex = (index, announce = false) => {
            if (index < 0) index = items.length - 1;
            if (index >= items.length) index = 0;

            currentIndex = index;
            const targetItem = items[currentIndex];

            if (targetItem) {
                const behavior = prefersReducedMotion.matches ? 'auto' : 'smooth';
                scroller.scrollTo({
                    left: targetItem.offsetLeft - scroller.offsetLeft,
                    behavior,
                });
            }

            updateDots(currentIndex);

            if (announce && statusEl) {
                const itemTitle = items[currentIndex].querySelector('h3')?.textContent?.trim() || '';
                statusEl.textContent = `Menampilkan album ${currentIndex + 1} dari ${items.length}: ${itemTitle}`;
            }
        };

        const autoAdvance = () => {
            if (isPaused || isStopped || prefersReducedMotion.matches) return;
            scrollToIndex(currentIndex + 1, false);
        };

        const startAutoplay = () => {
            if (prefersReducedMotion.matches || intervalId) return;
            intervalId = window.setInterval(autoAdvance, 5000);
        };

        const pauseAutoplay = () => { isPaused = true; };
        const resumeAutoplay = () => { isPaused = false; };
        const stopAutoplayPermanently = () => {
            isStopped = true;
            if (intervalId) {
                window.clearInterval(intervalId);
                intervalId = null;
            }
        };

        // Manual Navigation Handlers
        prevBtn?.addEventListener('click', () => {
            stopAutoplayPermanently();
            scrollToIndex(currentIndex - 1, true);
        });

        nextBtn?.addEventListener('click', () => {
            stopAutoplayPermanently();
            scrollToIndex(currentIndex + 1, true);
        });

        dots.forEach((dot, idx) => {
            dot.addEventListener('click', () => {
                stopAutoplayPermanently();
                scrollToIndex(idx, true);
            });
        });

        // Hover and Focus Pause Handlers
        carousel.addEventListener('pointerenter', pauseAutoplay);
        carousel.addEventListener('pointerleave', resumeAutoplay);
        carousel.addEventListener('focusin', pauseAutoplay);
        carousel.addEventListener('focusout', resumeAutoplay);

        // Scroll listener to update active dot when user manually swipes
        let scrollTimeout;
        scroller.addEventListener('scroll', () => {
            window.clearTimeout(scrollTimeout);
            scrollTimeout = window.setTimeout(() => {
                const scrollLeft = scroller.scrollLeft;
                let closestIndex = 0;
                let minDistance = Infinity;

                items.forEach((item, idx) => {
                    const distance = Math.abs((item.offsetLeft - scroller.offsetLeft) - scrollLeft);
                    if (distance < minDistance) {
                        minDistance = distance;
                        closestIndex = idx;
                    }
                });

                if (closestIndex !== currentIndex) {
                    currentIndex = closestIndex;
                    updateDots(currentIndex);
                }
            }, 100);
        }, { passive: true });

        // Reduced Motion Listener
        prefersReducedMotion.addEventListener('change', (e) => {
            if (e.matches) {
                stopAutoplayPermanently();
            } else if (!isStopped) {
                startAutoplay();
            }
        });

        startAutoplay();
    });
}
