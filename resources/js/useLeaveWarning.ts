import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

export function useLeaveWarning(needed: () => boolean, message: string) {
    let removeBeforeVisit: (() => void) | undefined;

    function beforeUnload(event: BeforeUnloadEvent) {
        if (!needed()) return;
        event.preventDefault();
        event.returnValue = '';
    }

    onMounted(() => {
        window.addEventListener('beforeunload', beforeUnload);
        removeBeforeVisit = router.on('before', event => {
            if (event.detail.visit.method === 'get' && needed() && !window.confirm(message)) event.preventDefault();
        });
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', beforeUnload);
        removeBeforeVisit?.();
    });
}
