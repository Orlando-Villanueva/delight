import test from 'node:test';
import assert from 'node:assert/strict';
import { loadHistorySlice, mergeHistoryDays, mutateHistory } from '../resources/js/history-requests.ts';

const day = (date = '2026-10-18') => ({ date, label: 'Oct 18, 2026', groups: [{ id: 1, passage: 'John 1-2', notes: 'Reflection', chapterCount: 2, loggedAt: '2026-10-18T12:00:00Z', timeLabel: '8:00 AM', chapters: [{ id: 1, chapter: 1 }, { id: 2, chapter: 2 }], noteUrl: '/logs/1/notes', deleteUrl: '/logs/1', deleteChaptersUrl: '/logs/1/chapters' }] });
const response = (status, data) => ({ status, ok: status >= 200 && status < 300, json: async () => data });
const mutation = () => ({ day: day(), date: '2026-10-18', today: '2026-10-23', hasLogs: true });

test('failed removal reports stale selection and validation recovery without changing the selection', async () => {
    const selection = { group_ids: [1, 2], log_ids: [2] };
    const stale = await mutateHistory('/logs/1/chapters', 'DELETE', selection, 'csrf', async () => response(409, {}));
    assert.equal(stale.kind, 'stale');
    assert.match(stale.message, /Refresh History/);
    const invalid = await mutateHistory('/logs/1/chapters', 'DELETE', selection, 'csrf', async () => response(422, { errors: { log_ids: ['Select valid chapters.'] } }));
    assert.deepEqual(invalid, { kind: 'validation', message: 'Select valid chapters.' });
    assert.deepEqual(selection.log_ids, [2]);
});

test('successful removal returns the authoritative day and sends the selection with CSRF protection', async () => {
    const result = await mutateHistory('/logs/1/chapters', 'DELETE', { group_ids: [1, 2], log_ids: [2] }, 'csrf', async (url, options) => {
        assert.equal(url, '/logs/1/chapters');
        assert.equal(options.method, 'DELETE');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf');
        assert.deepEqual(JSON.parse(options.body), { group_ids: [1, 2], log_ids: [2] });
        return response(200, mutation());
    });
    assert.deepEqual(result, { kind: 'success', data: mutation() });
});

for (const [label, fetcher] of [
    ['lost response', async () => { throw new TypeError('Offline'); }],
    ['server error', async () => response(500, {})],
    ['HTML success', async () => { throw new SyntaxError('Unexpected HTML'); }],
    ['malformed success', async () => response(200, { ...mutation(), day: {} })],
    ['mismatched date', async () => response(200, { ...mutation(), date: '2026-10-17' })],
]) {
    test(`does not silently retry an uncertain ${label}`, async () => {
        let attempts = 0;
        const result = await mutateHistory('/logs/1', 'DELETE', {}, 'csrf', async (...args) => { attempts++; return fetcher(...args); });
        assert.equal(result.kind, 'uncertain');
        assert.match(result.message, /check what was saved/);
        assert.equal(attempts, 1);
    });
}

test('expired sessions require refresh instead of treating a login redirect as success', async () => {
    for (const result of [response(401, {}), response(419, {}), { ...response(200, {}), redirected: true }]) {
        assert.equal((await mutateHistory('/logs/1', 'DELETE', {}, 'csrf', async () => result)).kind, 'session');
    }
});

test('older-day loading can retry the same cursor after failure and merge without duplicates', async () => {
    const existing = [day()];
    const signal = new AbortController().signal;
    await assert.rejects(loadHistorySlice('/logs/older?before=2026-10-18', signal, async () => response(503, {})), /loaded readings are still here/);
    const older = await loadHistorySlice('/logs/older?before=2026-10-18', signal, async (url, options) => {
        assert.equal(url, '/logs/older?before=2026-10-18');
        assert.equal(options.signal, signal);
        return response(200, { days: [day('2026-10-17')], nextUrl: null, hasLogs: true });
    });
    assert.deepEqual(mergeHistoryDays(existing, [...older.days, day()]).map(item => item.date), ['2026-10-18', '2026-10-17']);
    assert.equal(existing.length, 1);
});

test('invalid append data never becomes an empty success that hides recovery', async () => {
    await assert.rejects(loadHistorySlice('/logs/older', new AbortController().signal, async () => response(200, { days: [{}], nextUrl: null, hasLogs: true })));
});
