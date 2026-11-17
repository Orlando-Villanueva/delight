import '@/bootstrap';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import confetti from 'canvas-confetti';
import ReaderLayout from '@/components/ReaderLayout.vue';
import { clearReadingDraft, clearReadingDraftForDifferentAccount, hasReadingDraft } from '@/reading-drafts';

type AccountContext = { locale: string; readingTimezone: string; includeDeuterocanonical: boolean; user: { id: number } };
let confirmedContext: string | null = null;
function contextKey(reader?: AccountContext) {
    return reader ? JSON.stringify([reader.user.id, reader.locale, reader.readingTimezone, reader.includeDeuterocanonical]) : null;
}

window.confetti = confetti;

// Legacy pages can log out without Inertia; do not carry account history into them.
window.addEventListener('pagehide', () => { clearReadingDraft(); router.clearHistory(); });
window.addEventListener('beforeunload', (event) => {
    if (!hasReadingDraft()) return;
    event.preventDefault();
    event.returnValue = '';
});

createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: DefineComponent & { layout?: typeof ReaderLayout } }>('./pages/*.vue');
        const page = await pages[`./pages/${name}.vue`]();
        page.default.layout = ReaderLayout;
        return page.default;
    },
    setup({ el, App, props, plugin }) {
        confirmedContext = contextKey(props.initialPage.props.reader as AccountContext | undefined);
        router.on('success', (event) => {
            const reader = event.detail.page.props.reader as AccountContext | undefined;
            clearReadingDraftForDifferentAccount(reader?.user.id ?? null);
            confirmedContext = contextKey(reader);
        });
        router.on('navigate', (event) => {
            const context = contextKey(event.detail.page.props.reader as AccountContext | undefined);
            if (event.detail.type === 'history' && context && confirmedContext && context !== confirmedContext) {
                router.reload();
            } else if (context) {
                confirmedContext = context;
            }
        });
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
