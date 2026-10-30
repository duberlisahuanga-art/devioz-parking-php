/* Cache only a public offline notice. Never cache sessions, pages or media. */
'use strict';
const prefix = 'devioz-camera-' + encodeURIComponent(self.registration.scope) + '-';
const cacheName = prefix + 'v1';
const offlineUrl = new URL('./offline.html', self.registration.scope).href;
self.addEventListener('install', event => {
    event.waitUntil(caches.open(cacheName).then(cache => cache.add(offlineUrl)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(
        keys.filter(key => key.startsWith(prefix) && key !== cacheName).map(key => caches.delete(key))
    )).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET' || event.request.mode !== 'navigate') return;
    const target = new URL(event.request.url);
    if (target.origin !== self.location.origin || !target.href.startsWith(self.registration.scope)) return;
    event.respondWith(fetch(event.request, { cache: 'no-store' }).catch(async () =>
        (await caches.match(offlineUrl)) || new Response('Sin conexión. Las cámaras necesitan acceso a la red.', {
            status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store' }
        })
    ));
});
