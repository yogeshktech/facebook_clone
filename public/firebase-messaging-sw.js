/* Firebase Cloud Messaging + PWA shell */
importScripts('https://www.gstatic.com/firebasejs/11.6.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/11.6.0/firebase-messaging-compat.js');
importScripts('/firebase-config.js');

const CACHE_NAME = 'newbook-v1';

function buildPushNotificationOptions(payload = {}) {
    const notification = payload?.notification || {};
    const data = payload?.data || {};
    const title = notification.title || data.title || payload?.title || 'NEWBOOK';
    const body = notification.body || data.body || payload?.body || '';
    const url = data.url || payload?.fcmOptions?.link || '/notifications';
    const tag = data.type ? `newbook-${data.type}` : (data.notification_id ? `newbook-${data.notification_id}` : 'newbook-push');

    return {
        title,
        body,
        url,
        tag,
        data: { url, ...data },
    };
}

async function showPushNotification(payload = {}) {
    const { title, body, url, tag, data } = buildPushNotificationOptions(payload);

    await self.registration.showNotification(title, {
        body,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        vibrate: [150, 80, 150, 80, 150],
        silent: false,
        renotify: true,
        tag,
        data: { url, ...data },
    });
}

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

self.addEventListener('push', (event) => {
    try {
        const payload = event.data?.json ? event.data.json() : null;
        if (payload) {
            event.waitUntil(showPushNotification(payload));
        }
    } catch (e) {
        // Ignore invalid push payloads
    }
});

if (self.firebaseConfig?.apiKey) {
    firebase.initializeApp(self.firebaseConfig);
    const messaging = firebase.messaging();

    messaging.onBackgroundMessage((payload) => showPushNotification(payload));
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(clients.openWindow(url));
});
