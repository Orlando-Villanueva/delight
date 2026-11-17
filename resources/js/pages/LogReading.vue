<template>
    <Head><title>{{ copy.logReading }}</title></Head>
    <section class="mx-auto w-full max-w-md" aria-labelledby="log-reading-title">
        <header class="mb-6">
            <h1 id="log-reading-title" class="text-2xl font-bold text-gray-900 dark:text-white">{{ copy.logReading }}</h1>
            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ copy.recordToday }}</p>
        </header>
        <ReadingLogForm :key="page.props.reader.user.id" :config="config" :session="session" @leave-warning="needsWarning = $event" />
    </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import ReadingLogForm from '@/components/ReadingLogForm.vue';
import { readingDraftForReader } from '@/reading-drafts';
import type { ReaderPageProps } from '@/reader-types';
import { useLeaveWarning } from '@/useLeaveWarning';
import type { ReadingFormConfig } from '@/components/reading-log-types';
import { useReaderCopy } from '@/reader-copy';

const copy = useReaderCopy();
const props = defineProps<{ config: ReadingFormConfig }>();
const page = usePage<ReaderPageProps>();
const reader = computed(() => page.props.reader);
const config = computed(() => props.config);
const session = readingDraftForReader(reader, config);
const needsWarning = ref(false);

useLeaveWarning(() => needsWarning.value, () => copy.value.discardReading, () => Boolean(session.value.pending) || session.value.uncertain);
</script>
