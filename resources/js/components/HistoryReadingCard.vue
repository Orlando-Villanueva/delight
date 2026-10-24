<template>
    <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between gap-2">
            <div class="min-w-0 flex-1">
                <h3 class="flex flex-wrap items-center gap-x-2 gap-y-1 text-base font-semibold leading-tight text-gray-900 dark:text-white">
                    <span>{{ group.passage }}</span>
                    <span v-if="group.chapterCount > 1" class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ group.chapterCount }} chapters</span>
                </h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Logged at <time :datetime="group.loggedAt">{{ group.timeLabel }}</time></p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <button type="button" :aria-label="`${group.notes ? 'Edit' : 'Add'} note for ${group.passage}`" :title="group.notes ? 'Edit note' : 'Add note'" aria-haspopup="dialog" class="inline-flex size-11 items-center justify-center rounded-lg text-gray-500 hover:bg-primary-50 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:text-gray-400 dark:hover:bg-primary-900/20 dark:hover:text-primary-300" @click="emit('action', 'note')">
                    <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14.3 4.8 2.9 2.9M7 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-4.5m2.4-9.9a2 2 0 0 1 0 2.8l-6.8 6.9L8 14l.7-3.6 6.8-6.8a2 2 0 0 1 2.9 0Z" /></svg>
                </button>
                <button type="button" :aria-label="`Delete ${group.passage} reading`" title="Delete reading" aria-haspopup="dialog" class="inline-flex size-11 items-center justify-center rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 dark:text-gray-400 dark:hover:bg-red-900/20 dark:hover:text-red-400" @click="emit('action', 'delete')">
                    <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.9 13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" /></svg>
                </button>
            </div>
        </div>
        <p v-if="group.notes" class="mt-3 whitespace-pre-wrap break-words border-t border-gray-100 pt-3 text-sm italic leading-relaxed text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ group.notes }}</p>
    </article>
</template>

<script setup lang="ts">
import type { HistoryGroup } from '@/history-types';

defineProps<{ group: HistoryGroup }>();
const emit = defineEmits<{ action: [kind: 'note' | 'delete'] }>();
</script>
