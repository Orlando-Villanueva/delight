export function createNoticeTimer(onExpire: () => void, duration = 7000, {
    now = () => performance.now(), setTimer = setTimeout, clearTimer = clearTimeout,
}: { now?: () => number; setTimer?: typeof setTimeout; clearTimer?: typeof clearTimeout } = {}) {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let startedAt = 0;
    let remaining = duration;
    let running = false;
    const pauses = new Set<string>();
    const cancel = () => { if (timer !== null) clearTimer(timer); timer = null; };
    const schedule = () => {
        if (!running || pauses.size || timer !== null) return;
        startedAt = now();
        timer = setTimer(() => { timer = null; running = false; onExpire(); }, remaining);
    };
    return {
        restart() { cancel(); remaining = duration; running = true; schedule(); },
        stop() { cancel(); running = false; },
        pause(reason: string) {
            if (pauses.has(reason)) return;
            pauses.add(reason);
            if (timer !== null) remaining = Math.max(0, remaining - (now() - startedAt));
            cancel();
        },
        resume(reason: string) { pauses.delete(reason); schedule(); },
    };
}
