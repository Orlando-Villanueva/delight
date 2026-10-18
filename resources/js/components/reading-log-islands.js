import { createApp } from 'vue';
import ReadingLogForm from '@/components/ReadingLogForm.vue';
import { installReadingLogIslands } from '@/components/reading-log-lifecycle.js';

export function initReadingLogIslands() {
    installReadingLogIslands(createApp, ReadingLogForm, { document, window, history, location });
}
