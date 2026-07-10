const CACHE_NAME = 'newbook-v4';
const PRECACHE_URLS = ['/manifest.json', '/icons/icon-192.png', '/icons/icon-512.png', '/images/newbook-logo.jpg'];

function isRealtimePath(pathname) {
    return (
        pathname.startsWith('/api/')
        || pathname.startsWith('/chat/call/')
        || pathname.startsWith('/broadcasting/')
        || pathname.startsWith('/notifications/')
        || pathname.includes('/messages')
        || pathname.includes('/typing')
        || pathname.includes('/signal')
        || pathname.includes('/inbox')
        || pathname.includes('/presence')
    );
}

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

    // Never cache call signaling / realtime endpoints — breaks pickup→connect.
    if (isRealtimePath(url.pathname)) {
        event.respondWith(
            fetch(event.request).catch(
                () => new Response(JSON.stringify({ ok: false }), {
                    status: 503,
                    headers: { 'Content-Type': 'application/json' },
                })
            )
        );
        return;
    }

    // Cross-origin media (MinIO etc.) — network only, never SW cache.
    if (url.origin !== self.location.origin) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response && response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone)).catch(() => {});
                }
                return response;
            })
            .catch(async () => {
                const cached = await caches.match(event.request);
                if (cached) return cached;
                return new Response('', { status: 503, statusText: 'Offline' });
            })
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

// Firebase push (optional — loaded when config exists)
try {
    importScripts('https://www.gstatic.com/firebasejs/11.6.0/firebase-app-compat.js');
    importScripts('https://www.gstatic.com/firebasejs/11.6.0/firebase-messaging-compat.js');
    importScripts('/firebase-config.js');

    if (self.firebaseConfig?.apiKey) {
        firebase.initializeApp(self.firebaseConfig);
        firebase.messaging().onBackgroundMessage((payload) => showPushNotification(payload));
    }
} catch (e) {
    // Firebase optional
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/feed';
    event.waitUntil(clients.openWindow(url));
});
