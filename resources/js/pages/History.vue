<template>
    <Head title="Reading History" />
    <section class="mx-auto flex w-full max-w-4xl flex-col gap-6" aria-labelledby="reading-history-title">
        <header>
            <h1 id="reading-history-title" ref="heading" tabindex="-1" class="text-2xl font-bold text-gray-900 focus:outline-none dark:text-white">Reading History</h1>
            <p class="mt-1 text-gray-600 dark:text-gray-400">Review and manage past readings.</p>
        </header>
        <p class="sr-only" role="status" aria-live="polite">{{ announcement }}</p>
        <ol v-if="hasLogs || days.length" class="relative ms-3 space-y-6 border-s border-gray-200 dark:border-gray-700">
            <li v-if="!days.some(day => day.date === today)" class="relative ms-6">
                <span class="absolute -start-[1.9rem] mt-1.5 size-3 rounded-full border-2 border-white bg-gray-400 dark:border-gray-900 dark:bg-gray-600" aria-hidden="true"></span>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <time :datetime="today" class="text-sm font-semibold text-gray-900 dark:text-white">{{ todayLabel }}</time>
                    <span class="text-xs text-gray-500 dark:text-gray-400">0 readings</span>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-xl border border-dashed border-gray-300 bg-white p-4 dark:border-gray-700 dark:bg-gray-800/50">
                    <p class="text-sm text-gray-600 dark:text-gray-300">No readings today</p>
                    <Link :href="page.props.reader.urls.log" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-accent-500 px-4 py-2 text-sm font-medium text-white hover:bg-accent-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-500 lg:bg-transparent lg:px-0 lg:text-primary-700 lg:underline-offset-4 lg:hover:bg-transparent lg:hover:underline lg:focus-visible:outline-primary-500 dark:lg:text-primary-300">
                        <span class="lg:hidden">Log a Reading</span>
                        <span class="hidden lg:inline">Log today’s reading <span aria-hidden="true">→</span></span>
                    </Link>
                </div>
            </li>
            <li v-for="day in days" :key="day.date" :id="`reading-day-${day.date}`" class="relative ms-6">
                <span class="absolute -start-[1.9rem] mt-1.5 size-3 rounded-full border-2 border-white bg-primary-500 dark:border-gray-900" aria-hidden="true"></span>
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <time :datetime="day.date" class="text-sm font-semibold text-gray-900 dark:text-white">{{ day.label }}</time>
                    <span v-if="day.groups.length > 1" class="text-xs text-gray-500 dark:text-gray-400">{{ day.groups.reduce((sum, group) => sum + group.chapterCount, 0) }} chapters</span>
                </div>
                <div class="space-y-3">
                    <HistoryReadingCard v-for="group in day.groups" :key="group.id" :group="group" @action="action = { kind: $event, group, date: day.date, dateLabel: day.label }" />
                </div>
            </li>
        </ol>
        <div v-else class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Your reading journey starts here</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Log your first reading and build a history of time in the Bible.</p>
            <Link :href="page.props.reader.urls.log" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-accent-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-accent-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-500">Log a Reading</Link>
        </div>
        <div v-if="nextUrl" ref="sentinel" class="py-2 text-center">
            <p v-if="loadError" role="alert" class="mb-2 text-sm text-red-600 dark:text-red-400">{{ loadError }}</p>
            <button type="button" :disabled="loading" class="min-h-11 rounded-lg px-5 py-2 text-sm font-medium text-primary-700 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-60 dark:text-primary-300" @click="loadOlder">{{ loading ? 'Loading older readings…' : (loadError ? 'Retry loading older readings' : 'Load older readings') }}</button>
        </div>
        <p v-else-if="days.length" class="py-2 text-center text-xs text-gray-500 dark:text-gray-400">You’ve reached the start of your reading history.</p>
    </section>
    <HistoryActionDialog v-if="action" :key="`${action.kind}-${action.group.id}`" :action="action" :csrf-token="page.props.reader.csrfToken" :history-url="page.props.reader.urls.history" @close="action = null; needsWarning = false" @saved="saved" @warning="needsWarning = $event" />
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import HistoryReadingCard from '@/components/HistoryReadingCard.vue';
import HistoryActionDialog from '@/components/HistoryActionDialog.vue';
import { loadHistorySlice, mergeHistoryDays } from '@/history-requests';
import { useLeaveWarning } from '@/useLeaveWarning';
import type { HistoryAction, HistoryMutation, HistoryProps } from '@/history-types';
import type { ReaderPageProps } from '@/reader-types';

const props = defineProps<HistoryProps>();
const page = usePage<ReaderPageProps>();
const action = ref<HistoryAction | null>(null);
const needsWarning = ref(false);
const loading = ref(false);
const loadError = ref('');
const announcement = ref('');
const sentinel = ref<HTMLElement | null>(null);
const heading = ref<HTMLElement | null>(null);
const days = computed(() => props.days);
const todayLabel = computed(() => {
    const [year, month, day] = props.today.split('-').map(Number);
    return new Intl.DateTimeFormat('en', { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(year!, month! - 1, day!));
});
let observer: IntersectionObserver | undefined;
let controller: AbortController | undefined;
let mounted = true;

useLeaveWarning(() => needsWarning.value, 'Discard this unfinished note? If a change is running, it may finish after you leave.');

function updateHistory(change: (current: HistoryProps) => Partial<HistoryProps>, onSuccess = () => {}) {
    router.replace<HistoryProps & ReaderPageProps>({ props: current => ({ ...current, ...change(current) }), preserveState: true, preserveScroll: true, onSuccess });
}

async function loadOlder() {
    if (loading.value || !props.nextUrl || action.value) return;
    loading.value = true;
    loadError.value = '';
    controller = new AbortController();
    try {
        const slice = await loadHistorySlice(props.nextUrl, controller.signal);
        if (!mounted) return;
        updateHistory(current => ({ days: mergeHistoryDays(current.days, slice.days), nextUrl: slice.nextUrl, hasLogs: slice.hasLogs }));
        announcement.value = `${slice.days.length} older reading days loaded.`;
    } catch (error) {
        if (mounted) loadError.value = error instanceof Error ? error.message : 'Older readings could not be loaded. Try again.';
    } finally {
        if (mounted) loading.value = false;
    }
}

function saved(data: HistoryMutation) {
    const message = action.value?.kind === 'note' ? 'Reading note saved.' : 'Reading deleted.';
    action.value = null;
    needsWarning.value = false;
    updateHistory(current => ({ days: mergeHistoryDays(current.days.filter(day => day.date !== data.date), data.day ? [data.day] : []), today: data.today, hasLogs: data.hasLogs }), () => {
        void nextTick(() => { if (document.activeElement === document.body) heading.value?.focus(); });
    });
    announcement.value = message;
}

function observeSentinel() {
    observer?.disconnect();
    if (!sentinel.value) return;
    observer = new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting) && !loadError.value) void loadOlder();
    }, { root: sentinel.value.closest('main'), rootMargin: '100px' });
    observer.observe(sentinel.value);
}

watch(sentinel, observeSentinel);

onMounted(observeSentinel);

onBeforeUnmount(() => { mounted = false; controller?.abort(); observer?.disconnect(); });
</script>
