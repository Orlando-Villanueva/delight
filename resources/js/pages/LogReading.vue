<template>
    <Head title="Log Reading" />
    <section class="mx-auto w-full max-w-md" aria-labelledby="log-reading-title">
        <header class="mb-6">
            <h1 id="log-reading-title" class="text-2xl font-bold text-gray-900 dark:text-white">Log Reading</h1>
            <p class="mt-1 text-gray-600 dark:text-gray-400">Record today's Bible reading.</p>
        </header>
        <ReadingLogForm :config="config" :session="session" @leave-warning="needsWarning = $event" />
    </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ReadingLogForm from '@/components/ReadingLogForm.vue';
import { createReadingLogSession } from '@/components/reading-log-session';
import { useLeaveWarning } from '@/useLeaveWarning';
import type { ReadingFormConfig } from '@/components/reading-log-types';

const { config } = defineProps<{ config: ReadingFormConfig }>();
const session = createReadingLogSession({ values: { ...config.values }, collection: 'old', notesOpen: false });
const needsWarning = ref(false);

useLeaveWarning(() => needsWarning.value, 'Discard this unfinished reading? If a save is still running, it may finish after you leave.');
</script>
