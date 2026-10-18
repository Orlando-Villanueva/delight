import '@/bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import ReaderLayout from '@/components/ReaderLayout.vue';

// Legacy pages can log out without Inertia; do not carry account history into them.
window.addEventListener('pagehide', () => router.clearHistory());

createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob('./pages/*.vue');
        const page = await pages[`./pages/${name}.vue`]();
        page.default.layout = ReaderLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
