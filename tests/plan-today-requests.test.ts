import assert from 'node:assert/strict';
import test from 'node:test';
import { mutatePlanReading } from '../resources/js/plan-today-requests.ts';

const saved = () => ({
    today: {
        plan: { name: 'Canonical', slug: 'canonical' }, subscription: { startDay: 1 },
        reading: { chapters: [{ bookId: 1, bookName: 'Genesis', chapter: 1, completed: true }], completedCount: 1, totalCount: 1, allCompleted: true },
        dayNumber: 1, currentDay: 2, totalDays: 2, previousDay: null, nextDay: 2, progress: 50,
        isComplete: false, isActive: true, isBeforeTracking: false, hasOtherActivePlan: false,
        completedDaysCount: 1, trackedDaysCount: 2, unlinkedTodayChaptersCount: 0, unlinkedTodayChaptersTotal: 1,
        urls: { today: '/today', catalogue: '/plans', activate: '/activate', unsubscribe: '/unsubscribe', logChapter: '/chapter', logAll: '/all', applyReadings: '/apply' },
        flash: { success: null, info: null, error: null },
    },
    celebration: null,
});

test('sends one authenticated mutation and accepts refreshed chapter state', async () => {
    let requests = 0;
    const outcome = await mutatePlanReading('/chapter', { day: 1, book_id: 1, chapter: 1 }, 'csrf', async (url, request) => {
        requests++;
        assert.equal(url, '/chapter');
        assert.equal(request?.method, 'POST');
        assert.equal(request?.credentials, 'same-origin');
        assert.equal((request?.headers as Record<string, string>)['X-CSRF-TOKEN'], 'csrf');
        assert.deepEqual(JSON.parse(String(request?.body)), { day: 1, book_id: 1, chapter: 1 });
        return Response.json(saved());
    });
    assert.equal(requests, 1);
    assert.equal(outcome.kind, 'success');
    if (outcome.kind === 'success') assert.equal(outcome.data.today.reading?.chapters[0]?.completed, true);
});

test('requires refresh after ambiguous failures instead of retrying the write', async () => {
    for (const fetcher of [
        async () => { throw new Error('Connection lost'); },
        async () => new Response('Server error', { status: 500 }),
        async () => new Response('<html>Unexpected response</html>'),
        async () => Response.json({ today: null, celebration: null }),
        async () => Response.json({ ...saved(), today: { ...saved().today, dayNumber: 2 } }),
    ]) {
        assert.equal((await mutatePlanReading('/chapter', { day: 1 }, 'csrf', fetcher)).kind, 'refresh');
    }
});

test('requires refreshed access for expired sessions and stale plan state', async () => {
    for (const status of [400, 401, 403, 404, 409, 419]) {
        const outcome = await mutatePlanReading('/chapter', { day: 1 }, 'csrf', async () => new Response('', { status }));
        assert.equal(outcome.kind, 'refresh');
    }
});

test('allows a retry after a definite validation or throttle rejection', async () => {
    for (const status of [422, 429]) {
        assert.equal((await mutatePlanReading('/chapter', { day: 1 }, 'csrf', async () => new Response('', { status }))).kind, 'failure');
    }
});
