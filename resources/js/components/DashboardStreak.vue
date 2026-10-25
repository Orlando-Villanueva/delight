<template>
    <section aria-labelledby="daily-streak-title" class="flex h-full flex-col rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-start justify-between gap-3 px-5 pt-5">
            <div>
                <h2 id="daily-streak-title" class="text-lg font-semibold text-gray-900 dark:text-white">Daily Streak</h2>
            </div>
            <span v-if="streak.presentation.classes.showIcon" aria-hidden="true" class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-accent-500 dark:bg-gray-700 dark:text-accent-300">
                <svg class="size-5" fill="currentColor" viewBox="0 0 384 512"><path d="M216 23.86c0-23.8-30.65-32.77-44.15-13.04C48 191.85 224 200 224 288c0 35.63-29.11 64.46-64.85 63.99-35.17-.45-63.15-29.77-63.15-64.94v-85.51c0-21.7-26.47-32.4-41.6-16.9C21.22 216.4 0 268.2 0 320c0 105.87 86.13 192 192 192s192-86.13 192-192c0-170.29-168-193.17-168-296.14z" /></svg>
            </span>
        </div>
        <div class="flex-1 px-5 py-4">
            <div class="flex flex-wrap items-center gap-3">
                <p class="flex items-center gap-2 text-gray-900 dark:text-white"><strong class="text-4xl font-semibold">{{ streak.currentStreak }}</strong><span class="text-sm text-gray-600 dark:text-gray-300">{{ streak.currentStreak === 1 ? 'day' : 'days' }} in a row</span></p>
                <span v-if="streak.longestStreak > 0" class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ streak.presentation.recordJustBroken ? 'New best:' : 'Best:' }} {{ streak.longestStreak }}</span>
            </div>
            <figure v-if="series.length" class="mt-4">
                <div class="relative h-24 pl-7">
                    <div aria-hidden="true" class="absolute inset-y-0 start-0 flex w-5 flex-col justify-between pb-2 text-end text-[11px] leading-none text-gray-500 dark:text-gray-400"><span>{{ chart.max }}</span><span>{{ chart.max / 2 }}</span><span>0</span></div>
                    <svg :aria-describedby="chartId" role="img" aria-label="Chapters logged over the last 14 days" viewBox="20 0 264 54" preserveAspectRatio="none" class="h-full w-full overflow-visible">
                        <path d="M20 4V48H280" fill="none" vector-effect="non-scaling-stroke" class="stroke-gray-300 dark:stroke-gray-600" />
                        <path d="M20 26H280" fill="none" vector-effect="non-scaling-stroke" stroke-dasharray="3 4" class="stroke-gray-200 dark:stroke-gray-700" />
                        <path v-for="(_, index) in series" :key="index" :d="`M${20 + index / Math.max(1, series.length - 1) * 260} 48v2`" fill="none" vector-effect="non-scaling-stroke" class="stroke-gray-300 dark:stroke-gray-600" />
                        <polyline :points="chart.points" fill="none" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" :class="streak.presentation.recordStatus === 'record' ? 'stroke-accent-500 dark:stroke-accent-300' : 'stroke-primary-500 dark:stroke-primary-400'" />
                    </svg>
                </div>
                <div aria-hidden="true" class="ms-7 mt-1 relative h-4 text-[11px] text-gray-500 dark:text-gray-400"><span class="absolute start-0">{{ dateLabel(series[0]!.date) }}</span><span v-if="series.length > 2" class="absolute -translate-x-1/2" :style="{ left: `${middleIndex / (series.length - 1) * (260 / 264) * 100}%` }">{{ dateLabel(series[middleIndex]!.date) }}</span><span class="absolute end-0">{{ dateLabel(series[series.length - 1]!.date) }}</span></div>
                <figcaption :id="chartId" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Chapters logged · last 14 days
                    <span class="sr-only">{{ series.map(point => `${point.date}: ${point.count} ${point.count === 1 ? 'chapter' : 'chapters'}`).join('; ') }}</span>
                </figcaption>
            </figure>
        </div>
        <footer v-if="streak.presentation.message || streak.presentation.label || streak.presentation.showCta" class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-t border-gray-200 px-5 py-2 dark:border-gray-700">
            <p class="py-2 text-sm" :class="streak.presentation.tone === 'danger' ? 'text-accent-700 dark:text-accent-300' : 'text-gray-700 dark:text-gray-200'"><span v-if="streak.presentation.label" class="font-medium">{{ streak.presentation.label }} </span>{{ streak.presentation.message }}</p>
            <Link v-if="streak.presentation.showCta" :href="logUrl" class="inline-flex min-h-11 items-center rounded-lg text-sm font-medium text-accent-700 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-accent-500 dark:text-accent-300">Log today</Link>
        </footer>
    </section>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue';
import { Link } from '@inertiajs/vue3';
import { readingChart } from '@/dashboard-helpers';
import type { DashboardStreak } from '@/dashboard-types';

const { streak } = defineProps<{ streak: DashboardStreak; logUrl: string }>();
const chartId = useId();
const series = computed(() => streak.activity14DayCounts);
const chart = computed(() => readingChart(series.value));
const middleIndex = computed(() => Math.floor((series.value.length - 1) / 2));
const dateFormat = new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric' });

function dateLabel(date: string) {
    return dateFormat.format(new Date(`${date}T12:00:00`));
}
</script>
