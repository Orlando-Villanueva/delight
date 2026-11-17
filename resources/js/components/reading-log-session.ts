import type { BibleBook, ReadingDates, ReadingErrors, ReadingErrorScopes, ReadingOutcome, ReadingSession, ReadingSnapshot, ReadingSuccess, ReadingSubmissionContext, ReadingValues } from './reading-log-types.ts';

export function createReadingLogSession(snapshot: ReadingSnapshot): ReadingSession {
    return { snapshot: { ...snapshot, values: { ...snapshot.values } }, pending: null, outcome: null, dateContext: null, dateTimeZone: null, submissionContext: null, uncertain: false };
}

export function updateReadingLogSnapshot(session: ReadingSession, snapshot: ReadingSnapshot) {
    session.snapshot = { ...snapshot, values: { ...snapshot.values } };
}

export function updateReadingLogDateContext(session: ReadingSession, dates: ReadingDates) {
    session.dateContext = { ...dates };
}

export function initializeReadingLogForm(session: ReadingSession, config: { dates: ReadingDates }) {
    const configIsNewer = session.dateContext && config.dates.today > session.dateContext.today;
    const dates = { ...(configIsNewer || !session.dateContext ? config.dates : session.dateContext) };
    updateReadingLogDateContext(session, dates);
    const values = { ...session.snapshot.values };
    if (!session.pending && !session.uncertain && ![dates.today, dates.yesterday].includes(values.date_read)) {
        values.date_read = dates.today;
        updateReadingLogSnapshot(session, { ...session.snapshot, values });
    }

    return { dates, values };
}

export function setReadingLogUncertain(session: ReadingSession, uncertain: boolean) {
    session.uncertain = uncertain;
}

export function consumeReadingLogOutcome(session: ReadingSession) {
    const outcome = session.outcome;
    session.outcome = null;
    if (outcome) session.submissionContext = null;
    return outcome;
}

export function isReadingPresentationCurrent(submission: ReadingSubmissionContext, current: Omit<ReadingSubmissionContext, 'submittedValues'>): boolean {
    return submission.userId === current.userId
        && submission.locale === current.locale
        && submission.timeZone === current.timeZone
        && submission.includeDeuterocanonical === current.includeDeuterocanonical;
}

export function readingValidationPresentation(outcome: Extract<ReadingOutcome, { kind: 'validation' }>, locale: string, retryMessage: string) {
    const currentLanguage = outcome.submission.locale === locale;
    return {
        errors: currentLanguage ? outcome.errors : {},
        errorScopes: currentLanguage ? outcome.errorScopes : {},
        notice: currentLanguage ? '' : retryMessage,
    };
}

export function readingSuccessPresentation(outcome: Extract<ReadingOutcome, { kind: 'success' }>, current: Omit<ReadingSubmissionContext, 'submittedValues'>, latest: { books: BibleBook[]; recentBooks: BibleBook[]; dates: ReadingDates; values: ReadingValues }) {
    const currentPresentation = isReadingPresentationCurrent(outcome.submission, current);
    const books = currentPresentation ? outcome.data.form.books : latest.books;
    const submitted = outcome.submission.submittedValues;
    const book = books.find((item) => String(item.id) === String(submitted.book_id));
    const end = submitted.end_chapter && submitted.end_chapter !== submitted.start_chapter ? `–${submitted.end_chapter}` : '';
    return {
        current: currentPresentation,
        books,
        recentBooks: currentPresentation ? outcome.data.form.recent_books : latest.recentBooks,
        dates: currentPresentation ? outcome.data.form.dates : latest.dates,
        values: currentPresentation ? outcome.data.form.values : {
            ...latest.values,
            book_id: '',
            start_chapter: '',
            end_chapter: '',
            date_read: latest.dates.today,
            notes_text: '',
        },
        celebration: currentPresentation ? outcome.data.celebration : null,
        reference: book ? `${book.name} ${submitted.start_chapter}${end}` : null,
    };
}

export function submitReadingLog(session: ReadingSession, values: ReadingValues, { url, csrfToken, fetcher = globalThis.fetch, onSaved = () => {}, context = { userId: 0, locale: 'en', timeZone: 'UTC', includeDeuterocanonical: false } }: { url: string; csrfToken: string; fetcher?: typeof fetch; onSaved?: () => void; context?: Omit<ReadingSubmissionContext, 'submittedValues'> }): Promise<ReadingOutcome> {
    if (session.pending) return session.pending;
    if (session.uncertain) return Promise.resolve<ReadingOutcome>({ kind: 'uncertain' });

    const submitted = { ...values };
    const submission: ReadingSubmissionContext = { ...context, submittedValues: submitted };
    session.submissionContext = submission;
    session.pending = (async (): Promise<ReadingOutcome> => {
        let outcome: ReadingOutcome;
        try {
            const response = await fetcher(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(submitted),
            });
            if (response.redirected || [401, 419].includes(response.status)) {
                outcome = { kind: 'session' };
            } else if (response.status === 422) {
                const data = await response.json() as { errors?: ReadingErrors; error_scopes?: ReadingErrorScopes; form?: { dates?: ReadingDates; values?: Partial<ReadingValues> } };
                outcome = { kind: 'validation', errors: data.errors ?? {}, errorScopes: data.error_scopes ?? {}, submission };
                if (data.form?.dates && data.form?.values?.date_read) {
                    updateReadingLogDateContext(session, data.form.dates);
                    updateReadingLogSnapshot(session, {
                        ...session.snapshot,
                        values: { ...session.snapshot.values, date_read: data.form.values.date_read },
                    });
                }
            } else if (!response.ok) {
                outcome = { kind: response.status >= 500 ? 'uncertain' : 'failure' };
            } else {
                const data = await response.json() as ReadingSuccess;
                if (typeof data.reading?.date_read !== 'string' || typeof data.reading?.passage_text !== 'string'
                    || !data.form?.values || !data.form?.dates || !Array.isArray(data.form?.books)
                    || !Array.isArray(data.form?.recent_books) || typeof data.message !== 'string'
                    || !Array.isArray(data.celebration?.earned) || !Array.isArray(data.celebration?.progress)) {
                    throw new Error('Unrecognized reading response');
                }
                updateReadingLogDateContext(session, data.form.dates);
                updateReadingLogSnapshot(session, { values: { ...data.form.values }, collection: 'old', notesOpen: false });
                outcome = { kind: 'success', data, submission };
                onSaved();
            }
        } catch {
            outcome = { kind: 'uncertain' };
        }
        setReadingLogUncertain(session, outcome.kind === 'uncertain');
        session.outcome = outcome;
        return outcome;
    })().finally(() => { session.pending = null; });
    return session.pending;
}
