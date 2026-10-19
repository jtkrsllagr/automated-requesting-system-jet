/**
 * Registers the service worker once the page has fully loaded. Deferred to
 * the 'load' event so window.ARS_BASE (set by an inline script near the end
 * of <body>) is guaranteed to already be defined — needed so this still
 * works correctly whether the app is deployed at the domain root or under a
 * subpath (see config/app.php).
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        var base = window.ARS_BASE || '';
        navigator.serviceWorker
            .register(base + '/service-worker.js', { scope: base + '/' })
            .catch(function (err) {
                console.warn('Service worker registration failed:', err);
            });
    });
}