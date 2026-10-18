<template>
    <Head title="Settings" />
    <section class="mx-auto w-full max-w-3xl" aria-labelledby="settings-title">
        <header class="mb-6">
            <h1 id="settings-title" class="text-2xl font-bold text-gray-900 dark:text-white">Settings</h1>
            <p class="mt-1 text-gray-600 dark:text-gray-400">Manage your reading preferences.</p>
        </header>
        <form
            ref="element"
            :aria-busy="form.processing"
            class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            @submit.prevent="save"
        >
            <fieldset :disabled="form.processing || deviceBusy" class="space-y-6">
                <label class="flex min-h-11 items-start justify-between gap-4">
                    <span class="space-y-2">
                        <span class="block text-lg font-semibold text-gray-900 dark:text-white">Deuterocanonical books</span>
                        <span id="canon-help" class="block text-sm leading-6 text-gray-600 dark:text-gray-400"
                            >Include Tobit, Judith, Wisdom, Sirach, Baruch, 1-2 Maccabees, and the Catholic-integrated additions to Esther
                            and Daniel when logging readings.</span
                        >
                    </span>
                    <input
                        v-model="form.include_deuterocanonical"
                        type="checkbox"
                        name="include_deuterocanonical"
                        aria-label="Include deuterocanonical books"
                        aria-describedby="canon-help"
                        class="peer sr-only"
                    />
                    <span
                        class="relative mt-1 h-6 w-11 shrink-0 rounded-full bg-gray-200 after:absolute after:start-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-focus:ring-4 peer-focus:ring-primary-300 dark:bg-gray-700 dark:peer-focus:ring-primary-800"
                    ></span>
                </label>
                <BrowserReminders :config="push" :csrf-token="page.props.reader.csrfToken" @busy="deviceBusy = $event" />
                <section aria-labelledby="reminder-schedule-title" class="border-t border-gray-200 pt-6 dark:border-gray-700">
                    <h2 id="reminder-schedule-title" class="text-lg font-semibold text-gray-900 dark:text-white">Reminder schedule</h2>
                    <p class="mt-2 mb-3 text-sm text-gray-600 dark:text-gray-400">Save changes below.</p>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <label v-for="preference in reminders" :key="preference.name" class="flex min-h-11 items-start gap-3 py-3">
                            <input
                                v-model="form[preference.name]"
                                type="checkbox"
                                :name="preference.name"
                                :aria-describedby="preference.name + '-help'"
                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span class="space-y-1">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ preference.label }}</span>
                                <span :id="preference.name + '-help'" class="block text-sm leading-6 text-gray-600 dark:text-gray-400">{{
                                    preference.help
                                }}</span>
                            </span>
                        </label>
                    </div>
                </section>
                <div class="space-y-3 border-t border-gray-200 pt-6 dark:border-gray-700">
                    <label for="reading_timezone" class="text-lg font-semibold text-gray-900 dark:text-white">Time zone</label>
                    <p id="timezone-help" class="text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Used for your reading days, streaks, and reminders. Set automatically and stays the same when you travel. Offsets
                        shown are current.
                    </p>
                    <select
                        id="reading_timezone"
                        v-model="form.reading_timezone"
                        name="reading_timezone"
                        :aria-invalid="form.errors.reading_timezone ? 'true' : undefined"
                        aria-describedby="timezone-help reading_timezone-error"
                        class="form-input rounded-lg pr-10 shadow-none"
                    >
                        <option v-for="(label, zone) in readingTimezoneOptions" :key="zone" :value="zone">{{ label }}</option>
                    </select>
                    <p id="reading_timezone-error" role="alert" class="text-sm text-red-700 dark:text-red-300">
                        {{ form.errors.reading_timezone }}
                    </p>
                </div>
                <p v-for="(error, field) in otherErrors" :key="field" role="alert" class="text-sm text-red-700 dark:text-red-300">
                    {{ error }}
                </p>
                <div
                    class="grid gap-4 border-t border-gray-200 pt-6 dark:border-gray-700 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                >
                    <p role="status" aria-live="polite" class="text-sm font-medium text-success-600 dark:text-success-400">
                        {{ form.isDirty ? 'Unsaved changes' : page.props.status }}
                    </p>
                    <button
                        type="submit"
                        :disabled="form.processing || deviceBusy || !form.isDirty"
                        class="min-h-11 justify-self-end rounded-lg bg-accent-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-accent-600 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving…' : 'Save settings' }}
                    </button>
                </div>
            </fieldset>
        </form>
    </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import BrowserReminders from '@/components/BrowserReminders.vue';
import type { ReaderPageProps, SettingsProps } from '@/reader-types';

const props = defineProps<SettingsProps>();
const page = usePage<ReaderPageProps>();
const form = useForm({ ...props.values });
const element = ref<HTMLFormElement | null>(null);
const deviceBusy = ref(false);

const reminders: { name: 'daily_reading_reminder_enabled' | 'streak_warning_enabled'; label: string; help: string }[] = [
    { name: 'daily_reading_reminder_enabled', label: '09:00 daily reminder', help: 'Sends only when today has no reading log.' },
    {
        name: 'streak_warning_enabled',
        label: '18:00 streak warning',
        help: 'Sends only when yesterday was read and today is still unread.',
    },
];
const otherErrors = computed(() => Object.fromEntries(Object.entries(form.errors).filter(([field]) => field !== 'reading_timezone')));

function save() {
    if (form.processing || deviceBusy.value) return;
    form.patch(props.updateUrl, {
        preserveScroll: true,
        onSuccess: () => {
            Object.assign(form, props.values);
            form.defaults({ ...props.values });
        },
        onError: async () => {
            await nextTick();
            element.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
        },
    });
}

function warnBeforeLeaving(event: BeforeUnloadEvent) {
    if (form.isDirty || form.processing || deviceBusy.value) {
        event.preventDefault();
        event.returnValue = '';
    }
}

onMounted(() => window.addEventListener('beforeunload', warnBeforeLeaving));

onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeLeaving));
</script>
