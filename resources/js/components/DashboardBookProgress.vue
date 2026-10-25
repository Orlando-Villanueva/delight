<template>
    <section id="book-progress" aria-labelledby="book-progress-title" class="min-w-0 scroll-mt-4 rounded-xl border border-gray-200 bg-white p-4 sm:p-5 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="book-progress-title" class="text-lg font-semibold text-gray-900 dark:text-white">Bible Reading Progress</h2>
            <fieldset class="relative w-full sm:w-auto">
                <legend class="sr-only">Bible section</legend>
                <div class="grid gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 sm:flex sm:gap-0 sm:rounded-none sm:border-0 sm:border-b sm:bg-transparent sm:p-0 dark:border-gray-600 dark:bg-gray-900 dark:sm:bg-transparent" :class="sections.length === 3 ? 'grid-cols-1' : 'grid-cols-2'">
                    <label v-for="section in sections" :key="section.key" class="min-w-0 cursor-pointer">
                        <input v-model="active" type="radio" name="dashboard-bible-section" :value="section.key" class="peer sr-only" />
                        <span class="flex min-h-11 items-center justify-center rounded border border-transparent px-2 py-2 sm:rounded-none sm:border-0 sm:border-b-2 sm:px-3 sm:py-1 text-center text-sm font-medium text-gray-700 peer-checked:border-primary-500 peer-checked:bg-white peer-checked:text-primary-700 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-500 dark:text-gray-300 dark:peer-checked:border-primary-400 dark:peer-checked:bg-gray-700 dark:peer-checked:text-primary-300 sm:peer-checked:bg-transparent dark:sm:peer-checked:bg-transparent">{{ section.label }}</span>
                    </label>
                </div>
            </fieldset>
        </div>
        <div class="mt-4">
            <div class="flex items-center justify-between gap-3 text-sm"><p class="font-medium text-gray-700 dark:text-gray-200">{{ overallLabel }}</p><p class="font-semibold text-gray-900 dark:text-white">{{ overallPercent.toFixed(1) }}%</p></div>
            <div role="progressbar" :aria-label="overallLabel" :aria-valuenow="overallPercent" :aria-valuetext="`${overallPercent.toFixed(1)} percent`" aria-valuemin="0" aria-valuemax="100" class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700"><div class="h-full rounded-full bg-primary-500 dark:bg-primary-400" :style="{ width: `${overallPercent}%` }"></div></div>
        </div>
        <div role="group" aria-label="Book status counts" class="my-4 flex flex-wrap gap-x-4 gap-y-2 text-xs text-gray-600 dark:text-gray-400">
            <span class="inline-flex items-center gap-2"><span aria-hidden="true" class="size-2 rounded-full bg-success-500"></span>{{ current.completed_books }} completed</span>
            <span class="inline-flex items-center gap-2"><span aria-hidden="true" class="size-2 rounded-full bg-primary-500"></span>{{ current.in_progress_books }} in progress</span>
            <span class="inline-flex items-center gap-2"><span aria-hidden="true" class="size-2 rounded-full bg-gray-400"></span>{{ current.not_started_books }} not started</span>
        </div>
        <div class="@container/book-grid">
            <ul class="book-completion-grid grid grid-cols-1 overflow-hidden rounded-lg border border-gray-200 @min-[18rem]/book-grid:grid-cols-2 @min-[27rem]/book-grid:grid-cols-3 @min-[36rem]/book-grid:grid-cols-4 @min-[45rem]/book-grid:grid-cols-5 @min-[54rem]/book-grid:grid-cols-6 dark:border-gray-700">
                <li v-for="book in current.processed_books" :key="book.book_id" :aria-label="bookDetails(book)" :title="bookDetails(book)" class="flex min-h-21 flex-col justify-center gap-1 border-b border-e border-gray-200 p-2 text-center dark:border-gray-700" :class="book.status === 'completed' ? 'bg-green-50 text-green-900 dark:bg-green-950 dark:text-green-100' : book.status === 'in-progress' ? 'bg-blue-50 text-blue-900 dark:bg-blue-950 dark:text-blue-100' : 'text-gray-700 dark:text-gray-300'">
                    <div class="flex min-h-8 flex-wrap items-center justify-center gap-2"><span class="text-sm font-semibold leading-tight">{{ book.name }}</span><span v-if="book.completed_count > 0" aria-hidden="true" class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full border border-gray-300 bg-white px-1 text-[10px] font-bold text-gray-800 dark:border-gray-500 dark:bg-gray-900 dark:text-gray-100">{{ book.completed_count > 1 ? `×${book.completed_count}` : book.completed_count }}</span></div>
                    <p class="text-sm">{{ book.completed_count > 0 ? `${book.next_completion_chapters}/${book.chapter_count} to next` : `${book.chapters_read}/${book.chapter_count} chapters` }}</p>
                    <div aria-hidden="true" class="h-1 overflow-hidden rounded-full" :class="book.status === 'completed' ? 'bg-green-200 dark:bg-green-800' : book.status === 'in-progress' ? 'bg-blue-200 dark:bg-blue-800' : 'bg-gray-200 dark:bg-gray-700'"><div class="h-full" :class="book.status === 'completed' ? 'bg-green-600 dark:bg-green-300' : 'bg-blue-600 dark:bg-blue-300'" :style="{ width: `${book.completed_count > 0 ? book.next_completion_progress_percent : book.percentage}%` }"></div></div>
                </li>
            </ul>
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed, toRef, unref, watch } from 'vue';
import { useRemember } from '@inertiajs/vue3';
import { bookDetails, ordinal } from '@/dashboard-helpers';
import type { BibleProgress } from '@/dashboard-types';

type Section = 'old_testament' | 'new_testament' | 'deuterocanonical';
const { progress } = defineProps<{ progress: BibleProgress }>();
const selection = unref(useRemember<{ active: Section }>({ active: 'old_testament' }, 'Dashboard:bible-section'));
const active = toRef(selection, 'active');
const sections = computed<{ key: Section; label: string }[]>(() => [
    { key: 'old_testament', label: 'Old Testament' },
    { key: 'new_testament', label: 'New Testament' },
    ...(progress.deuterocanonical ? [{ key: 'deuterocanonical' as const, label: 'Deuterocanonical' }] : []),
]);
const current = computed(() => progress[active.value] ?? progress.old_testament);
const overallLabel = computed(() => progress.bible_completions > 0 ? `${ordinal(progress.bible_completions + 1)} Bible read` : 'First Bible read');
const overallPercent = computed(() => progress.bible_completions > 0 ? progress.next_completion_progress_percent : progress.first_coverage_percent);

watch(() => progress.deuterocanonical, () => {
    if (active.value === 'deuterocanonical' && !progress.deuterocanonical) active.value = 'old_testament';
}, { immediate: true });
</script>
