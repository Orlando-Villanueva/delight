import type { HistoryDay, HistoryMutation, HistorySlice } from './history-types.ts';

type Failure = { kind: 'failure' | 'session' | 'stale' | 'uncertain'; message: string };
export type HistoryMessages = { expired: string; load: string; changed: string; checkChanges: string; cannotChange: string; uncertain: string };
const englishMessages: HistoryMessages = {
    expired: 'Your session has expired. Refresh History to sign in again.', load: 'Older readings could not be loaded. Your loaded readings are still here.',
    changed: 'This reading has changed. Refresh History and open it again.', checkChanges: 'Check your changes and try again.',
    cannotChange: 'This change could not be made. Check your access and try again.',
    uncertain: 'We could not confirm this change. Refresh History to check what was saved before trying again.',
};
export type MutationOutcome = { kind: 'success'; data: HistoryMutation } | Failure | { kind: 'validation'; message: string };

function isDay(value: unknown): value is HistoryDay {
    if (!value || typeof value !== 'object') return false;
    const day = value as HistoryDay;
    return typeof day.date === 'string' && typeof day.label === 'string' && Array.isArray(day.groups)
        && day.groups.every(group => typeof group.id === 'number' && typeof group.passage === 'string'
            && (group.notes === null || typeof group.notes === 'string') && typeof group.chapterCount === 'number'
            && typeof group.loggedAt === 'string' && typeof group.timeLabel === 'string'
            && typeof group.noteUrl === 'string' && typeof group.deleteUrl === 'string' && typeof group.deleteChaptersUrl === 'string'
            && Array.isArray(group.chapters) && group.chapters.every(chapter => typeof chapter.id === 'number' && typeof chapter.chapter === 'number'));
}

export function mergeHistoryDays(existing: HistoryDay[], incoming: HistoryDay[]) {
    return [...new Map([...existing, ...incoming].map(day => [day.date, day])).values()]
        .sort((left, right) => right.date.localeCompare(left.date));
}

export async function loadHistorySlice(url: string, signal: AbortSignal, fetcher = globalThis.fetch, messages: HistoryMessages = englishMessages): Promise<HistorySlice> {
    let response: Response;
    try {
        response = await fetcher(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal });
    } catch {
        throw new Error(messages.load);
    }
    if (response.redirected || [401, 419].includes(response.status)) throw new Error(messages.expired);
    if (!response.ok) throw new Error(messages.load);
    try {
        const data = await response.json() as HistorySlice;
        if (!data || typeof data !== 'object' || !Array.isArray(data.days) || !data.days.every(isDay) || (data.nextUrl !== null && typeof data.nextUrl !== 'string') || typeof data.hasLogs !== 'boolean') {
            throw new Error(messages.load);
        }
        return data;
    } catch {
        throw new Error(messages.load);
    }
}

export async function mutateHistory(url: string, method: 'PATCH' | 'DELETE', body: object, csrfToken: string, fetcher = globalThis.fetch, messages: HistoryMessages = englishMessages): Promise<MutationOutcome> {
    try {
        const response = await fetcher(url, {
            method, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(body),
        });
        if (response.redirected || [401, 419].includes(response.status)) return { kind: 'session', message: messages.expired };
        if ([404, 409].includes(response.status)) return { kind: 'stale', message: messages.changed };
        if (response.status === 422) {
            const data = await response.json() as { errors?: Record<string, string[]> };
            return { kind: 'validation', message: Object.values(data.errors ?? {}).flat().join(' ') || messages.checkChanges };
        }
        if (!response.ok && response.status < 500) return { kind: 'failure', message: messages.cannotChange };
        if (!response.ok) throw new Error('Unconfirmed change');
        const data = await response.json() as HistoryMutation;
        if (typeof data.date !== 'string' || typeof data.today !== 'string' || typeof data.hasLogs !== 'boolean'
            || (data.day !== null && (!isDay(data.day) || data.day.date !== data.date))) throw new Error('Unrecognized change');
        return { kind: 'success', data };
    } catch {
        return { kind: 'uncertain', message: messages.uncertain };
    }
}
