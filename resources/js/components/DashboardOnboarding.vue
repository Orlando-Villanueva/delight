<template>
    <ReaderDialog labelledby="onboarding-title" :dismissible="false" :busy="busy" initial-focus="[data-first-reading]" size="lg">
        <div class="space-y-5 p-6 sm:p-8">
            <header class="text-center"><h2 id="onboarding-title" class="text-2xl font-bold">Welcome to Delight!</h2><p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">Building a consistent Bible reading habit starts with one chapter.</p></header>
            <ol class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-200"><li>1. Read a chapter from your Bible.</li><li>2. Log what you read here in the app.</li><li>3. Watch your progress and streak grow!</li></ol>
            <div class="space-y-2">
                <Link data-first-reading :href="logUrl" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-accent-500 px-5 py-3 text-sm font-semibold text-white hover:bg-accent-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-500">Log Your First Reading</Link>
                <a :href="onboarding.plansUrl" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-gray-100 px-5 py-3 text-sm font-medium text-gray-700 hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-primary-500 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Start with a reading plan</a>
                <button v-if="onboarding.canRemind" type="button" :disabled="busy || uncertain" class="min-h-11 w-full rounded-lg text-sm font-medium text-gray-600 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 dark:text-gray-300" @click="remind">{{ busy ? 'Scheduling reminder…' : 'Remind me tomorrow' }}</button>
            </div>
            <div v-if="error" role="alert" class="text-sm text-red-600 dark:text-red-400"><p>{{ error }}</p><a v-if="uncertain" :href="dashboardUrl" class="inline-flex min-h-11 items-center underline">Refresh Dashboard</a></div>
        </div>
    </ReaderDialog>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import ReaderDialog from '@/components/ReaderDialog.vue';
import type { DashboardOnboarding } from '@/dashboard-types';

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
        const response = await fetch(onboarding.remindUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken } });
        if (response.redirected || [401, 419].includes(response.status)) throw new Error('Your session has expired. Refresh the Dashboard to sign in again.');
        if (response.status !== 204) throw new Error('We could not confirm your reminder. Refresh the Dashboard before trying again.');
        if (mounted) emit('reminded');
    } catch (cause) {
        if (mounted) { uncertain.value = true; error.value = cause instanceof Error ? cause.message : 'We could not confirm your reminder. Refresh the Dashboard before trying again.'; }
    } finally {
        if (mounted) busy.value = false;
    }
}

onBeforeUnmount(() => { mounted = false; });
</script>
