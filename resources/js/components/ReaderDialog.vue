<template>
    <Teleport to="body">
        <dialog ref="dialog" :aria-labelledby="labelledby" :aria-describedby="describedby" :aria-busy="busy || undefined" class="fixed inset-0 m-0 h-dvh max-h-none w-full max-w-none items-center justify-center overflow-y-auto bg-transparent p-4 text-gray-900 backdrop:bg-gray-950/60 backdrop:backdrop-blur-sm open:flex dark:text-white" @cancel.prevent="requestClose" @click.self="requestClose">
            <slot name="overlay" />
            <div class="relative max-h-full w-full overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800" :class="size === 'lg' ? 'max-w-lg' : 'max-w-md'">
                <button ref="closeButton" type="button" :aria-label="closeLabel" :disabled="busy" class="absolute end-2 top-2 z-10 inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 dark:text-gray-300 dark:hover:bg-gray-700" @click="requestClose">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" /></svg>
                </button>
                <slot />
            </div>
        </dialog>
    </Teleport>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(defineProps<{ labelledby: string; describedby?: string; closeLabel?: string; busy?: boolean; initialFocus?: string; size?: 'md' | 'lg' }>(), { closeLabel: 'Close dialog', busy: false, size: 'md' });
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);

function requestClose() {
    if (!props.busy) emit('close');
}

onMounted(() => {
    dialog.value?.showModal();
    const initial = props.initialFocus ? dialog.value?.querySelector<HTMLElement>(props.initialFocus) : closeButton.value;
    (initial ?? closeButton.value)?.focus();
});

onBeforeUnmount(() => dialog.value?.close());
</script>
