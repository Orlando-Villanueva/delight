import test from 'node:test';
import { createNoticeTimer } from '../../resources/js/components/transient-notice-timer.js';
import assert from 'node:assert/strict';
import { createReadingLogSession, submitReadingLog } from '../../resources/js/components/reading-log-session.js';
import { installReadingLogIslands } from '../../resources/js/components/reading-log-lifecycle.js';

const draft = () => ({ values: { book_id: '43', start_chapter: '3', end_chapter: '1', date_read: '2026-10-14', notes_text: 'Private draft' }, collection: 'new', notesOpen: true });
const success = () => ({ message: 'John 1 recorded', form: { values: { book_id: '', start_chapter: '', end_chapter: '', date_read: '2026-10-14', notes_text: '' }, dates: { today: '2026-10-14', yesterday: '2026-10-13' }, books: [], recent_books: [] }, celebration_html: '' });
const response = (status, data) => ({ status, ok: status >= 200 && status < 300, json: async () => data });
const options = (fetcher) => ({ url: '/logs', csrfToken: 'test-csrf', fetcher });

test('coalesces concurrent saves and resets only on confirmed success', async () => {
    const session = createReadingLogSession(draft());
    let resolve;
    let calls = 0;
    let notified = 0;
    const opts = { ...options(async (url, request) => {
        calls++;
        assert.equal(url, '/logs');
        assert.equal(request.headers.Accept, 'application/json');
        assert.equal(request.headers['X-CSRF-TOKEN'], 'test-csrf');
        assert.equal(request.credentials, 'same-origin');
        assert.deepEqual(JSON.parse(request.body), draft().values);
        return new Promise((done) => { resolve = done; });
    }), onSaved: () => notified++ };
    const first = submitReadingLog(session, session.snapshot.values, opts);
    const second = submitReadingLog(session, session.snapshot.values, opts);
    assert.equal(first, second);
    assert.equal(calls, 1);
    assert.equal(session.snapshot.values.notes_text, 'Private draft');
    resolve(response(200, success()));
    assert.equal((await first).kind, 'success');
    assert.equal(session.snapshot.values.notes_text, '');
    assert.equal(session.snapshot.notesOpen, false);
    assert.equal(session.pending, null);
    assert.equal(notified, 1);
});

test('retains the draft and field errors after server validation', async () => {
    const session = createReadingLogSession(draft());
    const errors = { start_chapter: ['Invalid chapter range for the selected book.'] };
    const outcome = await submitReadingLog(session, session.snapshot.values, options(async () => response(422, { errors })));
    assert.deepEqual(outcome, { kind: 'validation', errors, errorScopes: {} });
    assert.deepEqual(session.snapshot, draft());
    assert.equal(session.uncertain, false);
});

for (const [label, fetcher] of [
    ['lost response', async () => { throw new TypeError('Network failure'); }],
    ['server error', async () => response(500, {})],
    ['malformed success', async () => response(200, {})],
]) {
    test(`requires an explicit decision before retrying an uncertain ${label}`, async () => {
        const session = createReadingLogSession(draft());
        let attempts = 0;
        const opts = options(async (...args) => { attempts++; return fetcher(...args); });
        assert.equal((await submitReadingLog(session, session.snapshot.values, opts)).kind, 'uncertain');
        assert.equal(session.uncertain, true);
        assert.deepEqual(session.snapshot, draft());
        await submitReadingLog(session, session.snapshot.values, opts);
        assert.equal(attempts, 1);
        session.uncertain = false;
        await submitReadingLog(session, session.snapshot.values, opts);
        assert.equal(attempts, 2);
    });
}

for (const status of [401, 419, 403, 429]) {
    test(`keeps values after HTTP ${status}`, async () => {
        const session = createReadingLogSession(draft());
        const outcome = await submitReadingLog(session, session.snapshot.values, options(async () => response(status, {})));
        assert.equal(outcome.kind, [401, 419].includes(status) ? 'session' : 'failure');
        assert.deepEqual(session.snapshot, draft());
    });
}

function environment() {
    const config = { userId: '1', values: draft().values, books: [{ id: 43, testament: 'new' }], errors: {} };
    const root = () => ({ dataset: { props: JSON.stringify(config) } });
    const document = Object.assign(new EventTarget(), { readyState: 'complete', root: root(), querySelectorAll() { return this.root ? [this.root] : []; } });
    const window = new EventTarget();
    const history = { state: {}, replaceState(state) { this.state = state; } };
    const location = { href: 'http://delight.test/logs/create' };
    const apps = [];
    const createApp = (component, props) => {
        const app = { props, unmounted: 0, mount() {}, unmount() { this.unmounted++; } };
        apps.push(app);
        return app;
    };
    const fire = (target, name, detail = {}) => target.dispatchEvent(Object.assign(new Event(name), { detail }));
    installReadingLogIslands(createApp, {}, { document, window, history, location });
    return { config, document, window, history, location, apps, root, fire };
}

test('mounts once per root and restores the correct draft per history entry', () => {
    const env = environment();
    const { document, window, history, apps, root, fire } = env;
    const firstSession = apps[0].props.session;
    firstSession.snapshot.values.notes_text = 'First entry draft';
    const firstKey = history.state.readingLogEntry;
    fire(document, 'htmx:afterSwap');
    assert.equal(apps.length, 1);

    // HTMX overwrites state when saving a page, before announcing the history update.
    history.state = { htmx: true };
    fire(document, 'htmx:beforeHistoryUpdate');
    assert.equal(history.state.readingLogEntry, firstKey);
    history.state = { htmx: true };
    fire(document, 'htmx:pushedIntoHistory');
    assert.notEqual(history.state.readingLogEntry, firstKey);
    fire(document, 'htmx:beforeCleanupElement', { elt: document.root });
    assert.equal(apps[0].unmounted, 1);
    document.root = root();
    fire(document, 'htmx:afterSwap');
    assert.notEqual(apps[1].props.session, firstSession);

    window.dispatchEvent(Object.assign(new Event('popstate'), { state: { htmx: true, readingLogEntry: firstKey } }));
    fire(document, 'htmx:beforeCleanupElement', { elt: document.root });
    history.state = { htmx: true };
    document.root = root();
    fire(document, 'htmx:afterSwap');
    fire(document, 'htmx:historyRestore');
    assert.equal(apps.length, 3);
    assert.equal(apps[2].props.session, firstSession);
    assert.equal(apps[2].props.session.snapshot.values.notes_text, 'First entry draft');
    assert.equal(history.state.readingLogEntry, firstKey);
});

test('keeps drafts separate for different authenticated users', () => {
    const { document, apps, root, config, fire } = environment();
    apps[0].props.session.snapshot.values.notes_text = 'User one only';
    fire(document, 'htmx:beforeCleanupElement', { elt: document.root });
    config.userId = '2';
    document.root = root();
    fire(document, 'htmx:afterSwap');
    assert.notEqual(apps[0].props.session, apps[1].props.session);
    assert.equal(apps[1].props.session.snapshot.values.notes_text, 'Private draft');
});

function noticeClock() {
    let time = 0;
    let sequence = 0;
    const tasks = new Map();
    let expired = 0;
    const timer = createNoticeTimer(() => expired++, 7000, {
        now: () => time,
        setTimer: (callback, delay) => { const id = ++sequence; tasks.set(id, { callback, at: time + delay }); return id; },
        clearTimer: (id) => tasks.delete(id),
    });
    return { timer, expired: () => expired, tick(ms) {
        const target = time + ms;
        while (true) {
            const next = [...tasks].sort((a, b) => a[1].at - b[1].at)[0];
            if (!next || next[1].at > target) break;
            time = next[1].at;
            tasks.delete(next[0]);
            next[1].callback();
        }
        time = target;
    } };
}

test('dismisses a notice after seven seconds and cancels timers on cleanup', () => {
    const clock = noticeClock();
    clock.timer.restart();
    clock.tick(6999);
    assert.equal(clock.expired(), 0);
    clock.tick(1);
    assert.equal(clock.expired(), 1);
    clock.timer.restart();
    clock.timer.stop();
    clock.tick(10000);
    assert.equal(clock.expired(), 1);
});

test('pauses independently for pointer, keyboard focus and the background tab', () => {
    const clock = noticeClock();
    clock.timer.restart();
    clock.tick(2000);
    clock.timer.pause('pointer');
    clock.timer.pause('focus');
    clock.tick(20000);
    clock.timer.resume('pointer');
    clock.tick(20000);
    assert.equal(clock.expired(), 0);
    clock.timer.pause('background');
    clock.timer.resume('focus');
    clock.tick(20000);
    assert.equal(clock.expired(), 0);
    clock.timer.resume('background');
    clock.tick(4999);
    assert.equal(clock.expired(), 0);
    clock.tick(1);
    assert.equal(clock.expired(), 1);
});

test('waits for a celebration to close before starting the notice countdown', () => {
    const clock = noticeClock();
    clock.timer.pause('celebration');
    clock.timer.restart();
    clock.tick(60000);
    assert.equal(clock.expired(), 0);
    clock.timer.resume('celebration');
    clock.tick(7000);
    assert.equal(clock.expired(), 1);
});

test('restarts the dismissal window when a newer confirmation replaces the message', () => {
    const clock = noticeClock();
    clock.timer.restart();
    clock.tick(6000);
    clock.timer.restart();
    clock.tick(6999);
    assert.equal(clock.expired(), 0);
    clock.tick(1);
    assert.equal(clock.expired(), 1);
});

test('preserves shared error scope metadata alongside the existing field error contract', async () => {
    const session = createReadingLogSession(draft());
    const errors = { start_chapter: ['Invalid chapter range for the selected book.'] };
    const error_scopes = { start_chapter: 'chapter_range' };
    const outcome = await submitReadingLog(session, session.snapshot.values, options(async () => response(422, { errors, error_scopes })));
    assert.deepEqual(outcome, { kind: 'validation', errors, errorScopes: error_scopes });
    assert.deepEqual(session.snapshot, draft());
});
