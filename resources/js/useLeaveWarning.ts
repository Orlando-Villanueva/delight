import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

export function useLeaveWarning(needed: () => boolean, message: string | (() => string), warnOnVisit: () => boolean = needed) {
    let removeBeforeVisit: (() => void) | undefined;

    function beforeUnload(event: BeforeUnloadEvent) {
        if (!needed()) return;
        event.preventDefault();
        event.returnValue = '';
    }

    onMounted(() => {
        window.addEventListener('beforeunload', beforeUnload);
        removeBeforeVisit = router.on('before', event => {
            const prompt = typeof message === 'function' ? message() : message;
            if (event.detail.visit.method === 'get' && warnOnVisit() && !window.confirm(prompt)) event.preventDefault();
        });
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', beforeUnload);
        removeBeforeVisit?.();
    });
}
