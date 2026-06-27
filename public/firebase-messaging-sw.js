/* Firebase Cloud Messaging — background push (browser closed / other tab) */
importScripts('https://www.gstatic.com/firebasejs/11.6.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/11.6.0/firebase-messaging-compat.js');
importScripts('/firebase-config.js');

firebase.initializeApp(self.firebaseConfig);

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    const title = payload.notification?.title || 'Newbook';
    const options = {
        body: payload.notification?.body || '',
        icon: '/favicon.svg',
        data: {
            url: payload.data?.url || payload.fcmOptions?.link || '/',
        },
    };

    self.registration.showNotification(title, options);
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(clients.openWindow(url));
});
