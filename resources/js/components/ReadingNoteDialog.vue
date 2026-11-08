<template>
    <ReaderDialog :labelledby="`${id}-title`" :describedby="description ? `${id}-description` : undefined" close-label="Close note editor" :busy="busy" initial-focus="textarea" editor :expanded="expanded" @close="emit('close')">
        <header class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-200 p-5 pe-16 dark:border-gray-700">
            <div class="min-w-0">
                <h2 :id="`${id}-title`" class="text-lg font-semibold">{{ title }}</h2>
                <p v-if="description" :id="`${id}-description`" class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ description }}</p>
            </div>
            <button type="button" :disabled="busy" :aria-expanded="expanded" class="hidden min-h-11 shrink-0 items-center rounded-lg px-3 text-sm font-medium text-primary-700 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 sm:inline-flex dark:text-primary-300 dark:hover:bg-gray-700" @click="expanded = !expanded">{{ expanded ? 'Reduce' : 'Expand' }}</button>
        </header>
        <form class="flex min-h-0 flex-1 flex-col" :aria-busy="busy" @submit.prevent="emit('submit')">
            <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-5">
                <div class="flex shrink-0 items-center justify-between gap-3 text-sm">
                    <label :for="`${id}-text`" class="font-medium">{{ label }}</label>
                    <span class="text-xs text-gray-500 tabular-nums dark:text-gray-400">{{ model.length }}/1000</span>
                </div>
                <textarea :id="`${id}-text`" v-model="model" :disabled="busy || blocked" rows="5" maxlength="1000" :placeholder="placeholder" :aria-invalid="error ? true : undefined" :aria-describedby="[help ? `${id}-help` : '', error ? `${id}-error` : ''].filter(Boolean).join(' ') || undefined" class="min-h-24 w-full flex-1 resize-none rounded-lg border border-gray-300 bg-gray-50 p-3 text-base text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none disabled:opacity-70 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"></textarea>
                <p v-if="help" :id="`${id}-help`" class="shrink-0 text-xs text-gray-600 dark:text-gray-400">{{ help }}</p>
                <div v-if="error" :id="`${id}-error`" role="alert" class="shrink-0 space-y-2 text-sm text-red-600 dark:text-red-400">
                    <p>{{ error }}</p>
                    <slot name="recovery" />
                </div>
            </div>
            <footer class="flex shrink-0 justify-end gap-2 border-t border-gray-200 bg-gray-50 p-5 pb-[max(1.25rem,env(safe-area-inset-bottom,0px))] dark:border-gray-700 dark:bg-gray-900/30">
                <button v-if="!draft" type="button" :disabled="busy" class="min-h-11 rounded-lg px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 dark:text-gray-200 dark:hover:bg-gray-700" @click="emit('close')">Cancel</button>
                <button type="submit" :disabled="busy || blocked" class="min-h-11 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-primary-500">{{ draft ? 'Done' : (busy ? 'Saving…' : 'Save note') }}</button>
            </footer>
        </form>
    </ReaderDialog>
</template>

<script setup lang="ts">
import { ref, useId } from 'vue';
import ReaderDialog from '@/components/ReaderDialog.vue';

withDefaults(defineProps<{ title: string; description?: string; label?: string; placeholder?: string; help?: string; error?: string; busy?: boolean; blocked?: boolean; draft?: boolean }>(), { label: 'Reading note', placeholder: 'Add reflections, insights, or questions…', busy: false, blocked: false, draft: false });
const model = defineModel<string>({ required: true });
const emit = defineEmits<{ close: []; submit: [] }>();
const id = useId();
const expanded = ref(false);
</script>
