<template>
    <Teleport to="body">
        <dialog ref="dialog" :aria-labelledby="labelledby" :aria-describedby="describedby" :aria-busy="busy || undefined" :style="editor ? viewportStyle : undefined" class="fixed inset-0 m-0 h-dvh max-h-none w-full max-w-none items-center justify-center overflow-y-auto bg-transparent text-gray-900 backdrop:bg-gray-950/60 backdrop:backdrop-blur-sm open:flex dark:text-white" :class="editor ? (expanded ? 'p-0' : 'p-0 sm:p-4') : 'p-4'" @cancel.prevent="requestClose" @click.self="requestClose">
            <slot name="overlay" />
            <div class="relative max-h-full w-full bg-white shadow-xl dark:bg-gray-800" :class="editor ? ['flex flex-col overflow-hidden pt-[env(safe-area-inset-top,0px)]', expanded ? 'h-full' : 'h-full sm:h-[38rem] sm:max-w-lg sm:rounded-xl sm:border sm:border-gray-200 dark:sm:border-gray-700'] : ['overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-700', size === 'lg' ? 'max-w-lg' : 'max-w-md']">
                <button v-if="dismissible" ref="closeButton" type="button" :aria-label="closeLabel" :disabled="busy" class="absolute end-2 z-10 inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 dark:text-gray-300 dark:hover:bg-gray-700" :class="editor ? 'top-[calc(0.5rem+env(safe-area-inset-top,0px))]' : 'top-2'" @click="requestClose">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" /></svg>
                </button>
                <slot />
            </div>
        </dialog>
    </Teleport>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(defineProps<{ labelledby: string; describedby?: string; closeLabel?: string; busy?: boolean; dismissible?: boolean; initialFocus?: string; size?: 'md' | 'lg'; editor?: boolean; expanded?: boolean }>(), { closeLabel: 'Close dialog', busy: false, dismissible: true, size: 'md', editor: false, expanded: false });
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);
const viewportStyle = ref<{ height: string; top: string; left: string; width: string }>();

function updateViewport() {
    const viewport = window.visualViewport;
    if (viewport) viewportStyle.value = { height: `${viewport.height}px`, top: `${viewport.offsetTop}px`, left: `${viewport.offsetLeft}px`, width: `${viewport.width}px` };
}

function requestClose() {
    if (!props.busy && props.dismissible) emit('close');
}

onMounted(() => {
    if (props.editor) {
        updateViewport();
        window.visualViewport?.addEventListener('resize', updateViewport);
        window.visualViewport?.addEventListener('scroll', updateViewport);
    }
    dialog.value?.showModal();
    const initial = props.initialFocus ? dialog.value?.querySelector<HTMLElement>(props.initialFocus) : closeButton.value;
    (initial ?? closeButton.value)?.focus();
});

onBeforeUnmount(() => {
    window.visualViewport?.removeEventListener('resize', updateViewport);
    window.visualViewport?.removeEventListener('scroll', updateViewport);
    dialog.value?.close();
});
</script>
