import { createReadingLogSession } from './reading-log-session.js';

export function installReadingLogIslands(createApp, ReadingLogForm, { document, window, history, location }) {
    const mounted = new Map();
    const drafts = new Map();
    let entrySequence = 0;
    const randomWords = new Uint32Array(4);
    globalThis.crypto.getRandomValues(randomWords);
    const documentKey = `${Date.now()}:${[...randomWords].map((word) => word.toString(36)).join('')}`;
    const nextEntryKey = () => `${documentKey}:${++entrySequence}`;
    let entryKey = nextEntryKey();

    const stampEntry = () => history.replaceState({ ...history.state, readingLogEntry: entryKey }, '', location.href);
    stampEntry();

    const mount = () => {
        stampEntry();
        document.querySelectorAll('[data-reading-log-island]').forEach((root) => {
            if (mounted.has(root)) return;
            const config = JSON.parse(root.dataset.props);
            const key = `${config.userId}:${entryKey}`;
            if (!drafts.has(key)) {
                const book = config.books.find((book) => String(book.id) === config.values.book_id);
                drafts.set(key, createReadingLogSession({
                    values: config.values, collection: book?.testament ?? 'old',
                    notesOpen: Boolean(config.values.notes_text || config.errors.notes_text),
                }));
            }
            const session = drafts.get(key);
            const app = createApp(ReadingLogForm, { config, session });
            app.mount(root);
            mounted.set(root, app);
        });
    };

    document.addEventListener('htmx:beforeHistoryUpdate', stampEntry);
    document.addEventListener('htmx:pushedIntoHistory', () => { entryKey = nextEntryKey(); stampEntry(); });
    // Capture the destination key before HTMX restores the page and rewrites history.state.
    window.addEventListener('popstate', (event) => {
        entryKey = event.state?.readingLogEntry ?? nextEntryKey();
    }, true);
    document.addEventListener('htmx:beforeCleanupElement', (event) => {
        for (const [root, app] of mounted) {
            if (event.detail.elt === root || event.detail.elt.contains(root)) {
                app.unmount();
                mounted.delete(root);
            }
        }
    });
    document.addEventListener('htmx:afterSwap', mount);
    document.addEventListener('htmx:historyRestore', mount);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true });
    else mount();
}
