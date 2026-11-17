import test from 'node:test';
import assert from 'node:assert/strict';
import { computed, reactive } from 'vue';
import { reconcileSettings } from '../resources/js/settings-state.ts';
import { clearReadingDraft, clearReadingDraftForDifferentAccount, hasReadingDraft, isReadingBookAvailable, readingDraftFor, readingDraftForReader, refreshReadingDates } from '../resources/js/reading-drafts.ts';
import { readerCopy } from '../resources/js/reader-copy.ts';
import { ordinal } from '../resources/js/dashboard-helpers.ts';
import { consumeReadingLogOutcome, createReadingLogSession, readingSuccessPresentation, readingValidationPresentation, submitReadingLog } from '../resources/js/components/reading-log-session.ts';

const settings = { preferred_locale: 'en', include_deuterocanonical: false, daily_reading_reminder_enabled: false, streak_warning_enabled: false, reading_timezone: 'Asia/Tokyo' };
const config = () => ({ values: { book_id: '', start_chapter: '', end_chapter: '', date_read: '2026-11-12', notes_text: '' }, dates: { today: '2026-11-12', yesterday: '2026-11-11' } });

test('clean cached settings adopt newly confirmed language', () => {
    assert.deepEqual(reconcileSettings(settings, settings, { ...settings, preferred_locale: 'fr' }), { ...settings, preferred_locale: 'fr' });
});

test('dirty settings retain only edited fields while adopting confirmed account changes', () => {
    const confirmed = { ...settings, preferred_locale: 'fr' };
    assert.deepEqual(reconcileSettings({ ...settings, daily_reading_reminder_enabled: true }, settings, confirmed), { ...confirmed, daily_reading_reminder_enabled: true });
});

test('private reading draft survives navigation in memory and clears when the account changes', () => {
    clearReadingDraft();
    const first = readingDraftFor(1, config());
    Object.assign(first.snapshot.values, { book_id: '43', start_chapter: '2', notes_text: 'Une note privée' });
    const returned = readingDraftFor(1, config());
    assert.equal(returned, first);
    assert.equal(returned.snapshot.values.notes_text, 'Une note privée');
    assert.equal(returned.snapshot.values.book_id, '43');
    assert.equal(readingDraftFor(2, config()).snapshot.values.notes_text, '');
    clearReadingDraft();
});

test('reactive page account changes replace the form session and isolate late outcomes', async () => {
    clearReadingDraft();
    const page = reactive({ reader: { user: { id: 1 }, readingTimezone: 'UTC' } });
    const reader = computed(() => page.reader);
    const formConfig = computed(config);
    const session = readingDraftForReader(reader, formConfig);
    const firstAccountSession = session.value;
    firstAccountSession.snapshot.values.notes_text = 'Private account one note';

    let settleRequest;
    const pending = submitReadingLog(firstAccountSession, firstAccountSession.snapshot.values, {
        url: '/reading', csrfToken: 'token',
        fetcher: () => new Promise((resolve) => { settleRequest = resolve; }),
    });

    page.reader = { user: { id: 2 }, readingTimezone: 'UTC' };
    const secondAccountSession = session.value;
    assert.notEqual(secondAccountSession, firstAccountSession);
    assert.equal(secondAccountSession.snapshot.values.notes_text, '');

    settleRequest({ redirected: false, status: 422, ok: false, json: async () => ({ errors: {}, form: { dates: { today: '2026-11-13', yesterday: '2026-11-12' }, values: { date_read: '2026-11-13' } } }) });
    await pending;
    assert.equal(secondAccountSession.snapshot.values.notes_text, '');
    assert.equal(secondAccountSession.snapshot.values.date_read, '2026-11-12');
    clearReadingDraft();
});

test('root account cleanup retains the draft for the same account and clears only on account change', () => {
    clearReadingDraft();
    const draft = readingDraftFor(1, config());
    draft.snapshot.values.notes_text = 'Private note';
    clearReadingDraftForDifferentAccount(1);
    assert.equal(readingDraftFor(1, config()).snapshot.values.notes_text, 'Private note');
    clearReadingDraftForDifferentAccount(2);
    assert.equal(readingDraftFor(2, config()).snapshot.values.notes_text, '');
    clearReadingDraft();
});

test('calendar corrections preserve Yesterday selection, chapters and private notes', () => {
    clearReadingDraft();
    const draft = readingDraftFor(1, config(), 'America/Toronto');
    Object.assign(draft.snapshot.values, { date_read: '2026-11-11', start_chapter: '3', notes_text: 'À conserver' });
    refreshReadingDates(draft, { today: '2026-11-11', yesterday: '2026-11-10' }, 'Pacific/Auckland');
    assert.equal(draft.snapshot.values.date_read, '2026-11-10');
    assert.equal(draft.snapshot.values.start_chapter, '3');
    assert.equal(draft.snapshot.values.notes_text, 'À conserver');
    clearReadingDraft();
});

test('calendar refresh never modifies a pending or uncertain reading submission', () => {
    clearReadingDraft();
    const draft = readingDraftFor(1, config());
    draft.pending = new Promise(() => {});
    refreshReadingDates(draft, { today: '2026-11-10', yesterday: '2026-11-09' });
    assert.equal(draft.snapshot.values.date_read, '2026-11-12');
    draft.pending = null;
    draft.uncertain = true;
    refreshReadingDates(draft, { today: '2026-11-10', yesterday: '2026-11-09' });
    assert.equal(draft.snapshot.values.date_read, '2026-11-12');
    clearReadingDraft();
});

test('date refresh keeps an absolute Today selection across midnight and only remaps on timezone change', () => {
    clearReadingDraft();
    const draft = readingDraftFor(1, config(), 'America/Toronto');
    draft.snapshot.values.date_read = '2026-11-12';
    refreshReadingDates(draft, { today: '2026-11-13', yesterday: '2026-11-12' }, 'America/Toronto');
    assert.equal(draft.snapshot.values.date_read, '2026-11-12');

    refreshReadingDates(draft, { today: '2026-11-14', yesterday: '2026-11-13' }, 'Pacific/Auckland');
    assert.equal(draft.snapshot.values.date_read, '2026-11-13');
    clearReadingDraft();
});

test('app-level unload warning stays active while a draft is parked outside the reading page', () => {
    clearReadingDraft();
    const draft = readingDraftFor(1, config(), 'UTC');
    assert.equal(hasReadingDraft(), false);
    draft.snapshot.values.notes_text = 'À conserver';
    assert.equal(hasReadingDraft(), true);
    draft.snapshot.values.notes_text = '';
    draft.snapshot.notesOpen = true;
    assert.equal(hasReadingDraft(), false);
    draft.outcome = { kind: 'success' };
    assert.equal(hasReadingDraft(), false);
    clearReadingDraft();
});

test('canon changes retain an unavailable selected book until the user changes it', () => {
    const session = createReadingLogSession({ ...config(), collection: 'new', notesOpen: false });
    session.snapshot.values.book_id = '67';
    session.uncertain = true;
    const currentBooks = [{ id: 1, name: 'Genèse', chapters: 50, testament: 'old' }];
    assert.equal(isReadingBookAvailable(session.snapshot.values.book_id, currentBooks), false);
    assert.equal(session.snapshot.values.book_id, '67');
    assert.equal(session.uncertain, true);
    assert.equal(isReadingBookAvailable('1', currentBooks), true);
});

test('late success uses the current localized catalog and suppresses stale celebration presentation', async () => {
    const values = { book_id: '43', start_chapter: '2', end_chapter: '4', date_read: '2026-11-12', notes_text: 'Note privée' };
    const data = {
        message: 'Saved', reading: { passage_text: 'John 2–4', date_read: '2026-11-12' },
        form: { values: { ...values, book_id: '', start_chapter: '', end_chapter: '', notes_text: '' }, dates: { today: '2026-11-13', yesterday: '2026-11-12' }, books: [{ id: 43, name: 'John', chapters: 21, testament: 'new' }], recent_books: [] },
        celebration: { earned: [{ id: 1, display_name: 'Book completed', description: '', icon: '', is_repeat_book_completion: false }], progress: [], record: null, reading: { passage: 'John 2–4', date: '2026-11-12' } },
    };
    const session = createReadingLogSession({ values, collection: 'new', notesOpen: true });
    const outcome = await submitReadingLog(session, values, {
        url: '/reading', csrfToken: 'token',
        context: { userId: 1, locale: 'en', timeZone: 'UTC', includeDeuterocanonical: true },
        fetcher: async () => ({ redirected: false, status: 200, ok: true, json: async () => data }),
    });
    assert.equal(outcome.kind, 'success');
    const current = { userId: 1, locale: 'fr', timeZone: 'UTC', includeDeuterocanonical: false };
    const latestBooks = [{ id: 43, name: 'Jean', chapters: 21, testament: 'new' }];
    const presentation = readingSuccessPresentation(outcome, current, {
        books: latestBooks, recentBooks: [], dates: { today: '2026-11-13', yesterday: '2026-11-12' },
        values: { ...values, book_id: '', start_chapter: '', end_chapter: '', notes_text: '' },
    });

    assert.equal(presentation.current, false);
    assert.deepEqual(presentation.books, latestBooks);
    assert.equal(presentation.celebration, null);
    assert.equal(presentation.reference, 'Jean 2–4');
    assert.equal(presentation.values.notes_text, '');
});

test('a retained validation outcome uses current-language recovery after switching languages without losing the draft', async () => {
    for (const [submittedLocale, currentLocale, serverMessage] of [
        ['en', 'fr', 'Invalid chapter range for the selected book.'],
        ['fr', 'en', 'La plage de chapitres est invalide pour le livre sélectionné.'],
    ]) {
        const values = { book_id: '43', start_chapter: '999', end_chapter: '', date_read: '2026-11-12', notes_text: 'Private note' };
        const session = createReadingLogSession({ values, collection: 'new', notesOpen: true });
        await submitReadingLog(session, values, {
            url: '/reading', csrfToken: 'token',
            context: { userId: 1, locale: submittedLocale, timeZone: 'UTC', includeDeuterocanonical: false },
            fetcher: async () => ({ redirected: false, status: 422, ok: false, json: async () => ({ errors: { start_chapter: [serverMessage] }, error_scopes: { start_chapter: 'chapter_range' } }) }),
        });

        const outcome = consumeReadingLogOutcome(session);
        const retryMessage = readerCopy(currentLocale).validationRetry;
        const presentation = readingValidationPresentation(outcome, currentLocale, retryMessage);

        assert.equal(outcome.kind, 'validation');
        assert.equal(session.submissionContext, null);
        assert.deepEqual(presentation, { errors: {}, errorScopes: {}, notice: retryMessage });
        assert.deepEqual(session.snapshot.values, values);
        assert.equal(session.uncertain, false);
        assert.deepEqual(readingValidationPresentation(outcome, submittedLocale, retryMessage), {
            errors: { start_chapter: [serverMessage] }, errorScopes: { start_chapter: 'chapter_range' }, notice: '',
        });
    }
});

test('French completion and deletion labels retain the ordinal and chapter unit', () => {
    const fr = readerCopy('fr');
    assert.equal(fr.ordinalBibleRead(ordinal(2, 'fr')), '2e lecture de la Bible');
    assert.equal(fr.deleteSelected(1), 'Supprimer 1 chapitre');
    assert.equal(fr.deleteSelected(2), 'Supprimer 2 chapitres');
    assert.match(fr.olderDaysLoaded(2), /2 jours de lecture/);
});


test('older same-timezone history props cannot roll a retained draft backward', () => {
    clearReadingDraft();
    const draft = readingDraftFor(1, config(), 'America/Toronto');
    refreshReadingDates(draft, { today: '2026-11-13', yesterday: '2026-11-12' }, 'America/Toronto');
    draft.snapshot.values.date_read = '2026-11-13';
    refreshReadingDates(draft, { today: '2026-11-12', yesterday: '2026-11-11' }, 'America/Toronto');
    assert.equal(draft.snapshot.values.date_read, '2026-11-13');
    assert.equal(draft.dateContext.today, '2026-11-13');
    clearReadingDraft();
});


test('reactive Inertia form props initialize and refresh an independent draft without cloning Vue proxies', () => {
    clearReadingDraft();
    const props = reactive(config());
    const session = readingDraftFor(1, props, 'UTC');
    session.snapshot.values.notes_text = 'Draft note';
    assert.equal(props.values.notes_text, '');
    refreshReadingDates(session, props.dates, 'UTC');
    assert.equal(session.snapshot.values.notes_text, 'Draft note');
    assert.notEqual(session.dateContext, props.dates);
    clearReadingDraft();
});

test('retained success keeps the newer confirmed calendar and resets the next reading to Today', async () => {
    const values = { book_id: '43', start_chapter: '1', end_chapter: '', date_read: '2026-11-12', notes_text: 'Saved note' };
    const context = { userId: 1, locale: 'en', timeZone: 'UTC', includeDeuterocanonical: false };
    const reset = { ...values, book_id: '', start_chapter: '', notes_text: '' };
    const oldDates = { today: '2026-11-12', yesterday: '2026-11-11' };
    const newDates = { today: '2026-11-13', yesterday: '2026-11-12' };
    const books = [{ id: 43, name: 'John', chapters: 21, testament: 'new' }];
    const data = { message: 'Saved', reading: { passage_text: 'John 1', date_read: values.date_read },
        form: { values: reset, dates: oldDates, books, recent_books: books },
        celebration: { earned: [], progress: [], record: null, reading: { passage: 'John 1', date: values.date_read } } };
    const session = createReadingLogSession({ values, collection: 'new', notesOpen: true });
    await submitReadingLog(session, values, { url: '/reading', csrfToken: 'token', context,
        fetcher: async () => ({ redirected: false, status: 200, ok: true, json: async () => data }) });
    refreshReadingDates(session, newDates, 'UTC');
    const outcome = consumeReadingLogOutcome(session);

    const presentation = readingSuccessPresentation(outcome, context, { books, recentBooks: [], dates: newDates, values: reset });

    assert.equal(presentation.current, true);
    assert.deepEqual(presentation.dates, newDates);
    assert.equal(presentation.values.date_read, newDates.today);
    assert.equal(presentation.values.notes_text, '');
    assert.equal(outcome.submission.submittedValues.date_read, '2026-11-12');
    const newerResponse = { ...outcome, data: { ...data, form: { ...data.form, dates: newDates, values: { ...reset, date_read: newDates.today } } } };
    const fresh = readingSuccessPresentation(newerResponse, context, { books, recentBooks: [], dates: oldDates, values: reset });
    assert.deepEqual(fresh.dates, newDates);
    assert.equal(fresh.values.date_read, newDates.today);
});
