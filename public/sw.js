const CACHE_NAME = 'newbook-v2';
const PRECACHE_URLS = ['/', '/feed', '/manifest.json', '/icons/icon-192.png', '/icons/icon-512.png', '/images/newbook-logo.jpg'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.pathname.startsWith('/api/') || url.pathname.includes('/messages')) return;

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response.ok && url.origin === self.location.origin) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});

// Firebase push (optional — loaded when config exists)
try {
    importScripts('https://www.gstatic.com/firebasejs/11.6.0/firebase-app-compat.js');
    importScripts('https://www.gstatic.com/firebasejs/11.6.0/firebase-messaging-compat.js');
    importScripts('/firebase-config.js');

    if (self.firebaseConfig?.apiKey) {
        firebase.initializeApp(self.firebaseConfig);
        firebase.messaging().onBackgroundMessage((payload) => {
            const title = payload.notification?.title || 'Newbook';
            self.registration.showNotification(title, {
                body: payload.notification?.body || '',
                icon: '/icons/icon-192.png',
                data: { url: payload.data?.url || '/' },
            });
        });
    }
} catch (e) {
    // Firebase optional
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/feed';
    event.waitUntil(clients.openWindow(url));
});
