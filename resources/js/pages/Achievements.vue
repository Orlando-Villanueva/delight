<template>
    <Head title="Achievements" />
    <div class="mx-auto w-full max-w-7xl space-y-8">
        <header>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Achievements</h1>
            <p class="mt-1 text-gray-600 dark:text-gray-400">Permanent milestones from your Bible reading journey.</p>
        </header>
        <section v-if="latestAchievement" aria-labelledby="latest-achievement-title" class="flex items-center gap-3 rounded-xl border border-primary-200 bg-primary-50/50 px-4 py-3 dark:border-primary-800 dark:bg-primary-900/20">
            <AchievementBadge :icon="latestAchievement.icon" :base-url="badgeBaseUrl" />
            <div class="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <div class="min-w-0">
                    <h2 id="latest-achievement-title" class="text-xs font-semibold text-primary-700 dark:text-primary-300">Latest achievement</h2>
                    <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ latestAchievement.name }}</p>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-400">Awarded <time :datetime="latestAchievement.earnedOn">{{ formatDate(latestAchievement.earnedOn) }}</time></p>
            </div>
        </section>
        <section v-if="nextGoals.books.length || nextGoals.progress.length" aria-labelledby="next-goals-title" class="space-y-4">
            <div>
                <h2 id="next-goals-title" class="text-lg font-semibold text-gray-900 dark:text-white">Next goals</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">The closest milestones in your reading journey.</p>
            </div>
            <div v-if="nextGoals.books.length" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Closest book completions</h3>
                    <a :href="`${page.props.reader.urls.dashboard}#book-progress`" class="inline-flex min-h-11 items-center rounded-lg text-sm font-medium text-primary-700 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-primary-300">View all book progress</a>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <article v-for="goal in nextGoals.books" :key="goal.key" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start gap-3">
                            <AchievementBadge :icon="goal.icon" :base-url="badgeBaseUrl" locked />
                            <div class="min-w-0 flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ goal.name }}</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    <template v-if="goal.completedCount">Completion {{ goal.completedCount + 1 }} · </template>{{ goal.chaptersRead }} of {{ goal.totalChapters }} chapters<template v-if="goal.missingChapters.length"> · Remaining: {{ goal.missingChapters.join(', ') }}</template>
                                </p>
                            </div>
                        </div>
                        <div role="progressbar" :aria-label="`${goal.name} completion ${goal.completedCount + 1}`" :aria-valuenow="goal.chaptersRead" :aria-valuemax="goal.totalChapters" aria-valuemin="0" class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-primary-600 dark:bg-primary-400" :style="{ width: `${goal.progressPercent}%` }"></div>
                        </div>
                    </article>
                </div>
            </div>
            <div v-if="nextGoals.progress.length" class="space-y-3">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Milestones in progress</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <article v-for="goal in nextGoals.progress" :key="goal.key" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start gap-3">
                            <AchievementBadge :icon="goal.icon" :base-url="badgeBaseUrl" locked />
                            <div class="min-w-0 flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ goal.kind === 'bible_progress' ? `${goal.target}% Bible milestone` : goal.name }}</h4>
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
                <a v-if="category.key === 'books' && !nextGoals.books.length" :href="`${page.props.reader.urls.dashboard}#book-progress`" class="inline-flex min-h-11 items-center rounded-lg text-sm font-medium text-primary-700 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-primary-300">View all book progress</a>
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
                            <p v-if="achievement.reading" class="mt-1 text-sm text-primary-700 dark:text-primary-300">{{ achievement.reading.passage }} · Read on {{ formatDate(achievement.reading.date) }}</p>
                            <p v-if="showDescription(achievement)" class="mt-1 text-sm leading-5 text-gray-600 dark:text-gray-300">{{ achievement.description }}</p>
                            <p v-if="!achievement.reading" class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ achievementDate(achievement) }}</p>
                            <button v-if="hasHistory(achievement)" type="button" :aria-label="`View ${achievement.count} completions for ${achievement.name}`" :title="`${achievement.count} completions — view history`" aria-haspopup="dialog" class="absolute inset-0 cursor-pointer rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500" @click="openHistory(achievement)"></button>
                        </div>
                    </div>
                </article>
            </div>
        </section>
        <section v-if="!categories.length" class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">No trophies yet</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Your first achievement appears here after you log a reading.</p>
        </section>
    </div>
    <Teleport to="body">
        <dialog ref="historyDialog" aria-labelledby="completion-history-title" class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl border border-gray-200 bg-white p-0 text-gray-900 shadow-xl backdrop:bg-gray-950/60 backdrop:backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" @close="selected = null" @click="closeOnBackdrop">
            <template v-if="selected">
                <header class="flex items-start justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700">
                    <div>
                        <h2 id="completion-history-title" class="text-lg font-semibold">{{ selected.name }} history</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ selected.count }} completions</p>
                    </div>
                    <button type="button" autofocus :aria-label="`Close ${selected.name} history`" class="-mr-2 -mt-2 inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-300 dark:hover:bg-gray-700" @click="historyDialog?.close()">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" /></svg>
                    </button>
                </header>
                <ol class="divide-y divide-gray-100 px-5 dark:divide-gray-700">
                    <li v-for="(completion, index) in selected.completions" :key="index" class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-4 text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Completion {{ index + 1 }}</span>
                        <span><span v-if="!completion.completedOn" class="text-gray-500 dark:text-gray-400">Awarded </span><time :datetime="completion.completedOn ?? completion.earnedOn">{{ formatDate(completion.completedOn ?? completion.earnedOn) }}</time></span>
                    </li>
                </ol>
            </template>
        </dialog>
    </Teleport>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AchievementBadge from '@/components/AchievementBadge.vue';
import type { AchievementProgressGoal, AchievementsProps, EarnedAchievement } from '@/achievement-types';
import type { ReaderPageProps } from '@/reader-types';

defineProps<AchievementsProps>();

const page = usePage<ReaderPageProps>();
const selected = ref<EarnedAchievement | null>(null);
const historyDialog = ref<HTMLDialogElement | null>(null);
const dateFormat = new Intl.DateTimeFormat('en', { year: 'numeric', month: 'long', day: 'numeric' });

function hasHistory(achievement: EarnedAchievement) {
    return achievement.count > 1 && achievement.completions.length > 0;
}

async function openHistory(achievement: EarnedAchievement) {
    selected.value = achievement;
    await nextTick();
    historyDialog.value?.showModal();
}

function closeOnBackdrop(event: MouseEvent) {
    if (event.target !== historyDialog.value || !historyDialog.value) return;
    const bounds = historyDialog.value.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
        historyDialog.value.close();
    }
}

function formatDate(date: string) {
    const [year, month, day] = date.split('-').map(Number);
    return dateFormat.format(new Date(year!, month! - 1, day!));
}

function progressLabel(goal: AchievementProgressGoal) {
    if (goal.kind === 'bible_progress') return `${goal.current}% read · Goal ${goal.target}%`;
    if (goal.kind === 'longest_streak') return `Best streak: ${goal.current} of ${goal.target} days`;
    return `${goal.current} reading days · Goal ${goal.target}`;
}

function showDescription(achievement: EarnedAchievement) {
    return !['book_completed', 'testament_completed', 'bible_completed'].includes(achievement.key);
}

function achievementDate(achievement: EarnedAchievement) {
    const latest = achievement.completions.at(-1);
    const label = latest?.completedOn
        ? (achievement.count > 1 ? 'Latest completion:' : 'Completed on')
        : (achievement.count > 1 ? 'Latest award:' : 'Earned');
    return `${label} ${formatDate(latest?.completedOn ?? latest?.earnedOn ?? achievement.earnedOn)}`;
}

onBeforeUnmount(() => historyDialog.value?.close());
</script>
