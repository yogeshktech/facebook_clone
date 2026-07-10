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

async function registerServiceWorkerForPush() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    const candidates = ['/firebase-messaging-sw.js', '/sw.js'];

    for (const swPath of candidates) {
        try {
            const existing = await navigator.serviceWorker.getRegistration(swPath);
            const registration = existing || await navigator.serviceWorker.register(swPath, { scope: '/' });
            await navigator.serviceWorker.ready;
            await registration.update().catch(() => {});
            return registration;
        } catch (error) {
            console.warn(`Push service worker registration failed for ${swPath}:`, error);
        }
    }

    try {
        const registration = await navigator.serviceWorker.getRegistration('/') || await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;
        return registration;
    } catch (error) {
        console.warn('Push service worker registration failed:', error);
        return null;
    }
}

export async function ensureServiceWorkerRegistration() {
    return registerServiceWorkerForPush();
}

export async function registerFirebaseMessaging(messaging, vapidKey) {
    if (!messaging || !vapidKey) {
        return null;
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        return null;
    }

    const registration = await ensureServiceWorkerRegistration();
    if (!registration) {
        return null;
    }

    return getToken(messaging, {
        vapidKey,
        serviceWorkerRegistration: registration,
    });
}

export { getToken, onMessage };
