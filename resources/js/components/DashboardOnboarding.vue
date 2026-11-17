<template>
    <ReaderDialog labelledby="onboarding-title" :dismissible="false" :busy="busy" initial-focus="[data-first-reading]" size="lg">
        <div class="space-y-5 p-6 sm:p-8">
            <header class="text-center"><h2 id="onboarding-title" class="text-2xl font-bold">{{ copy.welcome }}</h2><p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ copy.welcomeText }}</p></header>
            <ol class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-200"><li>{{ copy.onboardingStep1 }}</li><li>{{ copy.onboardingStep2 }}</li><li>{{ copy.onboardingStep3 }}</li></ol>
            <div class="space-y-2">
                <Link data-first-reading :href="logUrl" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-accent-500 px-5 py-3 text-sm font-semibold text-white hover:bg-accent-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-500">{{ copy.firstReading }}</Link>
                <a :href="onboarding.plansUrl" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-gray-100 px-5 py-3 text-sm font-medium text-gray-700 hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-primary-500 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">{{ copy.startReadingPlan }}</a>
                <button v-if="onboarding.canRemind" type="button" :disabled="busy || uncertain" class="min-h-11 w-full rounded-lg text-sm font-medium text-gray-600 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 dark:text-gray-300" @click="remind">{{ busy ? copy.onboardingReminder.scheduling : copy.onboardingReminder.remind }}</button>
            </div>
            <div v-if="error" role="alert" class="text-sm text-red-600 dark:text-red-400"><p>{{ error }}</p><a v-if="uncertain" :href="dashboardUrl" class="inline-flex min-h-11 items-center underline">{{ copy.refreshDashboard }}</a></div>
        </div>
    </ReaderDialog>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import ReaderDialog from '@/components/ReaderDialog.vue';
import type { DashboardOnboarding } from '@/dashboard-types';
import { useReaderCopy } from '@/reader-copy';

const copy = useReaderCopy();
const { onboarding, csrfToken } = defineProps<{ onboarding: DashboardOnboarding; logUrl: string; dashboardUrl: string; csrfToken: string }>();
const emit = defineEmits<{ reminded: [] }>();
const busy = ref(false);
const uncertain = ref(false);
const error = ref('');
let mounted = true;

async function remind() {
    if (busy.value || uncertain.value) return;
    busy.value = true;
    error.value = '';
    try {
        const response = await fetch(onboarding.remindUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken } }).catch(() => { throw new Error(copy.value.onboardingReminder.uncertain); });
        if (response.redirected || [401, 419].includes(response.status)) throw new Error(copy.value.onboardingReminder.expired);
        if (response.status !== 204) throw new Error(copy.value.onboardingReminder.uncertain);
        if (mounted) emit('reminded');
    } catch (cause) {
        if (mounted) { uncertain.value = true; error.value = cause instanceof Error ? cause.message : copy.value.onboardingReminder.uncertain; }
    } finally {
        if (mounted) busy.value = false;
    }
}

onBeforeUnmount(() => { mounted = false; });
</script>
