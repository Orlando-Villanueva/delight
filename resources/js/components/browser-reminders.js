/**
 * @param {HTMLElement} root
 * @param {string} csrfToken
 * @param {(busy: boolean) => void} [onBusy]
 * @returns {() => void}
 */
export function installBrowserReminders(root, csrfToken, onBusy = () => {}, localizedCopy = {}) {
    const copy = {
        canReceive: 'This browser can receive reading reminders.', turnOn: 'Turn on to connect this browser to Delight reminders.',
        enabled: 'Enabled', disabled: 'Disabled', unavailable: 'Unavailable', blocked: 'Blocked', blockedStatus: 'Notifications are blocked for this browser.',
        allowedConnect: 'Notifications are allowed. Turn this on to connect this browser to Delight reminders.', setupError: 'Reminder setup could not finish. Refresh the page and try again.',
        braveMessage: 'Brave could not connect to its push service. In Brave, enable Settings > Privacy and security > Use Google services for push messaging, then reload Delight and try again.',
        braveStatus: 'Brave could not connect to its push service.',
        pushMessage: 'This browser allowed notifications, but could not create a push subscription. Reload Delight and try again. If it repeats, the browser push service may be blocked or unavailable for this profile or network.',
        pushStatus: 'Browser could not create a push subscription.', permissionTimeout: 'The browser permission prompt did not finish.', statusError: 'Current browser reminder status could not be loaded.',
        statusCheckError: 'Delight could not check this browser reminder status. Refresh and try again.', statusCheckShort: 'Reminder status could not be checked.',
        notConfigured: 'Push reminders are not configured yet.', permissionWait: 'Waiting for browser permission.', permissionMissing: 'Choose Allow in the browser permission prompt, then try again.', permissionOff: 'Browser permission was not enabled.',
        savingBrowser: 'Saving this browser for reminders.', saveSubscription: 'Delight could not save this browser subscription. Refresh and try again.', subscriptionFailed: 'Subscription could not be saved.',
        saved: 'Saved', setupStillError: 'Notifications were allowed, but Delight could not finish setup. Refresh and try again.', updateFailed: 'Reminder preferences could not be updated. Try again.',
        offAllConfirm: 'Turn off reading reminders on every device?', saving: 'Saving...', disconnected: 'Reminder devices could not be disconnected.', offAllFailed: 'Could not turn off reminders everywhere. Try again.',
        stillWaiting: 'Still waiting for browser permission.', setupDidNotFinish: 'Reminder setup did not finish.', unsupported: 'This browser does not support web push reminders.',
        ...localizedCopy,
    };
    const controller = new AbortController();
    const timers = new Set();
    const delay = (callback, milliseconds) => {
        const timer = globalThis.setTimeout(() => {
            timers.delete(timer);
            if (!controller.signal.aborted) callback();
        }, milliseconds);
        timers.add(timer);
        return timer;
    };
    const postJson = (url, method, data = {}) =>
        fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
            signal: controller.signal,
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
            'dark:text-red-400'
        );

        if (tone === 'success') {
            element.classList.add('text-success-600', 'dark:text-success-400');
        } else if (tone === 'error') {
            element.classList.add('text-red-600', 'dark:text-red-400');
        } else {
            element.classList.add('text-gray-500', 'dark:text-gray-400');
        }

        if (clearAfter) {
            statusTimers.set(
                element,
                delay(() => {
                    element.hidden = true;
                    element.textContent = '';
                }, clearAfter)
            );
        }
    };

    const isIosLike = () =>
        /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    const isStandaloneDisplay = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    const isPushCapable = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

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
        const base64 = (base64String + padding).replaceAll('-', '+').replaceAll('_', '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let index = 0; index < rawData.length; index += 1) {
            outputArray[index] = rawData.codePointAt(index);
        }

        return outputArray;
    };

    const reminderSettings = root;

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
        const disconnectAllButton = reminderSettings.querySelector('[data-reading-reminders-disconnect-all]');
        let currentPushSubscription = null;

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

        const setEnabledState = (deviceEnabled, accountHasDevices = null) => {
            const hasDevices = accountHasDevices ?? reminderSettings.dataset.accountHasDevices === 'true';

            reminderSettings.dataset.deviceEnabled = deviceEnabled ? 'true' : 'false';
            reminderSettings.dataset.accountHasDevices = hasDevices ? 'true' : 'false';

            if (reminderToggle) {
                reminderToggle.setAttribute('aria-checked', deviceEnabled ? 'true' : 'false');
                reminderToggle.disabled = false;
                reminderToggle.title = deviceEnabled
                    ? copy.canReceive
                    : copy.turnOn;
            }

            if (reminderToggleLabel) {
                reminderToggleLabel.textContent = deviceEnabled ? copy.enabled : copy.disabled;
            }

            if (disconnectAllButton) {
                disconnectAllButton.hidden = !hasDevices;
            }

            setStatus();
        };

        const setBusy = (busy) => {
            if (controller.signal.aborted) return;
            onBusy(busy);
            if (reminderToggle) {
                reminderToggle.disabled = busy;
            }

            if (reminderToggleLabel) {
                reminderToggleLabel.textContent = busy
                    ? copy.saving
                    : reminderSettings.dataset.deviceEnabled === 'true'
                      ? copy.enabled
                      : copy.disabled;
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
                reminderToggleLabel.textContent = copy.unavailable;
            }
        };

        const showBlocked = () => {
            setStatus(copy.blockedStatus, true);

            setNotice(blockedNotice);

            if (reminderSettings.dataset.deviceEnabled !== 'true' && reminderToggle) {
                reminderToggle.setAttribute('aria-checked', 'false');
                reminderToggle.disabled = true;
            }

            if (reminderToggleLabel) {
                reminderToggleLabel.textContent = copy.blocked;
            }
        };

        const showPermissionGrantedButDisconnected = () => {
            if (reminderSettings.dataset.deviceEnabled === 'true' || !status) {
                return;
            }

            setStatus(copy.allowedConnect, true);
        };

        const showError = (message = copy.setupError, statusMessage = message) => {
            setStatus(statusMessage, true);

            setNotice(errorNotice, message);
        };

        const getPushSubscriptionFailureMessage = async () => {
            if (await isBraveBrowser()) {
                return {
                    message:
                        copy.braveMessage,
                    statusMessage: copy.braveStatus,
                };
            }

            return {
                message:
                    copy.pushMessage,
                statusMessage: copy.pushStatus,
            };
        };

        const requestPermissionWithTimeout = () =>
            new Promise((resolve, reject) => {
                const timeout = delay(() => {
                    reject(new Error(copy.permissionTimeout));
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

        const applySubscriptionState = (data) => {
            setEnabledState(Boolean(data.device_enabled), Boolean(data.account_has_devices));
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
                throw new Error(copy.statusError);
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
                reminderToggleLabel.textContent = copy.unavailable;
            }
        } else if (!isPushCapable()) {
            showUnsupported(copy.unsupported);
        } else if (Notification.permission === 'denied') {
            showBlocked();
        } else {
            if (Notification.permission === 'granted') {
                syncCurrentDeviceState().catch((error) => {
                    console.error('Reading reminder status failed', error);
                    showError(
                        copy.statusCheckError,
                        copy.statusCheckShort
                    );
                });
            }

            const enableReminders = async () => {
                const publicKey = reminderSettings.dataset.pushPublicKey;
                const previousAccountHasDevices = reminderSettings.dataset.accountHasDevices === 'true';

                if (!publicKey) {
                    setEnabledState(false, previousAccountHasDevices);
                    showError(copy.notConfigured);

                    return;
                }

                setBusy(true);
                setNotice(progressNotice);

                setStatus(copy.permissionWait, true);

                try {
                    const permission = await requestPermissionWithTimeout();
                    if (controller.signal.aborted) return;

                    if (permission !== 'granted') {
                        setEnabledState(false, previousAccountHasDevices);

                        if (permission === 'denied') {
                            showBlocked();
                        } else {
                            showError(
                                copy.permissionMissing,
                                copy.permissionOff
                            );
                        }

                        return;
                    }

                    setStatus(copy.savingBrowser, true);

                    const subscription = await getOrCreatePushSubscription(publicKey);
                    currentPushSubscription = subscription;
                    const subscriptionJson = subscription.toJSON();

                    const response = await postJson(reminderSettings.dataset.subscriptionUrl, 'POST', {
                        endpoint: subscription.endpoint,
                        keys: subscriptionJson.keys,
                        contentEncoding: 'aes128gcm',
                    });

                    if (!response.ok) {
                        console.error('Reading reminder subscription save failed', {
                            status: response.status,
                            body: await response.text(),
                        });
                        setEnabledState(false, previousAccountHasDevices);
                        showError(
                            copy.saveSubscription,
                            copy.subscriptionFailed
                        );

                        return;
                    }

                    setNotice(null);
                    applySubscriptionState(await response.json());
                    setPreferenceStatus(copy.saved, 'success', 2200);
                } catch (error) {
                    console.error('Reading reminder setup failed', error);

                    if (error?.name === 'NotAllowedError') {
                        setEnabledState(false, previousAccountHasDevices);
                        showBlocked();

                        return;
                    }

                    if (error?.name === 'AbortError') {
                        const failureMessage = await getPushSubscriptionFailureMessage();

                        setEnabledState(false, previousAccountHasDevices);
                        showError(failureMessage.message, failureMessage.statusMessage);

                        return;
                    }

                    if (error?.message === copy.permissionTimeout) {
                        setEnabledState(false, previousAccountHasDevices);
                        showError(
                            copy.permissionMissing,
                            copy.stillWaiting
                        );

                        return;
                    }

                    setEnabledState(false, previousAccountHasDevices);
                    showError(
                        copy.setupStillError,
                        copy.setupDidNotFinish
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
                        setEnabledState(previousDeviceEnabled, previousAccountHasDevices);
                        showError(copy.updateFailed);

                        return;
                    }

                    setNotice(null);
                    applySubscriptionState(await response.json());
                    currentPushSubscription = null;
                    setPreferenceStatus(copy.saved, 'success', 2200);

                    if (isPushCapable() && Notification.permission === 'denied') {
                        showBlocked();
                    }
                } catch (error) {
                    setEnabledState(previousDeviceEnabled, previousAccountHasDevices);
                    showError(copy.updateFailed);
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

            disconnectAllButton?.addEventListener('click', async () => {
                if (!window.confirm(copy.offAllConfirm)) {
                    return;
                }

                setBusy(true);
                disconnectAllButton.disabled = true;
                setPreferenceStatus(copy.saving);

                try {
                    const response = await postJson(reminderSettings.dataset.disconnectAllUrl, 'DELETE');

                    if (!response.ok) {
                        throw new Error(copy.disconnected);
                    }

                    currentPushSubscription = null;
                    applySubscriptionState(await response.json());
                    setPreferenceStatus(copy.saved, 'success', 2200);
                } catch (error) {
                    setPreferenceStatus(copy.offAllFailed, 'error');
                } finally {
                    setBusy(false);
                    disconnectAllButton.disabled = false;
                }
            });
        }
    }

    return () => {
        controller.abort();
        timers.forEach((timer) => globalThis.clearTimeout(timer));
        onBusy(false);
    };
}
