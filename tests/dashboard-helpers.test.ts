import assert from 'node:assert/strict';
import test from 'node:test';
import { loadDashboard, milestoneProgress, readingChart } from '../resources/js/dashboard-helpers.ts';
import type { DashboardGoal, DashboardProps } from '../resources/js/dashboard-types.ts';

function goal(key: string, current: number, target: number): DashboardGoal {
    return { key, contextKey: 'test', displayName: 'Test goal', description: '', icon: '', style: '', current, target, progressPercent: 0 };
}

function dashboardProps(): DashboardProps {
    return {
        hasReadToday: false,
        badgeBaseUrl: '/images/achievements',
        streak: {
            currentStreak: 0,
            longestStreak: 0,
            activity14DayCounts: [],
            currentStreakCounts: [],
            presentation: {
                state: 'inactive', classes: { showIcon: false }, message: '', tone: 'default',
                label: null, showCta: false, recordStatus: 'none', recordJustBroken: false,
            },
        },
        summary: { daysRead: 0, totalChapters: 0, bookCompletions: 0, averageChaptersPerDay: 0, elapsedDays: 0 },
        calendar: { monthName: 'January', days: [], thisMonthReadings: 0, thisMonthChapters: 0, daysPassedInMonth: 1 },
        bookProgress: {
            bible_completions: 0, first_coverage_percent: 0, next_completion_progress_percent: 0,
            old_testament: { processed_books: [], completed_books: 0, in_progress_books: 0, not_started_books: 39 },
            new_testament: { processed_books: [], completed_books: 0, in_progress_books: 0, not_started_books: 27 },
            deuterocanonical: null,
        },
        recentActivity: [],
        milestone: { latest: null, milestone: null },
        recap: { show: false, year: 2026, endLabel: '', isFinal: false, url: '/recap/2026' },
        onboarding: { show: false, canRemind: true, dismissUrl: '/onboarding/dismiss', remindUrl: '/onboarding/remind', plansUrl: '/plans' },
    };
}

function response(body: unknown, options: { status?: number; redirected?: boolean } = {}): Response {
    const result = new Response(JSON.stringify(body), { status: options.status ?? 200 });
    if (options.redirected) Object.defineProperty(result, 'redirected', { value: true });
    return result;
}

test('readingChart keeps a stable zero scale and adds headroom for nonzero counts', () => {
    assert.deepEqual(readingChart([{ date: '2026-01-01', count: 0 }, { date: '2026-01-02', count: 0 }]), {
        max: 4,
        points: '20,48 280,48',
    });
    assert.equal(readingChart([{ date: '2026-01-01', count: 17 }]).max, 20);
    assert.deepEqual(readingChart([{ date: '2026-01-01', count: 2 }, { date: '2026-01-02', count: 6 }]), {
        max: 6,
        points: '20,33.333333333333336 280,4',
    });
});

test('milestoneProgress formats chapter, book, reading, and Bible completion units', () => {
    assert.equal(milestoneProgress(goal('book_completed', 50, 50)), '50 of 50 chapters');
    assert.equal(milestoneProgress(goal('bible_completion', 34, 1189)), '34 of 1189 chapters');
    assert.equal(milestoneProgress(goal('testament_completed', 1, 1)), '1 of 1 book');
    assert.equal(milestoneProgress(goal('first_reading', 1, 1)), '1 of 1 reading');
    assert.equal(milestoneProgress(goal('streak', 2, 7)), '2 of 7 days');
    assert.equal(milestoneProgress(goal('bible_progress', 42, 70)), '42% read · Goal 70%');
});

test('loadDashboard distinguishes expired sessions, HTTP failures, malformed responses, and retries', async () => {
    const controller = new AbortController();
    const sessionError = /Your session has expired/;
    const refreshError = /Your previous overview is still here/;
    const malformedError = /The Dashboard could not be updated\. Try again/;

    await assert.rejects(loadDashboard('/dashboard', controller.signal, async () => response({}, { status: 419 })), sessionError);
    await assert.rejects(loadDashboard('/dashboard', controller.signal, async () => response({}, { redirected: true })), sessionError);
    await assert.rejects(loadDashboard('/dashboard', controller.signal, async () => response({}, { status: 503 })), refreshError);
    await assert.rejects(loadDashboard('/dashboard', controller.signal, async () => response(null)), malformedError);
    await assert.rejects(loadDashboard('/dashboard', controller.signal, async () => response({ hasReadToday: false })), malformedError);

    let attempts = 0;
    const fetcher = async () => {
        attempts += 1;
        return attempts === 1 ? response({}, { status: 503 }) : response(dashboardProps());
    };

    await assert.rejects(loadDashboard('/dashboard', controller.signal, fetcher), refreshError);
    const retried = await loadDashboard('/dashboard', controller.signal, fetcher);

    assert.equal(attempts, 2);
    assert.equal(retried.hasReadToday, false);
});
