import './bootstrap';
import confetti from 'canvas-confetti';
import { authenticatedShell } from './components/authenticated-shell.js';

globalThis.confetti = confetti;
globalThis.authenticatedShell = authenticatedShell;

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

        document.body.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const retryButton = target?.closest('[data-offline-retry]');

            if (!retryButton) {
                return;
            }

            event.preventDefault();

            if ('scrollRestoration' in history) {
                history.scrollRestoration = 'manual';
            }

            globalThis.scrollTo({ top: 0, left: 0, behavior: 'auto' });
            document.querySelector('main.flex-1')?.scrollTo({ top: 0, left: 0 });

            globalThis.location.replace(globalThis.location.href);
        });

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

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const postJson = (url, method, data = {}) => fetch(url, {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
        });

        const statusTimers = new WeakMap();

        const showInlineStatus = (element, message = '', tone = 'neutral', clearAfter = null) => {
            if (!element) {
                return;
            }

            globalThis.clearTimeout(statusTimers.get(element));
            element.textContent = message;
            element.hidden = message === '';
            element.classList.remove(
                'text-gray-500',
                'dark:text-gray-400',
                'text-success-600',
                'dark:text-success-400',
                'text-red-600',
                'dark:text-red-400',
            );

            if (tone === 'success') {
                element.classList.add('text-success-600', 'dark:text-success-400');
            } else if (tone === 'error') {
                element.classList.add('text-red-600', 'dark:text-red-400');
            } else {
                element.classList.add('text-gray-500', 'dark:text-gray-400');
            }

            if (clearAfter) {
                statusTimers.set(element, globalThis.setTimeout(() => {
                    element.hidden = true;
                    element.textContent = '';
                }, clearAfter));
            }
        };

        const elementsFor = (root, selector) => {
            const elements = Array.from(root.querySelectorAll(selector));

            if (root instanceof Element && root.matches(selector)) {
                elements.unshift(root);
            }

            return elements;
        };

        const isIosLike = () => /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        const isStandaloneDisplay = () => window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;

        const isPushCapable = () => 'serviceWorker' in navigator
            && 'PushManager' in window
            && 'Notification' in window;

        const isBraveBrowser = async () => {
            if (!navigator.brave || typeof navigator.brave.isBrave !== 'function') {
                return false;
            }

            try {
                return await navigator.brave.isBrave();
            } catch (error) {
                console.error('Brave detection failed', error);

                return false;
            }
        };

        const urlBase64ToUint8Array = (base64String) => {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let index = 0; index < rawData.length; index += 1) {
                outputArray[index] = rawData.charCodeAt(index);
            }

            return outputArray;
        };

        const initializeDeuterocanonicalSettings = (root = document) => {
            elementsFor(root, '[data-deuterocanonical-setting]').forEach((setting) => {
                if (setting.dataset.deuterocanonicalInitialized === 'true') {
                    return;
                }

                setting.dataset.deuterocanonicalInitialized = 'true';

                const toggle = setting.querySelector('[data-deuterocanonical-toggle]');
                const label = setting.querySelector('[data-deuterocanonical-toggle-label]');
                const status = setting.querySelector('[data-deuterocanonical-status]');
                const url = setting.dataset.settingsUrl;

                if (!toggle || !url) {
                    return;
                }

                toggle.dataset.savedChecked = toggle.checked ? 'true' : 'false';

                const setLabel = () => {
                    if (label) {
                        label.textContent = toggle.checked ? 'Enabled' : 'Disabled';
                    }
                };

                const refreshPlansNavigation = (plansNavigationHtml) => {
                    if (typeof plansNavigationHtml !== 'string' || plansNavigationHtml.length === 0) {
                        return;
                    }

                    const template = document.createElement('template');
                    template.innerHTML = plansNavigationHtml.trim();

                    ['desktop-plans-link', 'mobile-plans-link'].forEach((id) => {
                        const current = document.getElementById(id);
                        const replacement = template.content.querySelector('#' + id);

                        if (!current || !replacement) {
                            return;
                        }

                        replacement.removeAttribute('hx-swap-oob');
                        current.replaceWith(replacement);

                        if (window.htmx && typeof window.htmx.process === 'function') {
                            window.htmx.process(replacement);
                        }
                    });
                };

                toggle.addEventListener('change', async () => {
                    const previousChecked = toggle.dataset.savedChecked === 'true';

                    toggle.disabled = true;
                    setLabel();
                    showInlineStatus(status, 'Saving...');

                    try {
                        const response = await postJson(url, 'PATCH', {
                            include_deuterocanonical: toggle.checked,
                        });

                        if (!response.ok) {
                            throw new Error('Deuterocanonical preference could not be saved.');
                        }

                        const data = await response.json();

                        toggle.checked = Boolean(data.include_deuterocanonical);
                        toggle.dataset.savedChecked = toggle.checked ? 'true' : 'false';
                        refreshPlansNavigation(data.plans_navigation_html);
                        setLabel();
                        showInlineStatus(status, 'Saved', 'success', 2200);
                    } catch (error) {
                        toggle.checked = previousChecked;
                        setLabel();
                        showInlineStatus(status, 'Could not save. Try again.', 'error');
                    } finally {
                        toggle.disabled = false;
                    }
                });
            });
        };

        initializeDeuterocanonicalSettings();

        const reminderSettings = document.querySelector('[data-reading-reminders-settings]');

        if (reminderSettings) {
            const reminderToggle = reminderSettings.querySelector('[data-reading-reminders-toggle]');
            const reminderToggleLabel = reminderSettings.querySelector('[data-reading-reminders-toggle-label]');
            const unsupportedNotice = reminderSettings.querySelector('[data-reading-reminders-unsupported]');
            const blockedNotice = reminderSettings.querySelector('[data-reading-reminders-blocked]');
            const errorNotice = reminderSettings.querySelector('[data-reading-reminders-error]');
            const progressNotice = reminderSettings.querySelector('[data-reading-reminders-progress]');
            const iosGuidance = reminderSettings.querySelector('[data-reading-reminders-ios-guidance]');
            const status = reminderSettings.querySelector('[data-reading-reminders-status]');
            const preferenceStatus = reminderSettings.querySelector('[data-reading-reminders-preferences-status]');
            const timezoneInput = reminderSettings.querySelector('[data-push-timezone]');
            const preferenceInputs = reminderSettings.querySelectorAll('[data-reading-reminders-preference]');
            const disconnectAllButton = reminderSettings.querySelector('[data-reading-reminders-disconnect-all]');
            let currentPushSubscription = null;

            if (timezoneInput && Intl.DateTimeFormat().resolvedOptions().timeZone) {
                timezoneInput.value = Intl.DateTimeFormat().resolvedOptions().timeZone;
            }

            const setStatus = (message = '', visible = false) => {
                if (!status) {
                    return;
                }

                status.textContent = message;
                status.hidden = !visible;
            };

            const setNotice = (activeNotice, message = null) => {
                [unsupportedNotice, blockedNotice, errorNotice, progressNotice, iosGuidance].forEach((notice) => {
                    if (notice) {
                        notice.hidden = notice !== activeNotice;
                    }
                });

                if (activeNotice && message) {
                    activeNotice.textContent = message;
                }
            };

            const setPreferenceState = (accountHasDevices, activateDefaults = false) => {
                preferenceInputs.forEach((input) => {
                    input.disabled = !accountHasDevices;

                    if (activateDefaults && !input.checked) {
                        input.checked = true;
                    }

                    input.dataset.savedChecked = input.checked ? 'true' : 'false';
                });
            };

            const setEnabledState = (deviceEnabled, accountHasDevices = null, activateDefaults = false) => {
                const hasDevices = accountHasDevices ?? (reminderSettings.dataset.accountHasDevices === 'true');

                reminderSettings.dataset.deviceEnabled = deviceEnabled ? 'true' : 'false';
                reminderSettings.dataset.accountHasDevices = hasDevices ? 'true' : 'false';

                if (reminderToggle) {
                    reminderToggle.setAttribute('aria-checked', deviceEnabled ? 'true' : 'false');
                    reminderToggle.disabled = false;
                    reminderToggle.title = deviceEnabled
                        ? 'This browser can receive reading reminders.'
                        : 'Turn on to connect this browser to Delight reminders.';
                }

                if (reminderToggleLabel) {
                    reminderToggleLabel.textContent = deviceEnabled ? 'Enabled' : 'Disabled';
                }

                if (disconnectAllButton) {
                    disconnectAllButton.hidden = !hasDevices;
                }

                setPreferenceState(hasDevices, activateDefaults);

                setStatus();
            };

            const setBusy = (busy) => {
                if (reminderToggle) {
                    reminderToggle.disabled = busy;
                }

                if (reminderToggleLabel) {
                    reminderToggleLabel.textContent = busy
                        ? 'Saving...'
                        : (reminderSettings.dataset.deviceEnabled === 'true' ? 'Enabled' : 'Disabled');
                }
            };

            const showUnsupported = (message) => {
                setStatus(message, true);

                setNotice(unsupportedNotice);

                if (reminderSettings.dataset.deviceEnabled !== 'true' && reminderToggle) {
                    reminderToggle.setAttribute('aria-checked', 'false');
                    reminderToggle.disabled = true;
                }

                if (reminderToggleLabel) {
                    reminderToggleLabel.textContent = 'Unavailable';
                }
            };

            const showBlocked = () => {
                setStatus('Notifications are blocked for this browser.', true);

                setNotice(blockedNotice);

                if (reminderSettings.dataset.deviceEnabled !== 'true' && reminderToggle) {
                    reminderToggle.setAttribute('aria-checked', 'false');
                    reminderToggle.disabled = true;
                }

                if (reminderToggleLabel) {
                    reminderToggleLabel.textContent = 'Blocked';
                }
            };

            const showPermissionGrantedButDisconnected = () => {
                if (reminderSettings.dataset.deviceEnabled === 'true' || !status) {
                    return;
                }

                setStatus('Notifications are allowed. Turn this on to connect this browser to Delight reminders.', true);
            };

            const showError = (
                message = 'Reminder setup could not finish. Refresh the page and try again.',
                statusMessage = message,
            ) => {
                setStatus(statusMessage, true);

                setNotice(errorNotice, message);
            };

            const getPushSubscriptionFailureMessage = async () => {
                if (await isBraveBrowser()) {
                    return {
                        message: 'Brave could not connect to its push service. In Brave, enable Settings > Privacy and security > Use Google services for push messaging, then reload Delight and try again.',
                        statusMessage: 'Brave could not connect to its push service.',
                    };
                }

                return {
                    message: 'This browser allowed notifications, but could not create a push subscription. Reload Delight and try again. If it repeats, the browser push service may be blocked or unavailable for this profile or network.',
                    statusMessage: 'Browser could not create a push subscription.',
                };
            };

            const requestPermissionWithTimeout = () => new Promise((resolve, reject) => {
                const timeout = window.setTimeout(() => {
                    reject(new Error('The browser permission prompt did not finish.'));
                }, 20000);

                Notification.requestPermission()
                    .then((permission) => {
                        window.clearTimeout(timeout);
                        resolve(permission);
                    })
                    .catch((error) => {
                        window.clearTimeout(timeout);
                        reject(error);
                    });
            });

            const subscribeWithRegistration = async (registration, publicKey) => {
                let subscription = await registration.pushManager.getSubscription();

                if (!subscription) {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(publicKey),
                    });
                }

                return subscription;
            };

            const readyServiceWorkerRegistration = async () => {
                await navigator.serviceWorker.register('/sw.js', { scope: '/' });

                return navigator.serviceWorker.ready;
            };

            const getOrCreatePushSubscription = async (publicKey) => {
                return subscribeWithRegistration(await readyServiceWorkerRegistration(), publicKey);
            };

            const setPreferenceStatus = (message = '', tone = 'neutral', clearAfter = null) => {
                showInlineStatus(preferenceStatus, message, tone, clearAfter);
            };

            const saveReminderPreference = async (input) => {
                if (reminderSettings.dataset.accountHasDevices !== 'true') {
                    return;
                }

                const previousChecked = input.dataset.savedChecked === 'true';
                const preferenceName = input.dataset.readingRemindersPreference || input.name;

                input.disabled = true;
                setPreferenceStatus('Saving...');

                try {
                    const response = await postJson(reminderSettings.dataset.preferencesUrl, 'PATCH', {
                        [preferenceName]: input.checked,
                        timezone: timezoneInput?.value || Intl.DateTimeFormat().resolvedOptions().timeZone,
                    });

                    if (!response.ok) {
                        throw new Error('Reminder preference could not be saved.');
                    }

                    const data = await response.json();

                    if (Object.prototype.hasOwnProperty.call(data, preferenceName)) {
                        input.checked = Boolean(data[preferenceName]);
                    }

                    input.dataset.savedChecked = input.checked ? 'true' : 'false';
                    setPreferenceStatus('Saved', 'success', 2200);
                } catch (error) {
                    input.checked = previousChecked;
                    setPreferenceStatus('Could not save. Try again.', 'error');
                } finally {
                    if (reminderSettings.dataset.accountHasDevices === 'true') {
                        input.disabled = false;
                    }
                }
            };

            const applySubscriptionState = (data, activateDefaults = false) => {
                if (Object.prototype.hasOwnProperty.call(data, 'daily_reading_reminder_enabled')) {
                    const input = reminderSettings.querySelector('[data-reading-reminders-preference="daily_reading_reminder_enabled"]');

                    if (input) {
                        input.checked = Boolean(data.daily_reading_reminder_enabled);
                    }
                }

                if (Object.prototype.hasOwnProperty.call(data, 'streak_warning_enabled')) {
                    const input = reminderSettings.querySelector('[data-reading-reminders-preference="streak_warning_enabled"]');

                    if (input) {
                        input.checked = Boolean(data.streak_warning_enabled);
                    }
                }

                if (timezoneInput && data.push_notification_timezone) {
                    timezoneInput.value = data.push_notification_timezone;
                }

                setEnabledState(
                    Boolean(data.device_enabled),
                    Boolean(data.account_has_devices),
                    activateDefaults,
                );
            };

            const getCurrentPushSubscription = async () => {
                const registration = await readyServiceWorkerRegistration();

                return registration.pushManager.getSubscription();
            };

            const syncCurrentDeviceState = async () => {
                currentPushSubscription = await getCurrentPushSubscription();

                if (!currentPushSubscription) {
                    applySubscriptionState({
                        device_enabled: false,
                        account_has_devices: reminderSettings.dataset.accountHasDevices === 'true',
                    });
                    showPermissionGrantedButDisconnected();

                    return;
                }

                const response = await postJson(reminderSettings.dataset.statusUrl, 'POST', {
                    endpoint: currentPushSubscription.endpoint,
                });

                if (!response.ok) {
                    throw new Error('Current browser reminder status could not be loaded.');
                }

                applySubscriptionState(await response.json());

                if (reminderSettings.dataset.deviceEnabled !== 'true') {
                    showPermissionGrantedButDisconnected();
                }
            };

            setEnabledState(false, reminderSettings.dataset.accountHasDevices === 'true');

            if (isIosLike() && !isStandaloneDisplay()) {
                setNotice(iosGuidance);

                if (reminderToggle) {
                    reminderToggle.setAttribute('aria-checked', 'false');
                    reminderToggle.disabled = true;
                }

                if (reminderToggleLabel) {
                    reminderToggleLabel.textContent = 'Unavailable';
                }
            } else if (!isPushCapable()) {
                showUnsupported('This browser does not support web push reminders.');
            } else if (Notification.permission === 'denied') {
                showBlocked();
            } else {
                if (Notification.permission === 'granted') {
                    syncCurrentDeviceState().catch((error) => {
                        console.error('Reading reminder status failed', error);
                        showError(
                            'Delight could not check this browser reminder status. Refresh and try again.',
                            'Reminder status could not be checked.',
                        );
                    });
                }

                const enableReminders = async () => {
                    const publicKey = reminderSettings.dataset.pushPublicKey;
                    const previousAccountHasDevices = reminderSettings.dataset.accountHasDevices === 'true';

                    if (!publicKey) {
                        setEnabledState(false, previousAccountHasDevices);
                        showError('Push reminders are not configured yet.');

                        return;
                    }

                    setBusy(true);
                    setNotice(progressNotice);

                    setStatus('Waiting for browser permission.', true);

                    try {
                        const permission = await requestPermissionWithTimeout();

                        if (permission !== 'granted') {
                            setEnabledState(false, previousAccountHasDevices);

                            if (permission === 'denied') {
                                showBlocked();
                            } else {
                                showError(
                                    'Choose Allow in the browser permission prompt, then try again.',
                                    'Browser permission was not enabled.',
                                );
                            }

                            return;
                        }

                        setStatus('Saving this browser for reminders.', true);

                        const subscription = await getOrCreatePushSubscription(publicKey);
                        currentPushSubscription = subscription;
                        const subscriptionJson = subscription.toJSON();

                        const response = await postJson(reminderSettings.dataset.subscriptionUrl, 'POST', {
                            endpoint: subscription.endpoint,
                            keys: subscriptionJson.keys,
                            contentEncoding: 'aes128gcm',
                            timezone: timezoneInput?.value || Intl.DateTimeFormat().resolvedOptions().timeZone,
                        });

                        if (!response.ok) {
                            console.error('Reading reminder subscription save failed', {
                                status: response.status,
                                body: await response.text(),
                            });
                            setEnabledState(false, previousAccountHasDevices, false);
                            showError(
                                'Delight could not save this browser subscription. Refresh and try again.',
                                'Subscription could not be saved.',
                            );

                            return;
                        }

                        setNotice(null);
                        applySubscriptionState(await response.json(), true);
                        setPreferenceStatus('Saved', 'success', 2200);
                    } catch (error) {
                        console.error('Reading reminder setup failed', error);

                        if (error?.name === 'NotAllowedError') {
                            setEnabledState(false, previousAccountHasDevices, false);
                            showBlocked();

                            return;
                        }

                        if (error?.name === 'AbortError') {
                            const failureMessage = await getPushSubscriptionFailureMessage();

                            setEnabledState(false, previousAccountHasDevices, false);
                            showError(
                                failureMessage.message,
                                failureMessage.statusMessage,
                            );

                            return;
                        }

                        if (error?.message === 'The browser permission prompt did not finish.') {
                            setEnabledState(false, previousAccountHasDevices, false);
                            showError(
                                'Choose Allow in the browser permission prompt, then try again.',
                                'Still waiting for browser permission.',
                            );

                            return;
                        }

                        setEnabledState(false, previousAccountHasDevices, false);
                        showError(
                            'Notifications were allowed, but Delight could not finish setup. Refresh and try again.',
                            'Reminder setup did not finish.',
                        );
                    } finally {
                        setBusy(false);
                    }
                };

                const disableReminders = async () => {
                    const previousDeviceEnabled = reminderSettings.dataset.deviceEnabled === 'true';
                    const previousAccountHasDevices = reminderSettings.dataset.accountHasDevices === 'true';

                    setBusy(true);

                    try {
                        currentPushSubscription ??= await getCurrentPushSubscription();

                        if (!currentPushSubscription) {
                            setNotice(null);
                            setEnabledState(false, reminderSettings.dataset.accountHasDevices === 'true');
                            showPermissionGrantedButDisconnected();

                            return;
                        }

                        const response = await postJson(reminderSettings.dataset.unsubscribeUrl, 'DELETE', {
                            endpoint: currentPushSubscription.endpoint,
                        });

                        if (!response.ok) {
                            setEnabledState(previousDeviceEnabled, previousAccountHasDevices, false);
                            showError('Reminder preferences could not be updated. Try again.');

                            return;
                        }

                        setNotice(null);
                        applySubscriptionState(await response.json());
                        currentPushSubscription = null;
                        setPreferenceStatus('Saved', 'success', 2200);

                        if (isPushCapable() && Notification.permission === 'denied') {
                            showBlocked();
                        }
                    } catch (error) {
                        setEnabledState(previousDeviceEnabled, previousAccountHasDevices, false);
                        showError('Reminder preferences could not be updated. Try again.');
                    } finally {
                        setBusy(false);
                    }
                };

                reminderToggle?.addEventListener('click', async () => {
                    if (reminderToggle.getAttribute('aria-checked') === 'true') {
                        await disableReminders();

                        return;
                    }

                    await enableReminders();
                });

                preferenceInputs.forEach((input) => {
                    input.addEventListener('change', () => {
                        saveReminderPreference(input);
                    });
                });

                disconnectAllButton?.addEventListener('click', async () => {
                    if (!window.confirm('Turn off reading reminders on every device?')) {
                        return;
                    }

                    disconnectAllButton.disabled = true;
                    setPreferenceStatus('Saving...');

                    try {
                        const response = await postJson(reminderSettings.dataset.disconnectAllUrl, 'DELETE');

                        if (!response.ok) {
                            throw new Error('Reminder devices could not be disconnected.');
                        }

                        currentPushSubscription = null;
                        applySubscriptionState(await response.json());
                        setPreferenceStatus('Saved', 'success', 2200);
                    } catch (error) {
                        setPreferenceStatus('Could not turn off reminders everywhere. Try again.', 'error');
                    } finally {
                        disconnectAllButton.disabled = false;
                    }
                });
            }
        }

        document.body.addEventListener('htmx:afterSwap', (event) => {
            const target = event?.detail?.target;

            if (!(target instanceof Element) || target.id !== 'page-container') {
                return;
            }

            initializeDeuterocanonicalSettings(target);
        });
    });
}
