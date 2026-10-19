/**
 * Minimal service worker.
 *
 * This exists only to satisfy browsers' "installability" criteria for
 * Add to Home Screen / Install App (which is what lets the site be pinned
 * to the Windows taskbar with its own icon and window). ARS is a fully
 * dynamic, session-authenticated app, so this intentionally does NOT cache
 * pages, forms, or API responses — every request passes straight through
 * to the network. Caching here would risk serving stale/expired-session
 * content, which is worse than no offline support at all for this app.
 */

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});