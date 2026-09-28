const DESKTOP_MEDIA_QUERY = '(min-width: 768px)';
const FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function initAchievementHistory() {
    let activeDialog = null;

    const closeDialog = (restoreFocus = false) => {
        if (!activeDialog) {
            return;
        }

        const { backdrop, dialog, trigger, bodyWasLocked } = activeDialog;
        activeDialog = null;

        dialog.classList.add('hidden');
        dialog.classList.add('md:hidden');
        dialog.setAttribute('aria-hidden', 'true');
        dialog.setAttribute('aria-modal', 'false');
        dialog.style.removeProperty('left');
        dialog.style.removeProperty('top');
        backdrop.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');

        if (!bodyWasLocked) {
            document.body.classList.remove('overflow-hidden');
        }

        if (restoreFocus && trigger.isConnected) {
            trigger.focus();
        }
    };

    const positionDesktopDialog = (dialog, trigger) => {
        dialog.style.left = '0px';
        dialog.style.top = '0px';

        const dialogRect = dialog.getBoundingClientRect();
        const triggerRect = trigger.getBoundingClientRect();
        const edgeSpacing = 12;
        const verticalSpacing = 8;
        const maxLeft = window.innerWidth - dialogRect.width - edgeSpacing;
        const left = Math.max(edgeSpacing, Math.min(triggerRect.left, maxLeft));
        const preferredTop = triggerRect.bottom + verticalSpacing;
        const top = preferredTop + dialogRect.height <= window.innerHeight - edgeSpacing
            ? preferredTop
            : Math.max(edgeSpacing, triggerRect.top - dialogRect.height - verticalSpacing);

        dialog.style.left = `${Math.round(left)}px`;
        dialog.style.top = `${Math.round(top)}px`;
    };

    const openDialog = (trigger) => {
        const dialogId = trigger.dataset.achievementHistoryOpen;
        const dialog = document.getElementById(dialogId);
        const backdrop = document.getElementById(`${dialogId}-backdrop`);

        if (!dialog || !backdrop) {
            return;
        }

        if (activeDialog?.dialog === dialog) {
            closeDialog(true);

            return;
        }

        closeDialog();

        const isDesktop = window.matchMedia(DESKTOP_MEDIA_QUERY).matches;
        const bodyWasLocked = document.body.classList.contains('overflow-hidden');

        activeDialog = { backdrop, bodyWasLocked, dialog, isDesktop, trigger };
        dialog.classList.remove('hidden');
        if (isDesktop) {
            dialog.classList.remove('md:hidden');
        }
        dialog.setAttribute('aria-hidden', 'false');
        dialog.setAttribute('aria-modal', String(!isDesktop));
        trigger.setAttribute('aria-expanded', 'true');

        if (isDesktop) {
            positionDesktopDialog(dialog, trigger);
        } else {
            backdrop.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        dialog.querySelector('[data-achievement-history-close]')?.focus();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-achievement-history-open]');

        if (trigger) {
            openDialog(trigger);

            return;
        }

        if (!activeDialog) {
            return;
        }

        const closeButton = event.target.closest?.('[data-achievement-history-close]');

        if (closeButton && activeDialog.dialog.contains(closeButton)) {
            closeDialog(true);

            return;
        }

        if (event.target === activeDialog.backdrop) {
            closeDialog(true);
        } else if (!activeDialog.isDesktop && !activeDialog.dialog.querySelector('section')?.contains(event.target)) {
            closeDialog(true);
        } else if (activeDialog.isDesktop && !activeDialog.dialog.contains(event.target)) {
            closeDialog(activeDialog.dialog.contains(document.activeElement));
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!activeDialog) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog(true);

            return;
        }

        if (event.key !== 'Tab' || activeDialog.isDesktop) {
            return;
        }

        const focusableElements = Array.from(activeDialog.dialog.querySelectorAll(FOCUSABLE_SELECTOR));

        if (focusableElements.length === 0) {
            event.preventDefault();

            return;
        }

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey && document.activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
        } else if (!event.shiftKey && document.activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    });

    const repositionOrCloseDialog = () => {
        if (!activeDialog) {
            return;
        }

        if (!activeDialog.trigger.isConnected) {
            closeDialog();

            return;
        }

        const isDesktop = window.matchMedia(DESKTOP_MEDIA_QUERY).matches;

        if (isDesktop !== activeDialog.isDesktop) {
            closeDialog(true);

            return;
        }

        if (isDesktop) {
            positionDesktopDialog(activeDialog.dialog, activeDialog.trigger);
        }
    };

    window.addEventListener('resize', repositionOrCloseDialog);
    window.addEventListener('scroll', repositionOrCloseDialog, true);
    document.body.addEventListener('htmx:beforeSwap', (event) => {
        const swapTarget = event.detail?.target;

        if (!activeDialog || !swapTarget) {
            return;
        }

        const replacesDialog = swapTarget.contains(activeDialog.dialog)
            || swapTarget.contains(activeDialog.trigger)
            || activeDialog.dialog.contains(swapTarget);

        if (replacesDialog) {
            closeDialog();
        }
    });
}
