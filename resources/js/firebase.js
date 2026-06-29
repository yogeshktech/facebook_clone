import { initializeApp } from 'firebase/app';
import { getAnalytics, isSupported as isAnalyticsSupported } from 'firebase/analytics';
import { getMessaging, getToken, onMessage, isSupported as isMessagingSupported } from 'firebase/messaging';

export function getFirebaseConfig() {
    return window.firebaseConfig ?? null;
}

export async function initFirebase() {
    const config = getFirebaseConfig();
    if (!config?.apiKey) {
        return null;
    }

    const app = initializeApp({
        apiKey: config.apiKey,
        authDomain: config.authDomain,
        projectId: config.projectId,
        storageBucket: config.storageBucket,
        messagingSenderId: config.messagingSenderId,
        appId: config.appId,
        measurementId: config.measurementId,
    });

    if (await isAnalyticsSupported()) {
        getAnalytics(app);
    }

    const messaging = await isMessagingSupported() ? getMessaging(app) : null;

    return { app, messaging, vapidKey: config.vapidKey };
}

export async function registerFirebaseMessaging(messaging, vapidKey) {
    if (!messaging || !vapidKey) {
        return null;
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        return null;
    }

    if ('serviceWorker' in navigator) {
        const existing = await navigator.serviceWorker.getRegistration('/');
        const registration = existing || await navigator.serviceWorker.register('/sw.js');
        return getToken(messaging, {
            vapidKey,
            serviceWorkerRegistration: registration,
        });
    }

    return null;
}

export { getToken, onMessage };
