<template>
    <Head title="Reading Plans" />
    <section class="mx-auto w-full max-w-3xl space-y-6" aria-labelledby="plans-title" :aria-busy="submitting || undefined">
        <header>
            <h1 id="plans-title" tabindex="-1" class="text-2xl font-bold text-gray-900 dark:text-white">Reading Plans</h1>
        </header>
        <p v-if="startDayError" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200">
            {{ startDayError }}
        </p>
        <p
            v-else-if="notice"
            role="status"
            class="rounded-xl border px-4 py-3 text-sm"
            :class="
                flash.error
                    ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200'
                    : 'border-gray-200 bg-white text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200'
            "
        >
            {{ notice }}
        </p>
        <div v-if="plans.length" class="space-y-6">
            <article v-for="plan in plans" :key="plan.slug" class="rounded-xl border border-gray-200 bg-white px-6 pt-5 pb-3 dark:border-gray-700 dark:bg-gray-800">
                <div v-if="plan.subscription" class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1 space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ plan.name }}</h2>
                            <span v-if="!plan.subscription.active" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Paused</span>
                        </div>
                        <div>
                            <div class="flex flex-col items-start gap-1 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                                <span class="text-gray-600 dark:text-gray-400">Day {{ plan.subscription.dayNumber }} of {{ plan.subscription.lastDay }}</span>
                                <span class="font-medium text-primary-700 dark:text-primary-300">{{ plan.subscription.completedDays }} of {{ plan.subscription.trackedDays }} tracked days complete</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" :aria-label="`${plan.name} progress`" :aria-valuenow="Math.round(plan.subscription.progress)" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-primary-600 dark:bg-primary-400" :style="{ width: `${plan.subscription.progress}%` }"></div>
                            </div>
                        </div>
                    </div>
                    <Link
                        :href="plan.subscription.todayUrl"
                        class="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-lg px-4 text-sm font-medium focus-visible:outline-2 focus-visible:outline-primary-500 sm:w-auto"
                        :class="plan.subscription.active ? 'bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600' : 'border border-gray-300 text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700'"
                    >
                        {{ plan.subscription.active ? 'Continue Reading' : 'View Progress' }}
                    </Link>
                </div>
                <div v-else class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ plan.name }}</h2>
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ plan.daysCount }} days</span>
                        </div>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">{{ plan.description }}</p>
                        <button
                            v-if="plan.canStart"
                            type="button"
                            class="-mt-1 inline-flex min-h-11 items-end pb-1 text-sm font-medium text-primary-700 hover:text-primary-600 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-primary-300 dark:hover:text-primary-200"
                            @click="openStart(plan, $event)"
                        >
                            Start from a different passage
                        </button>
                        <p v-else class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">No readings available yet.</p>
                    </div>
                    <div v-if="plan.canStart && plan.subscribeUrl" class="w-full shrink-0 sm:w-auto">
                        <button type="button" :disabled="submitting" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-wait disabled:opacity-60 sm:w-auto dark:bg-primary-500 dark:hover:bg-primary-600" @click="beginFirstDay(plan, $event)">
                            Start from Day {{ plan.firstDay }}
                        </button>
                    </div>
                    <button v-else type="button" disabled class="inline-flex min-h-11 w-full cursor-not-allowed items-center justify-center rounded-lg bg-gray-100 px-4 text-sm font-medium text-gray-500 sm:w-auto dark:bg-gray-700 dark:text-gray-400">Coming soon</button>
                </div>
            </article>
        </div>
        <div v-else class="rounded-xl border border-gray-200 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">No reading plans available</h2>
            <p class="mt-2 text-gray-500 dark:text-gray-400">Check back soon for new reading plans.</p>
        </div>
    </section>
    <TransientNotice :message="success" dismiss-label="Dismiss notification" @dismiss="dismissSuccess" />
    <ReaderDialog v-if="selectedPlan" labelledby="plan-start-title" describedby="plan-start-desc" close-label="Close starting passage dialog" initial-focus="#plan-start-day" :busy="submitting" @close="closeStart">
        <header class="border-b border-gray-200 px-5 py-4 pe-16 dark:border-gray-700">
            <h2 id="plan-start-title" class="text-lg font-semibold">Start from a different passage</h2>
            <p id="plan-start-desc" class="mt-1 text-sm text-gray-600 dark:text-gray-400">Choose where tracking should begin for {{ selectedPlan.name }}.</p>
        </header>
        <form class="space-y-4 p-5" @submit.prevent="beginSelectedPlan">
            <div>
                <label for="plan-start-day" class="block text-sm font-medium text-gray-900 dark:text-white">Starting passage</label>
                <select id="plan-start-day" v-model.number="selectedDay" name="start_day" class="mt-2 block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option v-for="day in selectedPlan.days" :key="day.day" :value="day.day">{{ day.optionLabel }}</option>
                </select>
            </div>
            <p v-if="showEarlierDaysNote" class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:border-blue-900/40 dark:bg-blue-950/40 dark:text-blue-100">Earlier days won't count toward your progress.</p>
            <p v-if="hasActivePlan" class="text-sm text-gray-700 dark:text-gray-300">Starting this plan will pause your current active plan.</p>
            <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:justify-end dark:border-gray-700">
                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-900 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary-500 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700" @click="closeStart">Cancel</button>
                <button type="submit" :disabled="submitting || selectedDay <= 0" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary-600 px-5 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-primary-500 dark:hover:bg-primary-600">
                    Start tracking from Day {{ selectedDay }}
                </button>
            </div>
        </form>
    </ReaderDialog>
    <ReaderDialog v-if="confirmPlan" labelledby="plan-pause-title" describedby="plan-pause-desc" close-label="Close pause confirmation" :busy="submitting" @close="cancelConfirmation">
        <header class="border-b border-gray-200 px-5 py-4 pe-16 dark:border-gray-700">
            <h2 id="plan-pause-title" class="text-lg font-semibold">Pause your current plan?</h2>
            <p id="plan-pause-desc" class="mt-1 text-sm text-gray-600 dark:text-gray-400">Starting this plan will pause your current active plan.</p>
        </header>
        <div class="flex flex-col-reverse gap-3 p-5 sm:flex-row sm:justify-end">
            <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-900 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary-500 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700" @click="cancelConfirmation">Cancel</button>
            <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary-600 px-5 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-60 dark:bg-primary-500 dark:hover:bg-primary-600" :disabled="submitting" @click="confirmStart">Continue</button>
        </div>
    </ReaderDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ReaderDialog from '@/components/ReaderDialog.vue';
import TransientNotice from '@/components/TransientNotice.vue';
import type { PlanCatalogueItem, PlansPageProps } from '@/plans-types';
import type { ReaderPageProps } from '@/reader-types';

defineOptions({ inheritAttrs: false });

const props = defineProps<PlansPageProps>();

const page = usePage<ReaderPageProps & { errors: Record<string, string> }>();
const selectedSlug = ref<string | null>(null);
const selectedDay = ref(0);
const confirmPlan = ref<PlanCatalogueItem | null>(null);
const returnFocus = ref<HTMLElement | null>(null);
const submitting = ref(false);
const success = ref(props.flash.success ?? '');

const selectedPlan = computed(() => props.plans.find((plan) => plan.slug === selectedSlug.value) ?? null);
const showEarlierDaysNote = computed(() => selectedPlan.value !== null && selectedDay.value > selectedPlan.value.firstDay);
const startDayError = computed(() => page.props.errors.start_day ?? '');
const notice = computed(() => props.flash.error || props.flash.info || '');

onMounted(() => {
    if (!success.value) return;
    router.replace<ReaderPageProps & PlansPageProps>({
        props: current => ({ ...current, flash: { ...current.flash, success: null } }),
        preserveState: true,
        preserveScroll: true,
    });
});

function dismissSuccess(restoreFocus: boolean) {
    success.value = '';
    if (restoreFocus) nextTick(() => document.getElementById('plans-title')?.focus());
}

function rememberFocus(target: EventTarget | null) {
    returnFocus.value = target instanceof HTMLElement ? target : null;
}

function restoreFocus() {
    const target = returnFocus.value;
    returnFocus.value = null;
    nextTick(() => target?.focus());
}

function openStart(plan: PlanCatalogueItem, event: MouseEvent) {
    rememberFocus(event.currentTarget);
    selectedDay.value = plan.firstDay;
    selectedSlug.value = plan.slug;
}

function closeStart() {
    if (submitting.value) return;
    selectedSlug.value = null;
    restoreFocus();
}

function beginFirstDay(plan: PlanCatalogueItem, event: MouseEvent) {
    if (submitting.value || !plan.subscribeUrl) return;
    if (!props.hasActivePlan) {
        submitPlan(plan, plan.firstDay);
        return;
    }
    rememberFocus(event.currentTarget);
    confirmPlan.value = plan;
}

function cancelConfirmation() {
    if (submitting.value) return;
    confirmPlan.value = null;
    restoreFocus();
}

function confirmStart() {
    if (confirmPlan.value) submitPlan(confirmPlan.value, confirmPlan.value.firstDay);
}

function beginSelectedPlan() {
    if (selectedPlan.value) submitPlan(selectedPlan.value, selectedDay.value);
}

function submitPlan(plan: PlanCatalogueItem, startDay: number) {
    if (!plan.subscribeUrl || submitting.value) return;
    submitting.value = true;
    router.post(plan.subscribeUrl, { start_day: startDay }, {
        onFinish: () => {
            submitting.value = false;
            selectedSlug.value = null;
            confirmPlan.value = null;
        },
    });
}
</script>
