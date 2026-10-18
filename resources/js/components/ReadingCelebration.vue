<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="reading-celebration-title" class="fixed inset-0 m-0 h-dvh max-h-none w-full max-w-none items-center justify-center overflow-y-auto bg-transparent p-4 text-gray-950 backdrop:bg-gray-950/70 backdrop:backdrop-blur-sm open:flex dark:text-white" @cancel.prevent="emit('close')" @click.self="emit('close')">
            <canvas ref="confettiCanvas" aria-hidden="true" class="pointer-events-none fixed inset-0 z-20 h-full w-full"></canvas>
            <div class="relative max-h-full w-full max-w-lg overflow-y-auto rounded-xl bg-white shadow-xl dark:bg-gray-800">
                <button ref="closeButton" type="button" aria-label="Close celebration" class="absolute end-2 top-2 z-10 inline-flex size-11 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-white" @click="emit('close')">
                    <svg class="size-3" aria-hidden="true" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" /></svg>
                </button>
                <div class="px-6 pb-6 pt-8 sm:px-8">
                    <div class="mb-5 flex justify-center"><AchievementBadge :icon="heroIcon" :base-url="badgeBaseUrl" size="xl" /></div>
                    <div class="text-center">
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">{{ eyebrow }}</p>
                        <h2 id="reading-celebration-title" class="mt-2 text-2xl font-bold leading-tight text-balance sm:text-3xl">{{ title }}</h2>
                        <p v-if="heroDescription" class="mx-auto mt-3 max-w-sm text-sm leading-6 text-gray-700 dark:text-gray-300">{{ heroDescription }}</p>
                        <p v-if="payload.reading.passage && payload.reading.date" class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ payload.reading.passage }} recorded for {{ payload.reading.date }}.</p>
                    </div>
                </div>
                <div v-if="payload.earned.length > 1 || (payload.record && payload.earned.length) || payload.progress.length" class="space-y-5 px-6 pb-6 sm:px-8">
                    <article v-for="achievement in additionalAchievements" :key="achievement.id" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/30">
                        <div class="flex gap-3">
                            <AchievementBadge :icon="achievement.icon" :base-url="badgeBaseUrl" class="mt-0.5" />
                            <div class="min-w-0">
                                <h3 class="font-semibold">{{ achievement.display_name }}</h3>
                                <p v-if="achievementDescription(achievement)" class="mt-1 text-sm leading-5 text-gray-700 dark:text-gray-300">{{ achievementDescription(achievement) }}</p>
                            </div>
                        </div>
                    </article>
                    <article v-if="payload.record && payload.earned.length" class="rounded-lg border border-primary-200/70 bg-primary-50/50 p-4 dark:border-primary-400/20 dark:bg-primary-900/20">
                        <div class="flex gap-3">
                            <span class="mt-0.5 inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4ZM5 5H3v2a4 4 0 0 0 4 4M19 5h2v2a4 4 0 0 1-4 4" /></svg>
                            </span>
                            <div class="min-w-0">
                                <p v-if="payload.earned.length" class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">{{ payload.record.eyebrow }}</p>
                                <h3 class="font-semibold">{{ payload.record.title }}</h3>
                                <p class="mt-1 text-sm leading-5 text-gray-700 dark:text-gray-300">{{ payload.record.description }}</p>
                            </div>
                        </div>
                    </article>
                    <section v-if="payload.progress.length" class="border-t border-gray-200 pt-5 dark:border-gray-700" aria-labelledby="reading-next-up-title">
                        <h3 id="reading-next-up-title" class="text-sm font-semibold">Next up</h3>
                        <div class="mt-4 space-y-5">
                            <div v-for="next in payload.progress" :key="next.display_name" class="flex items-center gap-3">
                                <AchievementBadge :icon="next.icon" :base-url="badgeBaseUrl" size="xs" locked />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <p class="min-w-0 font-medium leading-5 text-gray-800 dark:text-gray-100">{{ next.display_name }}</p>
                                        <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ next.current }}{{ next.unit === 'percent' ? '%' : '' }} / {{ next.target }}{{ next.unit === 'percent' ? '%' : '' }}</p>
                                    </div>
                                    <div role="progressbar" :aria-label="next.display_name" :aria-valuenow="next.current" :aria-valuemax="next.target" aria-valuemin="0" class="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700/80">
                                        <div class="h-full rounded-full bg-primary-600 transition-[width] duration-500 ease-in-out motion-reduce:transition-none dark:bg-primary-400" :style="{ width: progressReady ? `${next.progress_percent}%` : '0%' }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                <div class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-900/30 sm:flex-row-reverse sm:px-8">
                    <button type="button" class="min-h-11 w-full rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:bg-primary-500 dark:hover:bg-primary-600 sm:w-auto" @click="emit('close')">Keep reading</button>
                    <a v-if="payload.earned.length" :href="achievementsUrl" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-200 dark:hover:bg-gray-700 sm:w-auto">View trophy shelf</a>
                </div>
            </div>
        </dialog>
    </Teleport>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AchievementBadge from '@/components/AchievementBadge.vue';
import type { CelebrationAchievement, ReadingCelebration } from '@/components/reading-log-types';

const { payload } = defineProps<{ payload: ReadingCelebration; achievementsUrl: string; badgeBaseUrl: string }>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);
const confettiCanvas = ref<HTMLCanvasElement | null>(null);
const progressReady = ref(false);
let confetti: ReadingConfetti | undefined;
let progressTimer: ReturnType<typeof setTimeout> | undefined;

const single = computed(() => payload.earned.length === 1 ? payload.earned[0] : undefined);
const additionalAchievements = computed(() => payload.earned.length > 1 ? payload.earned : []);
const heroDescription = computed(() => single.value
    ? achievementDescription(single.value)
    : (!payload.earned.length ? payload.record?.description : ''));
const heroIcon = computed(() => single.value?.icon ?? payload.record?.icon ?? 'trophy');
const eyebrow = computed(() => payload.earned.length ? (single.value ? 'Achievement unlocked' : 'Achievements unlocked') : (payload.record?.eyebrow ?? 'Personal best'));
const title = computed(() => payload.earned.length ? (single.value ? single.value.display_name : `${payload.earned.length} achievements unlocked`) : payload.record?.title);

function achievementDescription(achievement: CelebrationAchievement) {
    return achievement.icon === 'book-open' && !achievement.is_repeat_book_completion ? '' : achievement.description;
}

onMounted(() => {
    dialog.value?.showModal();
    closeButton.value?.focus();
    progressTimer = setTimeout(() => { progressReady.value = true; }, 120);
    if (confettiCanvas.value && window.confetti) {
        confetti = window.confetti.create(confettiCanvas.value, { resize: true });
        confetti({ particleCount: 180, spread: 90, origin: { y: 0.35 }, disableForReducedMotion: true });
    }
});

onBeforeUnmount(() => {
    clearTimeout(progressTimer);
    confetti?.reset();
    dialog.value?.close();
});
</script>
