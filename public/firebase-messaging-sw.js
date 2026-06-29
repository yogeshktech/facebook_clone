/* Firebase Cloud Messaging + PWA shell */
importScripts('https://www.gstatic.com/firebasejs/11.6.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/11.6.0/firebase-messaging-compat.js');
importScripts('/firebase-config.js');

const CACHE_NAME = 'newbook-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) =>
            cache.addAll(['/', '/feed', '/manifest.json', '/icons/icon-192.png', '/icons/icon-512.png'])
        ).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/chat/')) return;

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

if (self.firebaseConfig?.apiKey) {
    firebase.initializeApp(self.firebaseConfig);
    const messaging = firebase.messaging();

    messaging.onBackgroundMessage((payload) => {
        const title = payload.notification?.title || 'NEWBOOK';
        const body = payload.notification?.body || '';
        const url = payload.data?.url || payload.fcmOptions?.link || '/notifications';

        return self.registration.showNotification(title, {
            body,
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            vibrate: [150, 80, 150, 80, 150],
            silent: false,
            renotify: true,
            tag: payload.data?.type ? `newbook-${payload.data.type}` : 'newbook-push',
            data: { url },
        });
    });
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(clients.openWindow(url));
});
