import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../public/sw.js', import.meta.url), 'utf8');

function worker(stores = new Map()) {
    const listeners = new Map();
    const cacheApi = {
        open: async (name) => {
            if (!stores.has(name)) stores.set(name, new Map());
            const entries = stores.get(name);
            return {
                put: async (key, response) => entries.set(key, await response.text()),
                match: async (key) => entries.has(key) ? new Response(entries.get(key)) : undefined,
            };
        },
    };
    runInNewContext(source, {
        self: { addEventListener: (type, handler) => listeners.set(type, handler) },
        caches: cacheApi, URL, Response,
        fetch: async () => { throw new Error('offline'); },
    });
    return {
        stores,
        locale: async (locale) => {
            let pending;
            listeners.get('message')({ data: { type: 'DELIGHT_LOCALE', locale }, waitUntil: (value) => { pending = value; } });
            await pending;
        },
        offline: async (fragment = false) => {
            let pending;
            listeners.get('fetch')({
                request: { method: 'GET', url: 'https://delight.test/logs/create', mode: fragment ? 'same-origin' : 'navigate', headers: new Headers(fragment ? { 'HX-Request': 'true', 'HX-Target': 'page-container' } : {}) },
                respondWith: (value) => { pending = value; },
            });
            return pending;
        },
    };
}

test('offline document defaults to English without a confirmed page language', async () => {
    const response = await worker().offline();
    assert.equal(response.status, 503);
    assert.equal(response.headers.get('cache-control'), 'no-store');
    assert.match(await response.text(), /<html lang="en">/);
});

test('offline document and fragment retain only the confirmed language across worker restarts', async () => {
    const first = worker();
    await first.locale('fr');
    assert.deepEqual([...first.stores.get('delight-locale-v1').entries()], [['/__offline-locale', 'fr']]);
    const restarted = worker(first.stores);
    const document = await (await restarted.offline()).text();
    assert.match(document, /<html lang="fr">/);
    assert.match(document, /Delight est hors ligne/);
    assert.match(document, /Réessayer/);
    assert.doesNotMatch(document, /Try again|Delight is offline/);
    const fragment = await restarted.offline(true);
    assert.equal(fragment.status, 200);
    assert.match(await fragment.text(), /<section lang="fr"/);
    await restarted.locale('unexpected');
    assert.match(await (await restarted.offline()).text(), /Delight est hors ligne/);
    await restarted.locale('en');
    assert.match(await (await restarted.offline()).text(), /Delight is offline/);
    assert.equal(first.stores.size, 1);
});

test('page language is republished to a newly controlling worker and uses the current page language', async () => {
    const messages = [];
    const listeners = new Map();
    const document = { documentElement: { lang: 'fr' } };
    const oldWorker = { postMessage: () => {} };
    const serviceWorker = {
        controller: oldWorker,
        ready: Promise.resolve({ active: oldWorker }),
        register: async () => ({}),
        addEventListener: (type, handler) => listeners.set(type, handler),
    };
    runInNewContext(readFileSync(new URL('../public/offline-locale.js', import.meta.url), 'utf8'), {
        navigator: { serviceWorker }, document,
    });
    await Promise.resolve();
    serviceWorker.controller = { postMessage: (value) => messages.push(value) };
    listeners.get('controllerchange')();
    assert.equal(messages.at(-1).locale, 'fr');
    document.documentElement.lang = 'en';
    listeners.get('controllerchange')();
    assert.equal(messages.at(-1).locale, 'en');
    assert.deepEqual(Object.keys(messages.at(-1)), ['type', 'locale']);
});
