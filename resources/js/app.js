import './bootstrap';
import confetti from 'canvas-confetti';

window.confetti = confetti;

const applyFlowbiteBackdropPatch = () => {
    if (typeof window === 'undefined' || !window.Modal || window.Modal.__backdropPatched) {
        return;
    }

    const originalCreateBackdrop = window.Modal.prototype._createBackdrop;

    if (typeof originalCreateBackdrop !== 'function') {
        return;
    }

    window.Modal.prototype._createBackdrop = function patchedCreateBackdrop() {
        if (this._options && typeof this._options.backdropClasses === 'string') {
            const classes = this._options.backdropClasses
                .split(' ')
                .filter((cls) => cls.length > 0 && !/^z-/.test(cls));

            classes.push('z-stack-backdrop');
            this._options.backdropClasses = classes.join(' ');
        }

        originalCreateBackdrop.call(this);

        if (this._backdropEl) {
            this._backdropEl.classList.remove('z-40');
            this._backdropEl.classList.add('z-stack-backdrop');
        }
    };

    window.Modal.__backdropPatched = true;
};

const initFlowbiteWithPatches = () => {
    applyFlowbiteBackdropPatch();

    if (typeof window.initFlowbite === 'function') {
        window.initFlowbite();
    }
};

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        initFlowbiteWithPatches();

        document.body.addEventListener('htmx:afterSwap', initFlowbiteWithPatches);

        const pageNavigationLoading = document.getElementById('page-navigation-loading');

        const isPageContainerRequest = (event) => {
            const target = event?.detail?.target;

            return target?.id === 'page-container';
        };

        let pageNavigationLoadingStartedAt = 0;
        let pageNavigationLoadingRampFrame = null;
        let pageNavigationLoadingFinishTimeout = null;
        let pageNavigationLoadingHideTimeout = null;
        let pageNavigationLoadingResetTimeout = null;

        const clearPageNavigationLoadingTimers = () => {
            if (pageNavigationLoadingRampFrame) {
                globalThis.cancelAnimationFrame(pageNavigationLoadingRampFrame);
                pageNavigationLoadingRampFrame = null;
            }

            globalThis.clearTimeout(pageNavigationLoadingFinishTimeout);
            globalThis.clearTimeout(pageNavigationLoadingHideTimeout);
            globalThis.clearTimeout(pageNavigationLoadingResetTimeout);
        };

        const resetPageNavigationLoading = () => {
            pageNavigationLoading.value = 0;
        };

        const hideFinishedPageNavigationLoading = () => {
            pageNavigationLoading.classList.remove('opacity-100');
            pageNavigationLoading.classList.add('opacity-0');
            pageNavigationLoading.setAttribute('aria-hidden', 'true');

            pageNavigationLoadingResetTimeout = globalThis.setTimeout(resetPageNavigationLoading, 180);
        };

        const finishPageNavigationLoading = () => {
            pageNavigationLoading.value = 100;
            pageNavigationLoadingHideTimeout = globalThis.setTimeout(hideFinishedPageNavigationLoading, 220);
        };

        const showPageNavigationLoading = () => {
            if (pageNavigationLoading) {
                clearPageNavigationLoadingTimers();
                pageNavigationLoadingStartedAt = Date.now();
                pageNavigationLoading.value = 0;
                pageNavigationLoading.classList.remove('opacity-0');
                pageNavigationLoading.classList.add('opacity-100');
                pageNavigationLoading.setAttribute('aria-hidden', 'false');

                pageNavigationLoadingRampFrame = globalThis.requestAnimationFrame(() => {
                    pageNavigationLoading.value = 70;
                    pageNavigationLoadingRampFrame = null;
                });
            }
        };

        const hidePageNavigationLoading = () => {
            if (pageNavigationLoading) {
                clearPageNavigationLoadingTimers();

                const minimumVisibleDuration = 180;
                const elapsed = Date.now() - pageNavigationLoadingStartedAt;
                const finishDelay = Math.max(minimumVisibleDuration - elapsed, 0);

                pageNavigationLoadingFinishTimeout = globalThis.setTimeout(finishPageNavigationLoading, finishDelay);
            }
        };

        const hideIfPageContainerRequest = (event) => {
            if (isPageContainerRequest(event)) {
                hidePageNavigationLoading();
            }
        };

        document.body.addEventListener('htmx:afterRequest', hideIfPageContainerRequest);

        // Close all Flowbite dropdowns when a major HTMX navigation occurs
        document.body.addEventListener('htmx:beforeRequest', (event) => {
            const target = event.detail.target;

            if (target?.id !== 'page-container') {
                return;
            }

            showPageNavigationLoading();

            if (typeof FlowbiteInstances !== 'undefined') {
                const dropdowns = FlowbiteInstances.getInstances('Dropdown');
                if (dropdowns) {
                    Object.values(dropdowns).forEach(instance => {
                        if (typeof instance.hide === 'function') {
                            instance.hide();
                        }
                    });
                }
            }
        });

        document.body.addEventListener('hideModal', (event) => {
            const modalId = event?.detail?.id ?? event?.detail;

            if (!modalId || typeof window === 'undefined' || typeof window.Modal === 'undefined') {
                return;
            }

            const modalElement = document.getElementById(modalId);

            if (!modalElement) {
                return;
            }

            let instance = typeof window.Modal.getInstance === 'function'
                ? window.Modal.getInstance(modalElement)
                : null;

            if (!instance) {
                instance = new window.Modal(modalElement);
            }

            if (instance && typeof instance.hide === 'function') {
                instance.hide();
            }
        });

        const readingContent = document.getElementById('reading-content');
        const loadingOverlay = document.getElementById('loading');

        if (readingContent && loadingOverlay) {
            const showLoadingOverlay = () => {
                loadingOverlay.classList.remove('hidden');
            };

            const hideLoadingOverlay = () => {
                loadingOverlay.classList.add('hidden');
            };

            const isReadingContentRequest = (event) => {
                const triggerElement = event?.detail?.elt;

                return Boolean(triggerElement && readingContent.contains(triggerElement));
            };

            document.body.addEventListener('htmx:beforeRequest', (event) => {
                if (isReadingContentRequest(event)) {
                    showLoadingOverlay();
                }
            });

            const hideIfRelevant = (event) => {
                if (isReadingContentRequest(event)) {
                    hideLoadingOverlay();
                }
            };

            document.body.addEventListener('htmx:afterSwap', hideIfRelevant);
            document.body.addEventListener('htmx:afterSettle', hideIfRelevant);
            document.body.addEventListener('htmx:responseError', hideIfRelevant);
            document.body.addEventListener('htmx:sendError', hideIfRelevant);
        }
    });
}
