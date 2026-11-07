<template>
    <Head><title>{{ state.plan.name }} · Day {{ state.dayNumber }}</title></Head>
    <div class="mx-auto w-full max-w-2xl space-y-4 sm:space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 sm:p-6" aria-labelledby="plan-title">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 id="plan-title" tabindex="-1" class="text-lg font-semibold text-gray-900 dark:text-white">{{ state.plan.name }}</h1>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Day {{ state.dayNumber }} of {{ state.totalDays }}<span v-if="state.subscription.startDay > 1"> · tracking from Day {{ state.subscription.startDay }}</span> · {{ state.completedDaysCount }} of {{ state.trackedDaysCount }} tracked days complete</p>
                    <p v-if="state.currentDay !== state.dayNumber" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Current day: {{ state.currentDay }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ Math.round(state.progress) }}%</p>
                    <p v-if="state.isComplete" class="text-xs font-medium text-green-700 dark:text-green-400">Tracking complete</p>
                </div>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" :aria-label="`${state.plan.name} progress`" :aria-valuenow="Math.round(state.progress)" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-primary-600 transition-[width] dark:bg-primary-400" :style="{ width: `${state.progress}%` }" />
            </div>
            <nav class="mt-4 flex flex-wrap items-center justify-between gap-2" aria-label="Plan day navigation">
                <div class="flex gap-2">
                    <Link v-if="state.previousDay !== null" :href="dayUrl(state.previousDay)" class="inline-flex min-h-11 items-center rounded-lg bg-gray-100 px-3 text-sm font-medium text-gray-800 hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-primary-500 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600"><span class="sm:hidden">Previous</span><span class="hidden sm:inline">Previous Day</span></Link>
                    <span v-else class="inline-flex min-h-11 items-center rounded-lg bg-gray-100 px-3 text-sm text-gray-400 dark:bg-gray-700 dark:text-gray-500"><span class="sm:hidden">Previous</span><span class="hidden sm:inline">Previous Day</span></span>
                    <Link v-if="state.nextDay !== null" :href="dayUrl(state.nextDay)" class="inline-flex min-h-11 items-center rounded-lg bg-gray-100 px-3 text-sm font-medium text-gray-800 hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-primary-500 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600"><span class="sm:hidden">Next</span><span class="hidden sm:inline">Next Day</span></Link>
                    <span v-else class="inline-flex min-h-11 items-center rounded-lg bg-gray-100 px-3 text-sm text-gray-400 dark:bg-gray-700 dark:text-gray-500"><span class="sm:hidden">Next</span><span class="hidden sm:inline">Next Day</span></span>
                </div>
                <Link v-if="state.currentDay !== state.dayNumber" :href="dayUrl(state.currentDay)" class="inline-flex min-h-11 items-center text-sm font-medium text-primary-700 hover:text-primary-600 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-primary-300">Go to Current Day</Link>
            </nav>
        </section>

        <section v-if="state.reading" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800" aria-labelledby="reading-title">
            <div class="p-4 sm:p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 id="reading-title" class="text-lg font-semibold text-gray-900 dark:text-white">Day {{ state.dayNumber }}<span class="hidden sm:inline"> Reading</span></h2>
                    <button v-if="canLog && !state.reading.allCompleted" type="button" :disabled="Boolean(pending)" class="inline-flex min-h-11 items-center gap-1 rounded-lg bg-accent-500 px-3 text-sm font-medium text-white hover:bg-accent-600 focus-visible:outline-2 focus-visible:outline-accent-500 disabled:cursor-wait disabled:opacity-60" @click="mutate('logAll')"><span v-if="pending === 'logAll'">Saving…</span><template v-else><span class="sm:hidden">Complete day</span><span class="hidden sm:inline">Mark day complete</span></template></button>
                    <span v-else-if="state.reading.allCompleted" class="inline-flex min-h-11 items-center gap-1 rounded-lg bg-green-100 px-3 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">All Complete!</span>
                </div>
                <p v-if="state.isBeforeTracking" class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-700/40 dark:text-gray-200"><strong>Before tracking.</strong> This day is before where you started tracking this plan.</p>
                <div v-if="!state.isActive" class="mb-4 flex flex-col items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50 sm:flex-row">
                    <p class="text-sm text-gray-700 dark:text-gray-300"><strong>Plan paused.</strong> Resume to continue logging.</p>
                    <button type="button" :disabled="Boolean(pending)" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-60 sm:w-auto" @click="resume">Resume Plan</button>
                </div>
                <div v-if="canLog && state.unlinkedTodayChaptersCount > 0" class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:border-blue-900/40 dark:bg-blue-950/30 dark:text-blue-100">
                    <p>Found {{ state.unlinkedTodayChaptersCount }} of {{ state.unlinkedTodayChaptersTotal }} chapters from today.</p>
                    <button type="button" class="inline-flex min-h-11 items-center font-medium text-primary-700 dark:text-primary-300" :disabled="Boolean(pending)" @click="mutate('applyReadings')">Apply to this day</button>
                </div>
                <ol class="space-y-2">
                    <li v-for="chapter in state.reading.chapters" :key="`${chapter.bookId}-${chapter.chapter}`" class="min-h-14 rounded-lg" :class="chapter.completed ? 'bg-green-50 dark:bg-green-900/20' : 'bg-gray-50 dark:bg-gray-700/50'">
                        <component :is="canLog && !chapter.completed ? 'button' : 'span'" :type="canLog && !chapter.completed ? 'button' : undefined" :disabled="canLog && !chapter.completed ? Boolean(pending) : undefined" :aria-label="canLog && !chapter.completed ? `Mark ${chapter.bookName} ${chapter.chapter} read` : undefined" class="flex min-h-14 w-full min-w-0 items-center gap-3 rounded-lg px-3 py-2 text-start font-medium" :class="[chapter.completed ? 'text-green-800 dark:text-green-300' : 'text-gray-900 dark:text-white', canLog && !chapter.completed ? 'hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-wait disabled:opacity-60 dark:hover:bg-primary-900/20' : '']" @click="canLog && !chapter.completed && mutate('logChapter', chapter)">
                            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full" :class="chapter.completed ? 'bg-green-500 text-white' : (pendingChapter === `${chapter.bookId}-${chapter.chapter}` ? 'border-2 border-primary-200 border-t-primary-600 motion-safe:animate-spin dark:border-primary-900 dark:border-t-primary-300' : 'border-2 border-gray-300 dark:border-gray-600')" aria-hidden="true"><svg v-if="chapter.completed" class="size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg></span>
                            <span><span v-if="chapter.completed" class="sr-only">Completed: </span>{{ chapter.bookName }} {{ chapter.chapter }}</span>
                            <span v-if="pendingChapter === `${chapter.bookId}-${chapter.chapter}`" role="status" class="sr-only">Saving…</span>
                        </component>
                    </li>
                </ol>
                <p class="mt-4 border-t border-gray-200 pt-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">{{ state.reading.completedCount }} of {{ state.reading.totalCount }} chapters completed</p>
            </div>
        </section>

        <section class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:justify-between sm:p-5">
            <Link :href="state.urls.catalogue" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">All plans</Link>
            <button type="button" :disabled="Boolean(pending)" class="inline-flex min-h-11 items-center justify-center rounded-lg px-4 text-sm font-medium text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30" @click="unsubscribe">Leave plan</button>
        </section>

        <div v-if="errorMessage" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-100" role="alert">
            <p>{{ errorMessage }}</p>
            <button v-if="uncertain" type="button" class="inline-flex min-h-11 items-center rounded-lg px-3 font-semibold underline" @click="refreshState">Refresh plan state</button>
        </div>

        <ReaderDialog v-if="confirmation === 'resume'" labelledby="resume-plan-title" describedby="resume-plan-description" close-label="Close resume confirmation" :busy="pending === 'resume'" @close="confirmation = null">
            <header class="border-b border-gray-200 px-5 py-4 pe-16 dark:border-gray-700"><h2 id="resume-plan-title" class="text-lg font-semibold">Resume this plan?</h2><p id="resume-plan-description" class="mt-1 text-sm text-gray-600 dark:text-gray-400">This will pause your other active plan.</p></header>
            <div class="flex flex-col-reverse gap-3 p-5 sm:flex-row sm:justify-end"><button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm" @click="confirmation = null">Cancel</button><button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary-600 px-5 text-sm font-medium text-white" :disabled="pending === 'resume'" @click="confirmResume">Resume Plan</button></div>
        </ReaderDialog>
        <ReaderDialog v-if="confirmation === 'leave'" labelledby="leave-plan-title" describedby="leave-plan-description" close-label="Close leave plan confirmation" :busy="pending === 'leave'" @close="confirmation = null">
            <header class="border-b border-gray-200 px-5 py-4 pe-16 dark:border-gray-700"><h2 id="leave-plan-title" class="text-lg font-semibold">Leave this plan?</h2><p id="leave-plan-description" class="mt-1 text-sm text-gray-600 dark:text-gray-400">Your plan progress will be removed. Reading history will remain.</p></header>
            <div class="flex flex-col-reverse gap-3 p-5 sm:flex-row sm:justify-end"><button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm" @click="confirmation = null">Keep plan</button><button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-5 text-sm font-medium text-white" :disabled="pending === 'leave'" @click="confirmLeave">Leave plan</button></div>
        </ReaderDialog>
        <ReadingCelebration v-if="celebration" :payload="celebration" :achievements-url="props.achievementsUrl" :badge-base-url="props.achievementBadgeBaseUrl" @close="celebration = null" />
        <TransientNotice :message="notice" :suspended="Boolean(celebration)" dismiss-label="Dismiss notification" @dismiss="dismissNotice" />
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ReaderDialog from '@/components/ReaderDialog.vue';
import ReadingCelebration from '@/components/ReadingCelebration.vue';
import TransientNotice from '@/components/TransientNotice.vue';
import type { ReadingCelebration as CelebrationPayload } from '@/components/reading-log-types';
import { mutatePlanReading } from '@/plan-today-requests';
import type { PlanTodayChapter, PlanTodayPageProps, PlanTodayState } from '@/plan-today-types';
import type { ReaderPageProps } from '@/reader-types';

defineOptions({ inheritAttrs: false });

const props = defineProps<PlanTodayPageProps>();
const state = ref<PlanTodayState>(props);
const pending = ref<'logChapter' | 'logAll' | 'applyReadings' | 'resume' | 'leave' | null>(null);
const pendingChapter = ref<string | null>(null);
const uncertain = ref(false);
const errorMessage = ref('');
const notice = ref(props.flash.success ?? props.flash.info ?? props.flash.error ?? '');
const celebration = ref<CelebrationPayload | null>(null);
const confirmation = ref<'resume' | 'leave' | null>(null);
let pageGeneration = 0;
let mounted = true;
const canLog = computed(() => state.value.isActive && !state.value.isBeforeTracking && !uncertain.value);

watch(() => props, (value) => {
    pageGeneration += 1;
    state.value = value;
}, { deep: true });

onBeforeUnmount(() => { mounted = false; pageGeneration += 1; });

onMounted(() => {
    if (notice.value) persistState(props);
});

function persistState(today: PlanTodayState) {
    router.replace<PlanTodayPageProps & ReaderPageProps>({
        props: current => mounted ? { ...current, ...today, flash: { success: null, info: null, error: null } } : current,
        preserveState: true,
        preserveScroll: true,
    });
}

function dismissNotice(restoreFocus: boolean) {
    notice.value = '';
    if (restoreFocus) nextTick(() => document.getElementById('plan-title')?.focus());
}

function dayUrl(day: number) {
    const url = new URL(state.value.urls.today, window.location.origin);
    url.searchParams.set('day', String(day));
    return `${url.pathname}${url.search}`;
}

async function mutate(action: 'logChapter' | 'logAll' | 'applyReadings', chapter?: PlanTodayChapter) {
    if (pending.value || uncertain.value) return;
    const generation = pageGeneration;
    const planDay = `${state.value.plan.slug}:${state.value.dayNumber}`;
    pending.value = action;
    pendingChapter.value = action === 'logChapter' && chapter ? `${chapter.bookId}-${chapter.chapter}` : null;
    errorMessage.value = '';
    notice.value = '';
    const body: Record<string, number> = { day: state.value.dayNumber };
    if (action === 'logChapter' && chapter) {
        body.book_id = chapter.bookId;
        body.chapter = chapter.chapter;
    }
    try {
        const outcome = await mutatePlanReading(state.value.urls[action], body, document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '');
        if (generation !== pageGeneration || planDay !== `${state.value.plan.slug}:${state.value.dayNumber}`) return;
        if (outcome.kind !== 'success') {
            uncertain.value = outcome.kind === 'refresh';
            errorMessage.value = outcome.message;
            return;
        }
        const payload = outcome.data;
        state.value = payload.today;
        persistState(payload.today);
        celebration.value = payload.celebration;
        notice.value = payload.celebration ? '' : 'Plan reading updated.';
    } catch (error) {
        if (generation !== pageGeneration) return;
        uncertain.value = true;
        errorMessage.value = error instanceof Error && error.message !== 'Failed to fetch' ? `${error.message} Refresh the plan state before trying another action.` : 'We could not confirm whether the reading was saved. Refresh the plan state before trying another action.';
    } finally {
        pending.value = null;
        pendingChapter.value = null;
    }
}

function refreshState() {
    router.reload({ only: ['reading', 'dayNumber', 'currentDay', 'progress', 'isComplete', 'isActive', 'isBeforeTracking', 'hasOtherActivePlan', 'completedDaysCount', 'trackedDaysCount', 'unlinkedTodayChaptersCount', 'unlinkedTodayChaptersTotal', 'previousDay', 'nextDay'], onSuccess: () => { uncertain.value = false; errorMessage.value = ''; } });
}

function resume() {
    if (state.value.hasOtherActivePlan) confirmation.value = 'resume';
    else confirmResume();
}

function confirmResume() {
    confirmation.value = null;
    pending.value = 'resume';
    router.post(state.value.urls.activate, {}, { onFinish: () => { pending.value = null; } });
}

function unsubscribe() { confirmation.value = 'leave'; }

function confirmLeave() {
    confirmation.value = null;
    pending.value = 'leave';
    router.delete(state.value.urls.unsubscribe, { onFinish: () => { pending.value = null; } });
}
</script>
