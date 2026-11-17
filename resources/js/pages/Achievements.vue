<template>
    <Head><title>{{ copy.achievements }}</title></Head>
    <div class="mx-auto w-full max-w-7xl space-y-8">
        <header>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ copy.achievements }}</h1>
            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ copy.achievementDescription }}</p>
        </header>
        <section v-if="latestAchievement" aria-labelledby="latest-achievement-title" class="flex items-center gap-3 rounded-xl border border-primary-200 bg-primary-50/50 px-4 py-3 dark:border-primary-800 dark:bg-primary-900/20">
            <AchievementBadge :icon="latestAchievement.icon" :base-url="badgeBaseUrl" />
            <div class="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <div class="min-w-0">
                        <h2 id="latest-achievement-title" class="text-xs font-semibold text-primary-700 dark:text-primary-300">{{ copy.latestAchievement }}</h2>
                    <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ latestAchievement.name }}</p>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-400">{{ copy.awarded }} <time :datetime="latestAchievement.earnedOn">{{ formatDate(latestAchievement.earnedOn) }}</time></p>
            </div>
        </section>
        <section v-if="nextGoals.books.length || nextGoals.progress.length" aria-labelledby="next-goals-title" class="space-y-4">
            <div>
                    <h2 id="next-goals-title" class="text-lg font-semibold text-gray-900 dark:text-white">{{ copy.nextGoals }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ copy.closestMilestones }}</p>
            </div>
            <div v-if="nextGoals.books.length" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ copy.closestBookCompletions }}</h3>
                    <a :href="`${page.props.reader.urls.dashboard}#book-progress`" class="inline-flex min-h-11 items-center rounded-lg text-sm font-medium text-primary-700 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-primary-300">{{ copy.viewBookProgress }}</a>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <article v-for="goal in nextGoals.books" :key="goal.key" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start gap-3">
                            <AchievementBadge :icon="goal.icon" :base-url="badgeBaseUrl" locked />
                            <div class="min-w-0 flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ goal.name }}</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    <template v-if="goal.completedCount">{{ copy.completion }} {{ goal.completedCount + 1 }} · </template>{{ copy.chaptersCompleted(goal.chaptersRead, goal.totalChapters) }}<template v-if="goal.missingChapters.length"> · {{ copy.remaining }} {{ goal.missingChapters.join(', ') }}</template>
                                </p>
                            </div>
                        </div>
                        <div role="progressbar" :aria-label="copy.achievementProgress(goal.name, goal.completedCount + 1)" :aria-valuenow="goal.chaptersRead" :aria-valuemax="goal.totalChapters" aria-valuemin="0" class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-primary-600 dark:bg-primary-400" :style="{ width: `${goal.progressPercent}%` }"></div>
                        </div>
                    </article>
                </div>
            </div>
            <div v-if="nextGoals.progress.length" class="space-y-3">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ copy.milestonesInProgress }}</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <article v-for="goal in nextGoals.progress" :key="goal.key" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start gap-3">
                            <AchievementBadge :icon="goal.icon" :base-url="badgeBaseUrl" locked />
                            <div class="min-w-0 flex-1">
                            <h4 class="font-semibold text-gray-900 dark:text-white">{{ goal.kind === 'bible_progress' ? copy.bibleMilestone(goal.target) : goal.name }}</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ progressLabel(goal) }}</p>
                            </div>
                        </div>
                        <div role="progressbar" :aria-label="goal.name" :aria-valuenow="goal.current" :aria-valuemax="goal.target" :aria-valuetext="progressLabel(goal)" aria-valuemin="0" class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-primary-600 dark:bg-primary-400" :style="{ width: `${goal.progressPercent}%` }"></div>
                        </div>
                    </article>
                </div>
            </div>
        </section>
        <section v-for="category in categories" :key="category.key" :aria-labelledby="`category-${category.key}`" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <h2 :id="`category-${category.key}`" class="text-lg font-semibold text-gray-900 dark:text-white">{{ category.label }}</h2>
                <a v-if="category.key === 'books' && !nextGoals.books.length" :href="`${page.props.reader.urls.dashboard}#book-progress`" class="inline-flex min-h-11 items-center rounded-lg text-sm font-medium text-primary-700 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-primary-300">{{ copy.viewBookProgress }}</a>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="achievement in category.achievements" :key="achievement.id" class="relative rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800" :class="hasHistory(achievement) ? 'hover:border-primary-300 dark:hover:border-primary-500' : ''">
                    <div class="flex items-start gap-3">
                        <AchievementBadge :icon="achievement.icon" :base-url="badgeBaseUrl" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ achievement.name }}</h3>
                                <span v-if="hasHistory(achievement)" aria-hidden="true" class="shrink-0 rounded-full bg-primary-50 px-2 text-xs font-semibold leading-6 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">{{ achievement.count }}×</span>
                            </div>
                            <p v-if="achievement.reading" class="mt-1 text-sm text-primary-700 dark:text-primary-300">{{ achievement.reading.passage }} · {{ copy.readOn }} {{ formatDate(achievement.reading.date) }}</p>
                            <p v-if="showDescription(achievement)" class="mt-1 text-sm leading-5 text-gray-600 dark:text-gray-300">{{ achievement.description }}</p>
                            <p v-if="!achievement.reading" class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ achievementDate(achievement) }}</p>
                            <button v-if="hasHistory(achievement)" type="button" :aria-label="copy.viewCompletions(achievement.count, achievement.name)" :title="copy.completionsHistory(achievement.count)" aria-haspopup="dialog" class="absolute inset-0 cursor-pointer rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500" @click="openHistory(achievement)"></button>
                        </div>
                    </div>
                </article>
            </div>
        </section>
        <section v-if="!categories.length" class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ copy.noTrophies }}</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ copy.firstAchievementHint }}</p>
        </section>
    </div>
    <ReaderDialog v-if="selected" labelledby="completion-history-title" :close-label="copy.closeDialog" @close="selected = null">
        <header class="border-b border-gray-200 p-5 pe-16 dark:border-gray-700">
            <h2 id="completion-history-title" class="text-lg font-semibold">{{ selected.name }} · {{ copy.history }}</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ copy.completionsCount(selected.count) }}</p>
        </header>
        <ol class="divide-y divide-gray-100 px-5 dark:divide-gray-700">
            <li v-for="(completion, index) in selected.completions" :key="index" class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-4 text-sm">
                <span class="text-gray-500 dark:text-gray-400">{{ copy.completion }} {{ index + 1 }}</span>
                <span><span v-if="!completion.completedOn" class="text-gray-500 dark:text-gray-400">{{ copy.awarded }} </span><time :datetime="completion.completedOn ?? completion.earnedOn">{{ formatDate(completion.completedOn ?? completion.earnedOn) }}</time></span>
            </li>
        </ol>
    </ReaderDialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AchievementBadge from '@/components/AchievementBadge.vue';
import ReaderDialog from '@/components/ReaderDialog.vue';
import type { AchievementProgressGoal, AchievementsProps, EarnedAchievement } from '@/achievement-types';
import type { ReaderPageProps } from '@/reader-types';
import { useReaderCopy } from '@/reader-copy';

defineProps<AchievementsProps>();

const page = usePage<ReaderPageProps>();
const copy = useReaderCopy();
const selected = ref<EarnedAchievement | null>(null);
const dateFormat = computed(() => new Intl.DateTimeFormat(page.props.reader.locale === 'fr' ? 'fr-CA' : 'en', { year: 'numeric', month: 'long', day: 'numeric' }));

function hasHistory(achievement: EarnedAchievement) {
    return achievement.count > 1 && achievement.completions.length > 0;
}

function openHistory(achievement: EarnedAchievement) {
    selected.value = achievement;
}

function formatDate(date: string) {
    const [year, month, day] = date.split('-').map(Number);
    return dateFormat.value.format(new Date(year!, month! - 1, day!));
}

function progressLabel(goal: AchievementProgressGoal) {
    if (goal.kind === 'bible_progress') return copy.value.progressPercent(goal.current, goal.target);
    if (goal.kind === 'longest_streak') return copy.value.bestStreakLabel(goal.current, goal.target);
    return copy.value.readingDaysLabel(goal.current, goal.target);
}

function showDescription(achievement: EarnedAchievement) {
    return !['book_completed', 'testament_completed', 'bible_completed'].includes(achievement.key);
}

function achievementDate(achievement: EarnedAchievement) {
    const latest = achievement.completions.at(-1);
    let label: string = achievement.count > 1 ? copy.value.latestAward : copy.value.earned;
    if (latest?.completedOn) label = achievement.count > 1 ? copy.value.latestCompletion : copy.value.completedOn;
    return `${label} ${formatDate(latest?.completedOn ?? latest?.earnedOn ?? achievement.earnedOn)}`;
}

</script>
