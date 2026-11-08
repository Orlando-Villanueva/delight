import type { ReadingDates, ReadingErrors, ReadingErrorScopes, ReadingOutcome, ReadingSession, ReadingSnapshot, ReadingSuccess, ReadingValues } from './reading-log-types.ts';

export function createReadingLogSession(snapshot: ReadingSnapshot): ReadingSession {
    return { snapshot: structuredClone(snapshot), pending: null, outcome: null, dateContext: null, uncertain: false };
}

export function updateReadingLogSnapshot(session: ReadingSession, snapshot: ReadingSnapshot) {
    session.snapshot = structuredClone(snapshot);
}

export function updateReadingLogDateContext(session: ReadingSession, dates: ReadingDates) {
    session.dateContext = structuredClone(dates);
}

export function initializeReadingLogForm(session: ReadingSession, config: { dates: ReadingDates }) {
    const configIsNewer = session.dateContext && config.dates.today > session.dateContext.today;
    const dates = { ...(configIsNewer || !session.dateContext ? config.dates : session.dateContext) };
    updateReadingLogDateContext(session, dates);
    const values = structuredClone(session.snapshot.values);
    if (![dates.today, dates.yesterday].includes(values.date_read)) {
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
    return outcome;
}

export function submitReadingLog(session: ReadingSession, values: ReadingValues, { url, csrfToken, fetcher = globalThis.fetch, onSaved = () => {} }: { url: string; csrfToken: string; fetcher?: typeof fetch; onSaved?: () => void }): Promise<ReadingOutcome> {
    if (session.pending) return session.pending;
    if (session.uncertain) return Promise.resolve<ReadingOutcome>({ kind: 'uncertain' });

    const submitted = { ...values };
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
                outcome = { kind: 'validation', errors: data.errors ?? {}, errorScopes: data.error_scopes ?? {} };
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
                if (!data.form?.values || !data.form?.dates || !Array.isArray(data.form?.books)
                    || !Array.isArray(data.form?.recent_books) || typeof data.message !== 'string'
                    || !Array.isArray(data.celebration?.earned) || !Array.isArray(data.celebration?.progress)) {
                    throw new Error('Unrecognized reading response');
                }
                updateReadingLogDateContext(session, data.form.dates);
                updateReadingLogSnapshot(session, { values: { ...data.form.values }, collection: 'old', notesOpen: false });
                outcome = { kind: 'success', data };
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
