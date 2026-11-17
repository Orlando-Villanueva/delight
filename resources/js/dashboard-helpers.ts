import type { BookProgress, DashboardGoal, DashboardProps, ReadingActivityPoint } from './dashboard-types.ts';

export function readingChart(series: ReadingActivityPoint[]) {
    const rawMax = Math.max(4, ...series.map(point => point.count));
    const step = rawMax > 10 ? 5 : 2;
    const max = Math.ceil(rawMax / step) * step;
    const points = series.map((point, index) => `${20 + index / Math.max(1, series.length - 1) * 260},${48 - point.count / max * 44}`).join(' ');
    return { max, points };
}

export function milestoneProgress(goal: DashboardGoal, locale = 'en') {
    if (goal.key.startsWith('bible_progress')) return locale.startsWith('fr') ? `${goal.current} % lus · Objectif ${goal.target} %` : `${goal.current}% read · Goal ${goal.target}%`;
    const unit = ['book_completed', 'bible_completion'].includes(goal.key) ? 'chapters'
        : goal.key === 'testament_completed' ? 'books'
            : goal.key === 'first_reading' ? 'readings' : 'days';
    if (locale.startsWith('fr')) {
        const frenchUnits: Record<string, string> = { chapters: 'chapitre', books: 'livre', readings: 'lecture', days: 'jour' };
        const frenchUnit = frenchUnits[unit] ?? 'jour';
        return `${goal.current} sur ${goal.target} ${frenchUnit}${goal.target > 1 ? 's' : ''}`;
    }
    return `${goal.current} of ${goal.target} ${goal.target === 1 ? unit.slice(0, -1) : unit}`;
}

export function bookDetails(book: BookProgress, locale = 'en') {
    if (locale.startsWith('fr')) {
        return book.completed_count > 0
            ? `${book.name} : terminé ${book.completed_count} fois; ${book.next_completion_chapters} chapitre${book.next_completion_chapters > 1 ? 's' : ''} sur ${book.chapter_count} avant la prochaine réussite`
            : `${book.name} : ${book.chapters_read} chapitre${book.chapters_read > 1 ? 's' : ''} sur ${book.chapter_count} lu${book.chapters_read > 1 ? 's' : ''} au moins une fois (${book.percentage} %)`;
    }
    return book.completed_count > 0
        ? `${book.name}: completed ${book.completed_count} ${book.completed_count === 1 ? 'time' : 'times'}; ${book.next_completion_chapters} of ${book.chapter_count} chapters toward the next completion`
        : `${book.name}: ${book.chapters_read} of ${book.chapter_count} chapters read at least once (${book.percentage}%)`;
}

export function ordinal(number: number, locale = 'en') {
    if (locale.startsWith('fr')) return `${number}${number === 1 ? 're' : 'e'}`;
    const lastTwo = number % 100;
    const suffix = lastTwo >= 11 && lastTwo <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[number % 10] ?? 'th');
    return `${number}${suffix}`;
}

export type DashboardErrors = { expired: string; unable: string; tryAgain: string };
const englishErrors: DashboardErrors = {
    expired: 'Your session has expired. Refresh the page to sign in again.',
    unable: 'The Dashboard could not be updated. Your previous overview is still here.',
    tryAgain: 'The Dashboard could not be updated. Try again.',
};

export async function loadDashboard(url: string, signal: AbortSignal, fetcher = globalThis.fetch, errors: DashboardErrors = englishErrors): Promise<DashboardProps> {
    let response: Response;
    try {
        response = await fetcher(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal });
    } catch {
        throw new Error(errors.tryAgain);
    }
    if (response.redirected || [401, 419].includes(response.status)) throw new Error(errors.expired);
    if (!response.ok) throw new Error(errors.unable);
    try {
        const data = await response.json() as DashboardProps;
        if (!data || typeof data !== 'object' || typeof data.hasReadToday !== 'boolean' || typeof data.badgeBaseUrl !== 'string'
            || typeof data.streak?.currentStreak !== 'number' || !Array.isArray(data.streak?.activity14DayCounts)
            || !data.streak.presentation || typeof data.summary?.totalChapters !== 'number'
            || !Array.isArray(data.calendar?.days) || !Array.isArray(data.bookProgress?.old_testament?.processed_books)
            || !Array.isArray(data.bookProgress?.new_testament?.processed_books) || !Array.isArray(data.recentActivity)
            || !data.milestone || !data.onboarding || !data.recap) throw new Error(errors.tryAgain);
        return data;
    } catch {
        throw new Error(errors.tryAgain);
    }
}
