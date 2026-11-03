import '@/bootstrap';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import confetti from 'canvas-confetti';
import ReaderLayout from '@/components/ReaderLayout.vue';

window.confetti = confetti;

// Legacy pages can log out without Inertia; do not carry account history into them.
window.addEventListener('pagehide', () => router.clearHistory());

createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: DefineComponent & { layout?: typeof ReaderLayout } }>('./pages/*.vue');
        const page = await pages[`./pages/${name}.vue`]();
        page.default.layout = ReaderLayout;
        return page.default;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
