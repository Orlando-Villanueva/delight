(() => {
    if (!('serviceWorker' in navigator)) return;

    const publish = (worker) => worker?.postMessage({
        type: 'DELIGHT_LOCALE',
        locale: document.documentElement.lang.toLowerCase().startsWith('fr') ? 'fr' : 'en',
    });
    const sync = () => {
        publish(navigator.serviceWorker.controller);
        navigator.serviceWorker.ready.then((registration) => publish(registration.active)).catch(() => {});
    };

    navigator.serviceWorker.addEventListener('controllerchange', sync);
    navigator.serviceWorker.register('/sw.js').then(sync).catch(() => {});
    sync();
})();
