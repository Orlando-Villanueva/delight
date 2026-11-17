<template>
    <div
        id="reading-reminders"
        ref="root"
        data-reading-reminders-settings
        data-device-enabled="false"
        :data-account-has-devices="config.accountHasDevices ? 'true' : 'false'"
        :data-push-public-key="config.publicKey"
        :data-subscription-url="config.subscriptionUrl"
        :data-status-url="config.statusUrl"
        :data-unsubscribe-url="config.unsubscribeUrl"
        :data-disconnect-all-url="config.disconnectAllUrl"
        class="mt-6 flex flex-col gap-4 border-t border-gray-200 pt-6 dark:border-gray-700"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 space-y-2">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ copy.browserReminders.title }}
                    <span
                            :title="copy.browserReminders.betaTitle"
                            class="inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:border-primary-400/40 dark:bg-primary-900/60 dark:text-primary-200"
                    >{{ copy.browserReminders.beta }}</span>
                </h2>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ copy.browserReminders.browserNow }}</p>
                <p data-reading-reminders-status hidden class="text-sm leading-6 text-gray-600 dark:text-gray-400"></p>
            </div>

            <button
                type="button"
                role="switch"
                :aria-label="copy.browserReminders.switch"
                data-reading-reminders-toggle
                aria-checked="false"
                class="group mt-0.5 inline-flex cursor-pointer items-center"
            >
                <span
                    class="relative h-6 w-11 rounded-full bg-gray-200 transition group-aria-checked:bg-primary-600 group-disabled:cursor-not-allowed group-disabled:opacity-60 group-focus:outline-none group-focus:ring-4 group-focus:ring-primary-300 dark:bg-gray-700 dark:group-focus:ring-primary-800 after:absolute after:start-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all group-aria-checked:after:translate-x-full rtl:group-aria-checked:after:-translate-x-full"
                ></span>
                <span data-reading-reminders-toggle-label class="sr-only">{{ copy.browserReminders.disabled }}</span>
            </button>
        </div>

        <div
            data-reading-reminders-progress
            hidden
            class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm leading-6 text-blue-900 dark:border-blue-900/60 dark:bg-blue-900/20 dark:text-blue-100"
        >
            {{ copy.browserReminders.allowPrompt }}
        </div>

        <div
            data-reading-reminders-ios-guidance
            hidden
            class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm leading-6 text-yellow-900 dark:border-yellow-900/60 dark:bg-yellow-900/20 dark:text-yellow-100"
        >
            {{ copy.browserReminders.ios }}
        </div>

        <div
            data-reading-reminders-unsupported
            hidden
            class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm leading-6 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
        >
            {{ copy.browserReminders.unsupported }}
        </div>

        <div
            data-reading-reminders-blocked
            hidden
            class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm leading-6 text-yellow-900 dark:border-yellow-900/60 dark:bg-yellow-900/20 dark:text-yellow-100"
        >
            {{ copy.browserReminders.blockedNotice }}
        </div>

        <div
            data-reading-reminders-error
            hidden
            class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm leading-6 text-red-800 dark:border-red-900/60 dark:bg-red-900/20 dark:text-red-100"
        >
            {{ copy.browserReminders.setupError }}
        </div>

        <p class="text-sm text-gray-600 dark:text-gray-400">{{ copy.browserReminders.deviceNotice }}</p>

        <p data-reading-reminders-preferences-status hidden role="status" aria-live="polite" class="text-sm"></p>
        <div class="flex justify-end">
            <button
                type="button"
                data-reading-reminders-disconnect-all
                :hidden="!config.accountHasDevices"
                class="min-h-11 text-sm text-gray-600 underline underline-offset-4 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
            >
                {{ copy.browserReminders.turnOffEverywhere }}
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { installBrowserReminders } from '@/components/browser-reminders.js';
import type { BrowserReminderConfig } from '@/reader-types';
import { useReaderCopy } from '@/reader-copy';

const props = defineProps<{ config: BrowserReminderConfig; csrfToken: string }>();
const copy = useReaderCopy();
const emit = defineEmits<{ busy: [value: boolean] }>();
const root = ref<HTMLElement | null>(null);

let dispose: (() => void) | undefined;

onMounted(() => {
    if (!root.value) return;
    dispose = installBrowserReminders(root.value, props.csrfToken, (busy) => {
        emit('busy', busy);
    }, copy.value.browserReminders);
});

onBeforeUnmount(() => dispose?.());
</script>
