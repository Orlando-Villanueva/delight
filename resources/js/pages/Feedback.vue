<template>
    <Head><title>{{ copy.feedback }}</title></Head>
    <section class="mx-auto w-full max-w-3xl space-y-6" aria-labelledby="feedback-title">
        <header>
            <h1 id="feedback-title" class="text-2xl font-bold text-gray-900 dark:text-white">{{ copy.feedback }}</h1>
            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ copy.feedbackDescription }}</p>
        </header>
        <form class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800" :aria-busy="form.processing" @submit.prevent="submit">
            <div>
                <label for="feedback-category" class="form-label">{{ copy.category }}</label>
                <select id="feedback-category" v-model="form.category" name="category" required class="form-input block min-h-11 rounded-lg text-sm dark:border-gray-500 dark:focus:border-primary-400" :aria-invalid="!!form.errors.category" :aria-describedby="form.errors.category ? 'feedback-category-error' : undefined">
                    <option value="general">{{ copy.generalFeedback }}</option>
                    <option value="bug">{{ copy.reportBug }}</option>
                    <option value="feature">{{ copy.featureRequest }}</option>
                    <option value="other">{{ copy.other }}</option>
                </select>
                <p v-if="form.errors.category" id="feedback-category-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.category }}</p>
            </div>
            <div>
                <label for="feedback-message" class="form-label">{{ copy.message }}</label>
                <textarea id="feedback-message" v-model="form.message" name="message" rows="6" maxlength="5000" required :placeholder="copy.yourMessage" class="form-input block rounded-lg text-sm dark:border-gray-500 dark:focus:border-primary-400" :aria-invalid="!!form.errors.message" :aria-describedby="form.errors.message ? 'feedback-message-hint feedback-message-error' : 'feedback-message-hint'"></textarea>
                <p id="feedback-message-hint" class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ copy.charactersLimit }}</p>
                <p v-if="form.errors.message" id="feedback-message-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.message }}</p>
            </div>
            <p v-if="submissionError" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ submissionError }}</p>
            <div class="flex sm:justify-end">
                <button ref="submitButton" type="submit" :disabled="form.processing" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto dark:bg-primary-500 dark:hover:bg-primary-600">{{ form.processing ? copy.sending : copy.sendFeedback }}</button>
            </div>
        </form>
    </section>
    <TransientNotice :message="success" :dismiss-label="copy.dismissFeedback" @dismiss="dismissSuccess" />
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import TransientNotice from '@/components/TransientNotice.vue';
import type { ReaderPageProps } from '@/reader-types';
import { useReaderCopy } from '@/reader-copy';

const copy = useReaderCopy();
const props = defineProps<{ submitUrl: string }>();
const page = usePage<ReaderPageProps & { errors: Record<string, string> }>();
const form = useForm({ category: 'general', message: '' });
const success = ref('');
const submitButton = ref<HTMLButtonElement | null>(null);
const submissionError = computed(() => page.props.errors.submission);

function submit() {
    if (form.processing) return;
    success.value = '';
    form.post(props.submitUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            success.value = page.props.status ?? '';
            router.replace<ReaderPageProps>({
                props: current => ({ ...current, status: null }),
                preserveState: true,
                preserveScroll: true,
            });
        },
    });
}

function dismissSuccess(restoreFocus: boolean) {
    success.value = '';
    if (restoreFocus) nextTick(() => submitButton.value?.focus());
}
</script>
