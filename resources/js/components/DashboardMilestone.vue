<template>
    <section aria-labelledby="next-milestone-title" class="flex h-full flex-col rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-start justify-between gap-3 px-5 pt-5">
            <div>
                <h2 id="next-milestone-title" class="text-lg font-semibold text-gray-900 dark:text-white">{{ copy.nextMilestone }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ goal?.displayName ?? (milestone.latest ? copy.latestTrophy : copy.firstReadingMilestone) }}</p>
            </div>
            <AchievementBadge :icon="goal?.icon ?? milestone.latest?.icon ?? 'sparkles'" :base-url="badgeBaseUrl" :locked="Boolean(goal)" />
        </div>
        <div class="flex-1 space-y-3 px-5 py-5">
            <template v-if="goal">
                <p class="text-xl font-semibold text-gray-900 dark:text-white">{{ milestoneProgress(goal, page.props.reader.locale) }}</p>
                <div role="progressbar" :aria-label="goal.displayName" :aria-valuenow="goal.current" :aria-valuemax="goal.target" :aria-valuetext="milestoneProgress(goal, page.props.reader.locale)" aria-valuemin="0" class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-primary-500 dark:bg-primary-400" :style="{ width: `${goal.progressPercent}%` }"></div>
                </div>
                <p v-if="goal.key === 'first_reading'" class="text-sm text-gray-600 dark:text-gray-300">{{ copy.logFirstReading }}</p>
            </template>
            <template v-else>
                <p class="text-xl font-semibold text-gray-900 dark:text-white">{{ milestone.latest?.displayName ?? copy.emptyHistoryTitle }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ milestone.latest?.description ?? copy.logFirstReading }}</p>
            </template>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-t border-gray-200 px-5 py-2 dark:border-gray-700">
            <p class="min-w-0 py-2 text-sm text-gray-600 dark:text-gray-300"><template v-if="milestone.latest">{{ copy.latestTrophy }}: <span class="font-medium">{{ milestone.latest.displayName }}</span></template><template v-else>{{ copy.buildShelf }}</template></p>
            <Link :href="achievementsUrl" class="inline-flex min-h-11 shrink-0 items-center rounded-lg text-sm font-medium text-primary-700 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-primary-300">{{ copy.viewShelf }}</Link>
        </footer>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AchievementBadge from '@/components/AchievementBadge.vue';
import { milestoneProgress } from '@/dashboard-helpers';
import type { DashboardMilestone } from '@/dashboard-types';
import { useReaderCopy } from '@/reader-copy';
import { usePage } from '@inertiajs/vue3';
import type { ReaderPageProps } from '@/reader-types';

const { milestone } = defineProps<{ milestone: DashboardMilestone; badgeBaseUrl: string; achievementsUrl: string }>();
const copy = useReaderCopy();
const page = usePage<ReaderPageProps>();
const goal = computed(() => milestone.milestone);
</script>
