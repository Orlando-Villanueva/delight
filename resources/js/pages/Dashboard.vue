<template>
    <Head title="Dashboard" />
    <div class="mx-auto w-full max-w-7xl space-y-4">
        <header><h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1></header>
        <p v-if="refreshing" role="status" class="text-sm text-gray-600 dark:text-gray-300">Updating your overview…</p>
        <div v-if="refreshError" role="alert" class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-xl border border-red-200 bg-white px-4 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-gray-800 dark:text-red-300"><p>{{ refreshError }}</p><button type="button" class="min-h-11 rounded-lg font-medium underline underline-offset-4 focus-visible:outline-2" @click="refresh">Retry</button><a :href="page.props.reader.urls.dashboard" class="inline-flex min-h-11 items-center rounded-lg underline focus-visible:outline-2">Refresh page</a></div>
        <p class="sr-only" role="status">{{ announcement }}</p>
        <section v-if="recap.show" aria-labelledby="dashboard-recap-title" class="rounded-xl border border-primary-200 bg-primary-50 p-5 dark:border-primary-800 dark:bg-primary-900/20">
            <h2 id="dashboard-recap-title" class="text-lg font-semibold text-gray-900 dark:text-white">Your {{ recap.year }} recap is live</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">See your reader style, top books, total chapters, and best streak. {{ recap.isFinal ? `Based on your reading through ${recap.endLabel}.` : `Updates through ${recap.endLabel} — keep reading to shape it.` }}</p>
            <a :href="recap.url" class="mt-3 inline-flex min-h-11 items-center rounded-lg text-sm font-semibold text-primary-700 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-primary-300">View recap</a>
        </section>
        <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="min-w-0 space-y-4">
                <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                    <DashboardStreak :streak="streak" :log-url="page.props.reader.urls.log" />
                    <DashboardMilestone :milestone="milestone" :badge-base-url="badgeBaseUrl" :achievements-url="page.props.reader.urls.achievements" />
                </div>
                <div class="grid items-stretch gap-4 md:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-1">
                    <section aria-labelledby="all-time-title" class="relative flex min-w-0 flex-col rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <h2 id="all-time-title" class="text-sm font-semibold text-gray-900 dark:text-white">All-time activity</h2>
                        <dl class="mt-3 grid flex-1 grid-cols-2 content-center gap-x-4 gap-y-5 sm:grid-cols-4 md:grid-cols-1 md:content-stretch md:gap-y-0 xl:grid-cols-4 xl:content-center xl:gap-y-3">
                            <div class="md:flex md:items-center md:justify-between md:gap-4 md:border-b md:border-gray-100 md:py-4 md:last:border-0 xl:block xl:border-0 xl:py-0 dark:md:border-gray-700"><dd class="text-xl font-semibold md:text-2xl xl:text-xl text-gray-900 dark:text-white">{{ summary.daysRead.toLocaleString('en') }}</dd><dt class="mt-1 text-xs text-gray-600 md:mt-0 md:text-end xl:mt-1 xl:text-start dark:text-gray-400">Days read</dt></div>
                            <div class="md:flex md:items-center md:justify-between md:gap-4 md:border-b md:border-gray-100 md:py-4 md:last:border-0 xl:block xl:border-0 xl:py-0 dark:md:border-gray-700"><dd class="text-xl font-semibold md:text-2xl xl:text-xl text-gray-900 dark:text-white">{{ summary.totalChapters.toLocaleString('en') }}</dd><dt class="mt-1 text-xs text-gray-600 md:mt-0 md:text-end xl:mt-1 xl:text-start dark:text-gray-400">Chapters logged</dt></div>
                            <div class="md:flex md:items-center md:justify-between md:gap-4 md:border-b md:border-gray-100 md:py-4 md:last:border-0 xl:block xl:border-0 xl:py-0 dark:md:border-gray-700"><dd class="text-xl font-semibold md:text-2xl xl:text-xl text-gray-900 dark:text-white">{{ summary.bookCompletions.toLocaleString('en') }}</dd><dt class="mt-1 text-xs text-gray-600 md:mt-0 md:text-end xl:mt-1 xl:text-start dark:text-gray-400">Book completions</dt></div>
                            <div class="md:flex md:items-center md:justify-between md:gap-4 md:border-b md:border-gray-100 md:py-4 md:last:border-0 xl:block xl:border-0 xl:py-0 dark:md:border-gray-700"><dd class="text-xl font-semibold md:text-2xl xl:text-xl text-gray-900 dark:text-white">{{ summary.averageChaptersPerDay.toFixed(2) }}</dd><dt :aria-describedby="averageDescriptionId" title="Chapters logged, including rereads, divided by every calendar day since your first reading." class="mt-1 text-xs text-gray-600 md:mt-0 md:text-end xl:mt-1 xl:text-start dark:text-gray-400">Chapters/day since first reading</dt></div>
                        </dl>
                        <p :id="averageDescriptionId" class="sr-only">Chapters logged, including rereads, divided by every calendar day since your first reading, including days without a reading.</p>
                    </section>
                    <DashboardCalendar :calendar="calendar" class="w-full xl:hidden" />
                </div>
                <DashboardBookProgress :progress="bookProgress" />
            </div>
            <div class="hidden min-w-0 space-y-4 xl:block">
                <DashboardCalendar :calendar="calendar" />
                <section aria-labelledby="recent-activity-title" class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-wrap items-center justify-between gap-x-3">
                        <h2 id="recent-activity-title" class="font-semibold text-gray-900 dark:text-white">Recent Activity</h2>
                        <Link :href="page.props.reader.urls.history" class="inline-flex min-h-11 items-center rounded-lg text-sm font-medium text-primary-700 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-primary-300">View History</Link>
                    </div>
                    <ul v-if="recentActivity.length" class="mt-2 space-y-3"><li v-for="reading in recentActivity" :key="reading.id" class="text-sm"><p class="font-medium text-gray-800 dark:text-gray-200">{{ reading.passageText }}</p><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ reading.timeAgo }}</p></li></ul>
                    <p v-else class="mt-2 text-sm text-gray-600 dark:text-gray-400">Your recent readings will appear here.</p>
                </section>
            </div>
        </div>
    </div>
    <DashboardOnboarding v-if="onboarding.show && !refreshing" :onboarding="onboarding" :log-url="page.props.reader.urls.log" :dashboard-url="page.props.reader.urls.dashboard" :csrf-token="page.props.reader.csrfToken" @reminded="reminded" />
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref, useId } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DashboardStreak from '@/components/DashboardStreak.vue';
import DashboardMilestone from '@/components/DashboardMilestone.vue';
import DashboardCalendar from '@/components/DashboardCalendar.vue';
import DashboardBookProgress from '@/components/DashboardBookProgress.vue';
import DashboardOnboarding from '@/components/DashboardOnboarding.vue';
import { loadDashboard } from '@/dashboard-helpers';
import type { DashboardProps } from '@/dashboard-types';
import type { ReaderPageProps } from '@/reader-types';

defineProps<DashboardProps>();
const page = usePage<ReaderPageProps>();
const averageDescriptionId = useId();
const refreshing = ref(false);
const refreshError = ref('');
const announcement = ref('');
let controller: AbortController | undefined;
let mounted = true;

const stopNavigation = router.on('navigate', event => {
    if (event.detail.type === 'history' && event.detail.page.component === 'Dashboard') void refresh();
});

async function refresh() {
    if (refreshing.value) return;
    refreshing.value = true;
    refreshError.value = '';
    controller = new AbortController();
    try {
        const data = await loadDashboard(page.props.reader.urls.dashboard, controller.signal);
        if (!mounted) return;
        router.replace<DashboardProps & ReaderPageProps>({
            props: current => mounted ? { ...current, ...data } : current,
            preserveState: true, preserveScroll: true,
            onSuccess: () => { if (mounted) announcement.value = 'Dashboard updated.'; },
        });
    } catch (error) {
        if (mounted) refreshError.value = error instanceof Error ? error.message : 'The Dashboard could not be updated. Try again.';
    } finally {
        if (mounted) refreshing.value = false;
    }
}

function reminded() {
    router.replace<DashboardProps & ReaderPageProps>({ props: current => ({ ...current, onboarding: { ...current.onboarding, show: false } }), preserveState: true, preserveScroll: true });
    announcement.value = 'Onboarding dismissed.';
}

onBeforeUnmount(() => { mounted = false; controller?.abort(); stopNavigation(); });
</script>
