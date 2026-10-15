import { createApp } from 'vue';
import ReadingLogForm from './ReadingLogForm.vue';
import { installReadingLogIslands } from './reading-log-lifecycle.js';

export function initReadingLogIslands() {
    installReadingLogIslands(createApp, ReadingLogForm, { document, window, history, location });
}
