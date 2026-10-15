export function createReadingLogSession(snapshot) {
    return { snapshot: structuredClone(snapshot), pending: null, uncertain: false };
}

export function submitReadingLog(session, values, { url, csrfToken, fetcher = globalThis.fetch, onSaved = () => {} }) {
    if (session.pending) return session.pending;
    if (session.uncertain) return Promise.resolve({ kind: 'uncertain' });

    const submitted = { ...values };
    session.pending = (async () => {
        let outcome;
        try {
            const response = await fetcher(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(submitted),
            });
            if (response.redirected || [401, 419].includes(response.status)) {
                outcome = { kind: 'session' };
            } else if (response.status === 422) {
                const data = await response.json();
                outcome = { kind: 'validation', errors: data.errors ?? {}, errorScopes: data.error_scopes ?? {} };
            } else if (!response.ok) {
                outcome = { kind: response.status >= 500 ? 'uncertain' : 'failure' };
            } else {
                const data = await response.json();
                if (!data.form?.values || !data.form?.dates || !Array.isArray(data.form?.books)
                    || !Array.isArray(data.form?.recent_books) || typeof data.message !== 'string'
                    || typeof data.celebration_html !== 'string') {
                    throw new Error('Unrecognized reading response');
                }
                session.snapshot = { values: { ...data.form.values }, collection: 'old', notesOpen: false };
                outcome = { kind: 'success', data };
                onSaved();
            }
        } catch {
            outcome = { kind: 'uncertain' };
        }
        session.uncertain = outcome.kind === 'uncertain';
        return outcome;
    })().finally(() => { session.pending = null; });
    return session.pending;
}
