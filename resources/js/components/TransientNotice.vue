<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { createNoticeTimer } from './transient-notice-timer.js';

const props = defineProps({ message: { type: String, default: '' }, dismissLabel: { type: String, required: true }, suspended: Boolean });
const emit = defineEmits(['dismiss']);
const visible = ref(false);
const announcement = ref('');
const notice = ref(null);
let mounted = false;
const timer = createNoticeTimer(() => dismiss());

function dismiss() {
    const restoreFocus = Boolean(notice.value?.contains(document.activeElement));
    visible.value = false;
    announcement.value = '';
    timer.stop();
    emit('dismiss', restoreFocus);
}
async function showMessage() {
    timer.stop();
    announcement.value = '';
    visible.value = Boolean(props.message);
    if (!props.message) {
        timer.resume('pointer');
        timer.resume('focus');
        return;
    }
    timer.restart();
    // Populate an already-mounted status region so updates are announced reliably.
    await nextTick();
    if (mounted && !props.suspended && visible.value) announcement.value = props.message;
}
watch(() => props.message, showMessage);
watch(() => props.suspended, (suspended) => {
    if (suspended) { timer.pause('celebration'); announcement.value = ''; }
    else {
        timer.resume('celebration');
        if (visible.value) announcement.value = props.message;
    }
}, { immediate: true });
function syncVisibility() {
    if (document.hidden) timer.pause('background');
    else timer.resume('background');
}
function focusOut(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) timer.resume('focus');
}
onMounted(() => {
    mounted = true;
    document.addEventListener('visibilitychange', syncVisibility);
    syncVisibility();
    showMessage();
});
onBeforeUnmount(() => {
    mounted = false;
    timer.stop();
    document.removeEventListener('visibilitychange', syncVisibility);
});
</script>

<template>
    <Teleport to="#transient-notice-root">
        <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ announcement }}</div>
        <Transition enter-active-class="transition duration-150 ease-out motion-reduce:transition-none" enter-from-class="translate-y-2 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in motion-reduce:transition-none" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="visible && !suspended" ref="notice" data-transient-notice class="pointer-events-auto flex items-center gap-3 rounded-xl border border-gray-200 bg-white py-2 ps-4 pe-2 text-sm text-gray-900 shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" @mouseenter="timer.pause('pointer')" @mouseleave="timer.resume('pointer')" @focusin="timer.pause('focus')" @focusout="focusOut">
                <p class="min-w-0 flex-1">{{ message }}</p>
                <button type="button" :aria-label="dismissLabel" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:text-gray-300 dark:hover:bg-gray-700" @click="dismiss">
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="none"><path d="m4 4 8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
