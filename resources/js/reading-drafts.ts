import { computed, type ComputedRef } from 'vue';
import { createReadingLogSession, updateReadingLogDateContext } from './components/reading-log-session.ts';
import type { BibleBook, ReadingDates, ReadingFormConfig, ReadingSession } from './components/reading-log-types.ts';
import type { ReaderProps } from './reader-types.ts';

let accountId: number | null = null;
let draft: ReadingSession | null = null;

export function clearReadingDraft() {
    accountId = null;
    draft = null;
}

export function clearReadingDraftForDifferentAccount(userId: number | null) {
    if (accountId !== null && accountId !== userId) clearReadingDraft();
}

export function readingDraftForReader(
    reader: ComputedRef<Pick<ReaderProps, 'user' | 'readingTimezone'>>,
    config: ComputedRef<ReadingFormConfig>,
): ComputedRef<ReadingSession> {
    return computed(() => readingDraftFor(reader.value.user.id, config.value, reader.value.readingTimezone));
}

export function refreshReadingDates(session: ReadingSession, dates: ReadingDates, timeZone = session.dateTimeZone ?? '') {
    if (session.pending || session.uncertain) return;
    const previous = session.dateContext;
    const selected = session.snapshot.values.date_read;
    const timeZoneChanged = Boolean(session.dateTimeZone && timeZone && session.dateTimeZone !== timeZone);
    if (!timeZoneChanged && previous && dates.today < previous.today) return;
    if (timeZoneChanged && previous) {
        session.snapshot.values.date_read = selected === previous.today ? dates.today
            : selected === previous.yesterday ? dates.yesterday
                : [dates.today, dates.yesterday].includes(selected) ? selected : dates.today;
    } else if (![dates.today, dates.yesterday].includes(selected)) {
        session.snapshot.values.date_read = dates.today;
    }
    updateReadingLogDateContext(session, dates);
    if (timeZone) session.dateTimeZone = timeZone;
}

export function readingDraftFor(userId: number, config: ReadingFormConfig, timeZone = ''): ReadingSession {
    if (accountId !== userId) clearReadingDraft();
    accountId = userId;
    draft ??= createReadingLogSession({ values: { ...config.values }, collection: 'old', notesOpen: false });
    refreshReadingDates(draft, config.dates, timeZone);
    return draft;
}

export function hasReadingDraft(): boolean {
    if (!draft) return false;
    const { values } = draft.snapshot;
    return Boolean(draft.pending || draft.uncertain || values.book_id || values.start_chapter || values.end_chapter || values.notes_text || (draft.dateContext && values.date_read !== draft.dateContext.today));
}

export function isReadingBookAvailable(bookId: string, books: BibleBook[]): boolean {
    return !bookId || books.some((book) => String(book.id) === String(bookId));
}
