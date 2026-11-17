<template>
    <ReadingNoteDialog v-if="isNote" v-model="note" :title="action.group.notes ? copy.editNote : copy.addNote" :description="`${action.group.passage} · ${action.dateLabel}`" :help="multiple ? copy.noteAppliesToChapters(action.group.chapterCount) : undefined" :busy="busy" :blocked="blocked" :error="error" @close="close" @submit="submit">
        <template #recovery><a v-if="blocked" :href="historyUrl" class="inline-flex min-h-11 items-center rounded-lg font-medium underline underline-offset-4 focus-visible:outline-2">{{ copy.refreshHistory }}</a></template>
    </ReadingNoteDialog>
    <ReaderDialog v-else labelledby="history-action-title" describedby="history-action-description" :close-label="copy.closeDeleteConfirmation" :busy="busy" initial-focus="[data-dialog-cancel]" @close="close">
        <header class="border-b border-gray-200 p-5 pe-16 dark:border-gray-700">
            <h2 id="history-action-title" class="text-lg font-semibold">{{ multiple ? copy.selectChaptersToDelete : copy.deleteReadingQuestion }}</h2>
            <p id="history-action-description" class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ action.group.passage }} · {{ action.dateLabel }}</p>
        </header>
        <form :aria-busy="busy" @submit.prevent="submit">
            <div class="space-y-4 p-5">
                <template v-if="multiple">
                    <div class="flex flex-wrap gap-x-4">
                        <button type="button" :disabled="busy || blocked" class="min-h-11 rounded-lg text-sm font-medium text-primary-700 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-primary-300" @click="selected = allSelected ? [] : action.group.chapters.map(chapter => chapter.id)">{{ allSelected ? copy.deselectAll : copy.selectAll }}</button>
                    </div>
                    <fieldset class="max-h-60 overflow-y-auto" :disabled="busy || blocked">
                        <legend class="sr-only">{{ copy.chaptersToDelete }}</legend>
                        <label v-for="chapter in action.group.chapters" :key="chapter.id" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <input v-model="selected" type="checkbox" :value="chapter.id" class="size-4 rounded border-gray-300 text-primary-600 focus:ring-0 focus:ring-offset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:focus-visible:outline-primary-400" />
                            <span class="text-sm">{{ copy.chapter }} {{ chapter.chapter }}</span>
                        </label>
                    </fieldset>
                    <p class="sr-only" role="status">{{ copy.selectedCount(selected.length) }}</p>
                </template>
                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ multiple ? copy.selectedChaptersRemoved : copy.readingAndNoteRemoved }} {{ copy.thisCannotBeUndone }}</p>
                <div v-if="error" id="history-action-error" role="alert" class="space-y-2 text-sm text-red-600 dark:text-red-400">
                    <p>{{ error }}</p>
                    <a v-if="blocked" :href="historyUrl" class="inline-flex min-h-11 items-center rounded-lg font-medium underline underline-offset-4 focus-visible:outline-2">{{ copy.refreshHistory }}</a>
                </div>
            </div>
            <footer class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/30 sm:flex-row sm:justify-end">
                <button data-dialog-cancel type="button" :disabled="busy" class="min-h-11 rounded-lg px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 dark:text-gray-200 dark:hover:bg-gray-700" @click="close">{{ copy.cancel }}</button>
                <button type="submit" :disabled="busy || blocked || (multiple && !selected.length)" class="min-h-11 rounded-lg bg-red-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 disabled:cursor-not-allowed disabled:opacity-50">{{ busy ? copy.deleting : (multiple ? copy.deleteSelected(selected.length) : copy.deleteReading) }}</button>
            </footer>
        </form>
    </ReaderDialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ReaderDialog from '@/components/ReaderDialog.vue';
import ReadingNoteDialog from '@/components/ReadingNoteDialog.vue';
import { mutateHistory } from '@/history-requests';
import type { HistoryAction, HistoryMutation } from '@/history-types';
import { useReaderCopy } from '@/reader-copy';

const { action, csrfToken, historyUrl } = defineProps<{ action: HistoryAction; csrfToken: string; historyUrl: string }>();
const copy = useReaderCopy();
const emit = defineEmits<{ close: []; saved: [data: HistoryMutation]; warning: [needed: boolean] }>();
const isNote = computed(() => action.kind === 'note');
const multiple = computed(() => action.group.chapterCount > 1);
const note = ref(action.group.notes ?? '');
const selected = ref<number[]>([]);
const allSelected = computed(() => selected.value.length === action.group.chapters.length);
const busy = ref(false);
const blocked = ref(false);
const error = ref('');
const dirty = computed(() => isNote.value && note.value !== (action.group.notes ?? ''));
let mounted = true;

watch([dirty, busy], () => emit('warning', dirty.value || busy.value), { immediate: true });

function close() {
    if (busy.value) return;
    if (dirty.value && !window.confirm(copy.value.discardNote)) return;
    emit('close');
}

async function submit() {
    if (busy.value || blocked.value || (!isNote.value && multiple.value && !selected.value.length)) return;
    busy.value = true;
    error.value = '';
    const ids = action.group.chapters.map(chapter => chapter.id);
    const url = isNote.value ? action.group.noteUrl : (multiple.value ? action.group.deleteChaptersUrl : action.group.deleteUrl);
    const body = isNote.value ? { notes_text: note.value, log_ids: ids } : (multiple.value ? { group_ids: ids, log_ids: [...selected.value] } : {});
    const outcome = await mutateHistory(url, isNote.value ? 'PATCH' : 'DELETE', body, csrfToken, undefined, copy.value.historyErrors);
    if (!mounted) return;
    busy.value = false;
    if (outcome.kind === 'success') emit('saved', outcome.data);
    else {
        error.value = outcome.message;
        blocked.value = ['uncertain', 'session', 'stale'].includes(outcome.kind);
    }
}

onBeforeUnmount(() => { mounted = false; emit('warning', false); });
</script>
