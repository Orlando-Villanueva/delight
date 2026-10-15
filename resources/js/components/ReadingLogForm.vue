<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { consumeReadingLogOutcome, initializeReadingLogForm, setReadingLogUncertain, submitReadingLog, updateReadingLogSnapshot } from './reading-log-session.js';
import TransientNotice from './TransientNotice.vue';

const { config, session } = defineProps(['config', 'session']);
const messages = config.messages;
const initialForm = initializeReadingLogForm(session, config);
const form = ref(null);
const collectionButton = ref(null);
const collectionMenu = ref(null);
const menuOpen = ref(false);
const books = ref(config.books);
const recentBooks = ref(config.recentBooks);
const dates = ref(initialForm.dates);
const values = reactive({ ...initialForm.values });
if (!books.value.some((book) => String(book.id) === String(values.book_id))) values.book_id = '';
const collection = ref(books.value.find((book) => String(book.id) === String(values.book_id))?.testament ?? session.snapshot.collection);
const notesOpen = ref(session.snapshot.notesOpen || Boolean(values.notes_text));
const errors = ref(config.errors);
const errorScopes = ref(config.errorScopes ?? {});
const rangeErrors = computed(() => errorScopes.value.start_chapter === 'chapter_range' ? (errors.value.start_chapter ?? []) : []);
const chapterFieldError = (field) => field === 'start_chapter' && rangeErrors.value.length ? null : errors.value[field];
function chapterDescription(field) {
    return [chapterFieldError(field) ? `${field}-error` : '', rangeErrors.value.length ? 'chapter-range-error' : ''].filter(Boolean).join(' ') || undefined;
}
const saving = ref(Boolean(session.pending));
const success = ref(config.successMessage);
const celebrationPending = ref(false);
const notice = ref(session.uncertain ? messages.uncertain_error : '');
const uncertain = ref(session.uncertain);
const isScrolling = ref(false);
let scrollTimer;
let active = true;
const collections = computed(() => Object.entries(messages.collections).filter(([key]) => books.value.some((book) => book.testament === key)));
const visibleBooks = computed(() => books.value.filter((book) => book.testament === collection.value));
const selectedBook = computed(() => books.value.find((book) => String(book.id) === String(values.book_id)));
const chapterNote = computed(() => {
    if (selectedBook.value?.id === 17 && selectedBook.value.chapters === 16) return messages.chapter_notes.esther_additions;
    if (selectedBook.value?.id === 27 && selectedBook.value.chapters === 14) return messages.chapter_notes.daniel_additions;
    return '';
});

function snapshot() {
    // A pending save owns the submitted snapshot until its result settles.
    if (!session.pending) updateReadingLogSnapshot(session, { values: { ...values }, collection: collection.value, notesOpen: notesOpen.value });
}
watch([values, collection, notesOpen], snapshot, { deep: true, flush: 'sync' });

function closeMenu(focus = false) {
    menuOpen.value = false;
    if (focus) collectionButton.value?.focus();
}
function chooseCollection(key) {
    collection.value = key;
    // Keep the submitted ID consistent with the visibly selected book.
    if (!visibleBooks.value.some((book) => String(book.id) === String(values.book_id))) values.book_id = '';
    closeMenu(true);
}
function selectRecent(book) {
    collection.value = book.testament;
    values.book_id = String(book.id);
}
async function openMenu() {
    menuOpen.value = true;
    await nextTick();
    collectionMenu.value?.querySelector('button')?.focus();
}
function menuKeys(event) {
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const buttons = [...collectionMenu.value.querySelectorAll('button')];
    const current = buttons.indexOf(document.activeElement);
    const index = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1
        : (current + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length;
    buttons[index]?.focus();
}
function outsideClick(event) {
    if (!collectionMenu.value?.contains(event.target) && !collectionButton.value?.contains(event.target)) closeMenu();
}
function recentScroll() {
    isScrolling.value = true;
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => { isScrolling.value = false; }, 700);
}
async function handleOutcome(pending) {
    const settledOutcome = await pending;
    if (!active) return;
    const outcome = consumeReadingLogOutcome(session) ?? settledOutcome;
    saving.value = false;
    if (outcome.kind === 'success') {
        const data = outcome.data;
        const refreshedForm = initializeReadingLogForm(session, { dates: dates.value });
        books.value = data.form.books;
        recentBooks.value = data.form.recent_books;
        dates.value = refreshedForm.dates;
        Object.assign(values, refreshedForm.values);
        collection.value = 'old';
        notesOpen.value = false;
        errors.value = {};
        errorScopes.value = {};
        notice.value = '';
        uncertain.value = false;
        celebrationPending.value = Boolean(data.celebration_html && window.htmx);
        const dateLabel = new Intl.DateTimeFormat(config.locale ?? 'en', {
            month: 'short', day: 'numeric', timeZone: 'UTC',
        }).format(new Date(`${data.reading.date_read}T12:00:00Z`));
        success.value = messages.saved.replace(':passage', data.reading.passage_text).replace(':date', dateLabel);
        snapshot();
        // This existing Alpine celebration is outside the Vue-owned form.
        if (data.celebration_html && window.htmx) {
            window.htmx.swap('#achievement-celebration-root', data.celebration_html, { swapStyle: 'none' });
        }
    } else if (outcome.kind === 'validation') {
        const refreshedForm = initializeReadingLogForm(session, { dates: dates.value });
        dates.value = refreshedForm.dates;
        values.date_read = refreshedForm.values.date_read;
        errors.value = outcome.errors;
        errorScopes.value = outcome.errorScopes;
        if (errors.value.notes_text) notesOpen.value = true;
        notice.value = '';
        await nextTick();
        form.value?.querySelector('[aria-invalid="true"]')?.focus();
    } else {
        uncertain.value = outcome.kind === 'uncertain';
        notice.value = outcome.kind === 'session' ? messages.session_error
            : uncertain.value ? messages.uncertain_error : messages.request_error;
    }
}
async function submit() {
    if (saving.value || uncertain.value) return;
    snapshot();
    saving.value = true;
    errors.value = {};
    errorScopes.value = {};
    notice.value = '';
    success.value = '';
    await handleOutcome(submitReadingLog(session, values, {
        url: config.submitUrl, csrfToken: config.csrfToken,
        onSaved: () => document.body.dispatchEvent(new CustomEvent('readingLogAdded', { bubbles: true })),
    }));
}
async function dismissSuccess(restoreFocus) {
    success.value = '';
    if (restoreFocus) {
        await nextTick();
        form.value?.querySelector('button[type="submit"]')?.focus();
    }
}
function celebrationClosed() { celebrationPending.value = false; }
function allowRetry() {
    setReadingLogUncertain(session, false);
    uncertain.value = false;
    notice.value = '';
}

onMounted(() => {
    document.addEventListener('click', outsideClick);
    document.addEventListener('achievement-celebration-closed', celebrationClosed);
    if (session.pending) handleOutcome(session.pending);
    else if (session.outcome) handleOutcome(Promise.resolve(session.outcome));
});
onBeforeUnmount(() => {
    active = false;
    snapshot();
    clearTimeout(scrollTimer);
    document.removeEventListener('click', outsideClick);
    document.removeEventListener('achievement-celebration-closed', celebrationClosed);
});
</script>

<template>
    <form ref="form" class="space-y-6" :aria-busy="saving" @submit.prevent="submit">
        <fieldset class="m-0 min-w-0 space-y-6 border-0 p-0" :disabled="saving">
            <fieldset class="space-y-2">
                <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ messages.when }}</legend>
                <div data-date-read-segmented-control class="grid grid-cols-2 overflow-hidden rounded-lg border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-700">
                    <div v-for="day in ['today', 'yesterday']" :key="day" class="flex items-center" :class="day === 'yesterday' ? 'border-l border-gray-300 dark:border-gray-600' : ''">
                        <input :id="day" v-model="values.date_read" type="radio" name="date_read" :value="dates[day]" class="peer sr-only" :aria-invalid="Boolean(errors.date_read)" :aria-describedby="errors.date_read ? 'date_read-error' : undefined">
                        <label :for="day" class="flex w-full cursor-pointer items-center justify-center bg-transparent px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 peer-checked:bg-primary-50 peer-checked:text-primary-700 peer-checked:hover:bg-primary-50 peer-focus-visible:relative peer-focus-visible:z-10 peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-inset dark:text-gray-200 dark:hover:bg-gray-600 dark:peer-checked:bg-primary-900/30 dark:peer-checked:text-primary-300 dark:peer-checked:hover:bg-primary-900/30">{{ messages[day] }}</label>
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ messages.date_help }}</p>
                <p v-if="errors.date_read" id="date_read-error" class="form-error" role="alert">{{ errors.date_read[0] }}</p>
            </fieldset>

            <div class="space-y-3">
                <label for="book_id" class="form-label">{{ messages.book }}</label>
                <div class="relative flex" @keydown.esc.prevent="closeMenu(true)" @focusout="event => { if (!event.currentTarget.contains(event.relatedTarget)) closeMenu(); }">
                    <button id="testament-button" ref="collectionButton" type="button" aria-controls="testament-dropdown" :aria-expanded="menuOpen" class="z-10 inline-flex shrink-0 items-center rounded-s-lg border border-gray-300 bg-white px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:z-10 focus-visible:border-primary-500 focus:outline-none focus-visible:outline-1 focus-visible:outline-solid focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 dark:focus-visible:border-primary-400 dark:focus-visible:outline-primary-400" @click="menuOpen ? closeMenu() : openMenu()" @keydown.down.prevent="openMenu">
                        <span>{{ messages.collections[collection] }}</span>
                        <svg class="ms-2.5 h-2.5 w-2.5" aria-hidden="true" fill="none" viewBox="0 0 10 6"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" /></svg>
                    </button>
                    <div v-if="menuOpen" id="testament-dropdown" ref="collectionMenu" class="absolute start-0 top-full z-30 mt-1 w-52 divide-y divide-gray-100 rounded-lg bg-white py-2 shadow dark:divide-gray-600 dark:bg-gray-700" @keydown="menuKeys">
                        <button v-for="[key, label] in collections" :key="key" type="button" class="inline-flex w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-200 dark:hover:bg-gray-600" @click="chooseCollection(key)">{{ label }}</button>
                    </div>
                    <div class="relative min-w-0 flex-1 focus-within:z-20">
                        <select id="book_id" v-model="values.book_id" name="book_id" required class="form-input -ml-px w-full rounded-s-none shadow-none" :aria-label="messages.book_label" :aria-invalid="Boolean(errors.book_id)" :aria-describedby="errors.book_id ? 'book_id-error' : undefined">
                            <option value="">{{ messages.select_book }}</option>
                            <option v-for="book in visibleBooks" :key="book.id" :value="String(book.id)">{{ book.name }}</option>
                        </select>
                    </div>
                </div>
                <div v-if="recentBooks.length" data-recent-books class="recent-books-rail -mt-1 flex max-w-full items-center gap-2 overflow-x-auto py-1 [-webkit-overflow-scrolling:touch]" :class="{ 'is-scrolling': isScrolling }" @scroll.passive="recentScroll">
                    <p id="recent-books-label" class="shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400">{{ messages.recent }}</p>
                    <div class="flex min-w-0 flex-nowrap gap-2" role="group" aria-labelledby="recent-books-label">
                        <button v-for="book in recentBooks" :key="book.id" type="button" :data-recent-book-suggestion="book.id" :aria-pressed="selectedBook?.id === book.id" class="inline-flex min-h-9 max-w-[11rem] shrink-0 items-center rounded-full border px-2.5 py-1 text-left text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-gray-900" :class="selectedBook?.id === book.id ? 'border-gray-200 bg-primary-50 text-primary-700 hover:bg-primary-100 dark:border-gray-700 dark:bg-primary-900/40 dark:text-primary-300 dark:hover:bg-primary-900/60' : 'border-gray-200 bg-gray-50 text-gray-700 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-100'" @click="selectRecent(book)">
                            <span class="truncate">{{ book.name }}</span>
                        </button>
                    </div>
                </div>
                <p v-if="errors.book_id" id="book_id-error" class="form-error" role="alert">{{ errors.book_id[0] }}</p>
            </div>

            <div>
                <div class="grid grid-cols-2 gap-4">
                    <div v-for="field in ['start_chapter', 'end_chapter']" :key="field">
                        <label :for="field" class="form-label">{{ field === 'start_chapter' ? messages.start : messages.end }}</label>
                        <input :id="field" v-model="values[field]" :name="field" type="text" inputmode="numeric" pattern="[0-9]*" class="form-input" :required="field === 'start_chapter'" :placeholder="selectedBook ? (field === 'start_chapter' ? '1' : String(selectedBook.chapters)) : (field === 'start_chapter' ? messages.start_placeholder : messages.end_placeholder)" :aria-invalid="Boolean(chapterFieldError(field) || rangeErrors.length)" :aria-describedby="chapterDescription(field)">
                        <p v-if="chapterFieldError(field)" :id="`${field}-error`" class="form-error" role="alert">{{ chapterFieldError(field)[0] }}</p>
                    </div>
                </div>
                <p v-if="rangeErrors.length" id="chapter-range-error" class="form-error mt-2" role="alert">{{ rangeErrors[0] }}</p>
                <p v-if="chapterNote" class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ chapterNote }}</p>
            </div>

            <div class="space-y-3">
                <button v-if="!notesOpen" type="button" class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 transition hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300" @click="notesOpen = true; nextTick(() => form.querySelector('#notes_text')?.focus())"><span aria-hidden="true">+</span>{{ messages.add_note }}</button>
                <div v-show="notesOpen" class="space-y-1">
                    <div class="flex items-baseline justify-between gap-3">
                        <label for="notes_text" class="form-label">{{ messages.note }}</label>
                        <span class="text-xs font-medium text-gray-500 tabular-nums dark:text-gray-400" aria-live="polite">{{ values.notes_text.length }}/1000</span>
                    </div>
                    <textarea id="notes_text" v-model="values.notes_text" name="notes_text" rows="3" maxlength="1000" class="form-input resize-y" :placeholder="messages.note_placeholder" :aria-invalid="Boolean(errors.notes_text)" :aria-describedby="errors.notes_text ? 'notes_text-error' : undefined"></textarea>
                    <p v-if="errors.notes_text" id="notes_text-error" class="form-error" role="alert">{{ errors.notes_text[0] }}</p>
                </div>
            </div>

            <div class="pt-0">
                <button type="submit" class="btn btn-accent min-h-12 w-full px-6 py-3 text-base font-medium shadow-sm disabled:cursor-wait disabled:opacity-60 sm:w-auto" :disabled="saving || uncertain">{{ saving ? messages.saving : messages.save }}</button>
            </div>
        </fieldset>
        <div v-if="notice" role="alert" class="space-y-3">
            <p class="form-error">{{ notice }}</p>
            <template v-if="uncertain">
                <a :href="config.historyUrl" target="_blank" rel="noopener" class="text-sm text-primary-600 underline dark:text-primary-400">{{ messages.check_history }}</a>
                <button type="button" class="block text-sm text-primary-600 underline dark:text-primary-400" @click="allowRetry">{{ messages.retry_checked }}</button>
            </template>
        </div>
    </form>
    <TransientNotice :message="success" :suspended="celebrationPending" :dismiss-label="messages.dismiss_success" @dismiss="dismissSuccess" />
</template>
