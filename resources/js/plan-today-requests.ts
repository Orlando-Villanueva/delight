import type { PlanTodayMutation, PlanTodayState } from './plan-today-types.ts';

export type PlanReadingOutcome = { kind: 'success'; data: PlanTodayMutation } | { kind: 'failure' | 'refresh'; message: string };

function isToday(value: unknown): value is PlanTodayState {
    if (!value || typeof value !== 'object') return false;
    const state = value as PlanTodayState;
    const counts = [state.dayNumber, state.currentDay, state.totalDays, state.progress, state.completedDaysCount, state.trackedDaysCount, state.unlinkedTodayChaptersCount, state.unlinkedTodayChaptersTotal];
    return typeof state.plan?.slug === 'string' && typeof state.plan.name === 'string'
        && typeof state.subscription?.startDay === 'number' && counts.every(count => typeof count === 'number' && Number.isFinite(count))
        && [state.isComplete, state.isActive, state.isBeforeTracking, state.hasOtherActivePlan].every(flag => typeof flag === 'boolean')
        && [state.previousDay, state.nextDay].every(day => day === null || typeof day === 'number')
        && Boolean(state.urls) && ['today', 'catalogue', 'activate', 'unsubscribe', 'logChapter', 'logAll', 'applyReadings'].every(key => typeof state.urls[key as keyof PlanTodayState['urls']] === 'string')
        && (state.reading === null || Boolean(state.reading && Array.isArray(state.reading.chapters)
            && typeof state.reading.completedCount === 'number' && typeof state.reading.totalCount === 'number' && typeof state.reading.allCompleted === 'boolean'
            && state.reading.chapters.every(chapter => typeof chapter.bookId === 'number' && typeof chapter.bookName === 'string' && typeof chapter.chapter === 'number' && typeof chapter.completed === 'boolean')));
}

function isMutation(value: unknown): value is PlanTodayMutation {
    if (!value || typeof value !== 'object') return false;
    const payload = value as PlanTodayMutation;
    const celebration = payload.celebration;
    return isToday(payload.today) && (celebration === null || Boolean(celebration
        && Array.isArray(celebration.earned) && Array.isArray(celebration.progress)
        && typeof celebration.reading?.passage === 'string' && typeof celebration.reading.date === 'string'
        && (celebration.record === null || typeof celebration.record?.title === 'string')));
}

export async function mutatePlanReading(url: string, body: Record<string, number>, csrfToken: string, fetcher = globalThis.fetch): Promise<PlanReadingOutcome> {
    try {
        const response = await fetcher(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(body),
        });
        if (response.redirected || [401, 419].includes(response.status)) return { kind: 'refresh', message: 'Your session has expired. Refresh the plan to sign in again.' };
        if ([400, 403, 404, 409].includes(response.status)) return { kind: 'refresh', message: 'This plan has changed or is no longer available. Refresh the plan before continuing.' };
        if (response.status >= 500) throw new Error('Unconfirmed save');
        if (!response.ok) return { kind: 'failure', message: 'The reading could not be saved. Check your connection and access, then try again.' };
        const payload: unknown = await response.json();
        if (!isMutation(payload) || payload.today.dayNumber !== body.day) throw new Error('Unrecognized save');
        return { kind: 'success', data: payload };
    } catch {
        return { kind: 'refresh', message: 'We could not confirm whether the reading was saved. Refresh the plan state before trying another action.' };
    }
}
