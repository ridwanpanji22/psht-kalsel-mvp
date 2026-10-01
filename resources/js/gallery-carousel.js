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
        let isStopped = false;
        let isPointerInside = false;
        let isFocusInside = false;
        let intervalId = null;

        // Reachable-position model. Cards are fixed-width with a fixed gap, so the scroller can
        // only start a card at offsets 0..maxIndex; `scrollToIndex(items.length - 1)` would clamp
        // to the same scroll position as `maxIndex`. With N cards visible, the last N-1 dots all
        // map to that single terminal scroll position, which is why the index is clamped to
        // `maxIndex` everywhere instead of wrapping around `items.length`.
        let stride = 0;
        let maxIndex = Math.max(0, items.length - 1);

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        const measureGeometry = () => {
            const first = items[0];
            if (!first) return;
            const gap = parseFloat(window.getComputedStyle(scroller).columnGap) || 0;
            const width = first.getBoundingClientRect().width;
            stride = width + gap;
            const visibleCount = stride > 0 ? Math.max(1, Math.round(scroller.clientWidth / stride)) : 1;
            maxIndex = Math.max(0, items.length - visibleCount);
        };

        const updateDots = (newIndex) => {
            dots.forEach((dot, idx) => {
                // Every dot follows its own card; dots beyond `maxIndex` all describe the
                // same terminal scroll position, so several can be `aria-current` at once.
                const isCurrent = idx === Math.min(newIndex, maxIndex);
                dot.setAttribute('aria-current', isCurrent ? 'true' : 'false');
                const visual = dot.querySelector('span') ?? dot;
                visual.classList.toggle('w-6', isCurrent);
                visual.classList.toggle('bg-[#2162ed]', isCurrent);
                visual.classList.toggle('w-2', !isCurrent);
                visual.classList.toggle('bg-white/30', !isCurrent);
            });
        };

        const scrollToIndex = (index, announce = false) => {
            // Clamp into the reachable range before scrolling AND before setting state, so the
            // debounced scroll listener can never disagree with `currentIndex`.
            const target = Math.min(Math.max(index, 0), maxIndex);

            currentIndex = target;
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
            if (isPointerInside || isFocusInside || isStopped || prefersReducedMotion.matches) return;
            // Wrap to the first album instead of stepping past the reachable end.
            scrollToIndex(currentIndex >= maxIndex ? 0 : currentIndex + 1, false);
        };

        const startAutoplay = () => {
            if (prefersReducedMotion.matches || intervalId) return;
            intervalId = window.setInterval(autoAdvance, 5000);
        };

        // Pause is the union of "pointer inside" and "focus inside". Resuming on pointerleave
        // while a child still holds focus (or on focusout while the pointer is still inside)
        // would restart autoplay under the user.
        const pauseAutoplay = () => {
            if (isStopped) return;
            if (intervalId) {
                window.clearInterval(intervalId);
                intervalId = null;
            }
        };
        const resumeAutoplayIfIdle = () => {
            if (isStopped || isPointerInside || isFocusInside) return;
            startAutoplay();
        };
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
            scrollToIndex(currentIndex <= 0 ? maxIndex : currentIndex - 1, true);
        });

        nextBtn?.addEventListener('click', () => {
            stopAutoplayPermanently();
            scrollToIndex(currentIndex >= maxIndex ? 0 : currentIndex + 1, true);
        });

        dots.forEach((dot, idx) => {
            dot.addEventListener('click', () => {
                stopAutoplayPermanently();
                scrollToIndex(idx, true);
            });
        });

        // Hover and Focus Pause Handlers
        carousel.addEventListener('pointerenter', () => {
            isPointerInside = true;
            pauseAutoplay();
        });
        carousel.addEventListener('pointerleave', () => {
            isPointerInside = false;
            resumeAutoplayIfIdle();
        });
        carousel.addEventListener('focusin', () => {
            isFocusInside = true;
            pauseAutoplay();
        });
        carousel.addEventListener('focusout', (event) => {
            if (carousel.contains(event.relatedTarget)) return;
            isFocusInside = false;
            resumeAutoplayIfIdle();
        });

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

                const clampedIndex = Math.min(closestIndex, maxIndex);

                if (clampedIndex !== currentIndex) {
                    currentIndex = clampedIndex;
                    updateDots(currentIndex);
                }
            }, 100);
        }, { passive: true });

        // Keep the reachable model in sync with the viewport (1 card mobile -> 3 cards desktop).
        window.addEventListener('resize', () => {
            measureGeometry();
            if (currentIndex > maxIndex) {
                scrollToIndex(maxIndex, false);
            }
        }, { passive: true });

        // Reduced Motion Listener
        prefersReducedMotion.addEventListener('change', (e) => {
            if (e.matches) {
                stopAutoplayPermanently();
            } else if (!isStopped) {
                startAutoplay();
            }
        });

        measureGeometry();
        startAutoplay();
    });
}
