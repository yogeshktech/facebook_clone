import './echo';
import { ensureServiceWorkerRegistration } from './firebase';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

const NOTIFICATION_ICON = '/icons/icon-192.png';
const NOTIFIED_IDS_KEY = 'newbook_notified_ids';

function loadNotifiedIds() {
    try {
        const raw = localStorage.getItem(NOTIFIED_IDS_KEY);
        return new Set(raw ? JSON.parse(raw) : []);
    } catch {
        return new Set();
    }
}

function saveNotifiedIds(ids) {
    const arr = [...ids].slice(-200);
    localStorage.setItem(NOTIFIED_IDS_KEY, JSON.stringify(arr));
}

function markNotified(id) {
    if (!id) return;
    const ids = loadNotifiedIds();
    ids.add(id);
    saveNotifiedIds(ids);
}

function getUnnotified(notifications) {
    const notified = loadNotifiedIds();
    return (notifications || []).filter((n) => n.id && !notified.has(n.id));
}

export function updateNotificationBadges(count) {
    const label = count > 9 ? '9+' : String(count);
    ['notification-count', 'mobile-notification-count', 'dropdown-notification-count'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (count > 0) {
            el.textContent = label;
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    });
}

export function playNotificationSound() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;

        const ctx = new AudioCtx();
        const playTone = (freq, start, duration) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.25, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(start);
            osc.stop(start + duration);
        };

        const run = () => {
            const now = ctx.currentTime;
            playTone(880, now, 0.15);
            playTone(1175, now + 0.18, 0.2);

            if (navigator.vibrate) {
                navigator.vibrate([120, 60, 120]);
            }
        };

        if (ctx.state === 'suspended') {
            ctx.resume().then(run).catch(run);
        } else {
            run();
        }
    } catch {
        // ignore if audio blocked
    }
}

export async function showSystemNotification({ title, body, url, tag, type, senderId }) {
    if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
        return;
    }

    const isCall = type === 'incoming_call' || (typeof title === 'string' && title.toLowerCase().includes('incoming') && title.toLowerCase().includes('call'));

    const options = {
        body: body || '',
        icon: NOTIFICATION_ICON,
        badge: NOTIFICATION_ICON,
        vibrate: isCall ? [500, 110, 500, 110, 500, 110, 500, 110, 500] : [150, 80, 150],
        silent: false,
        renotify: true,
        requireInteraction: isCall ? true : false,
        tag: isCall ? 'incoming-call' : (tag || 'newbook-alert'),
        data: { url: url || '/notifications', sender_id: senderId, type },
    };

    if (isCall) {
        options.actions = [
            { action: 'answer', title: 'Answer' },
            { action: 'decline', title: 'Decline' }
        ];
    }

    try {
        const registration = await ensureServiceWorkerRegistration();
        if (registration) {
            await registration.showNotification(title, options);
            return;
        }
        new Notification(title, options);
    } catch {
        // fallback silent
    }
}

export async function alertUser(notification) {
    const title = notification.title || notification.message || 'NEWBOOK';
    const body = notification.message || notification.body || '';
    const url = notification.url || '/notifications';
    const tag = notification.id ? `newbook-${notification.id}` : `newbook-${Date.now()}`;
    const isIncomingCall = notification.type === 'incoming_call'
        || (typeof title === 'string' && title.toLowerCase().includes('incoming') && title.toLowerCase().includes('call'));

    if (notification.id) {
        markNotified(notification.id);
    }

    // Wake call UI immediately (WebSocket may have missed the offer).
    if (isIncomingCall && window.CallManager) {
        try {
            window.CallManager.init?.();
            window.CallManager.checkPendingCall?.();
        } catch (e) {}
    }

    showNotificationToast(notification);
    playNotificationSound();

    if (Notification.permission === 'granted') {
        await showSystemNotification({
            title,
            body,
            url,
            tag,
            type: notification.type,
            senderId: notification.sender_id || notification.sender?.id
        });
    }
}

export function showNotificationToast(notification) {
    const toastEl = document.getElementById('notification-toast');
    if (!toastEl) return;

    const avatar = notification.sender?.avatar_url
        ? `<img src="${notification.sender.avatar_url}" class="w-8 h-8 rounded-full object-cover" alt="">`
        : '';

    toastEl.innerHTML = `
        <div class="flex gap-3 items-start text-slate-900">
            ${avatar}
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-sm text-slate-900">${notification.title || notification.message}</p>
                <p class="text-xs text-gray-500 mt-0.5">${notification.created_at_human || 'Just now'}</p>
            </div>
        </div>`;
    toastEl.classList.remove('hidden');
    setTimeout(() => toastEl.classList.add('hidden'), 5000);
}

export function renderNotificationDropdownItem(notification) {
    const avatar = notification.sender?.avatar_url
        ? `<img src="${notification.sender.avatar_url}" class="w-10 h-10 rounded-full object-cover flex-shrink-0" alt="">`
        : '<div class="w-10 h-10 rounded-full bg-gray-200 flex-shrink-0"></div>';

    const unreadClass = notification.is_read ? '' : 'bg-blue-50';

    return `
        <a href="${notification.url || '/notifications'}"
           data-notification-id="${notification.id}"
           class="notification-dropdown-item flex gap-3 p-3 hover:bg-gray-50 transition ${unreadClass}">
            ${avatar}
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 truncate">${notification.title}</p>
                <p class="text-xs text-gray-500">${notification.created_at_human || ''}</p>
            </div>
        </a>`;
}

export async function loadNotifications() {
    try {
        const res = await fetch('/notifications/unread', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) return null;
        return await res.json();
    } catch {
        return null;
    }
}

export async function refreshNotificationUI({ bootstrap = false } = {}) {
    const data = await loadNotifications();
    if (!data) return 0;

    const count = data.count || 0;
    const notifications = data.notifications || [];
    updateNotificationBadges(count);

    const listEl = document.getElementById('notification-dropdown-list');
    if (listEl) {
        if (notifications.length) {
            listEl.innerHTML = notifications.map(renderNotificationDropdownItem).join('');
            bindDropdownItemClicks(listEl);
        } else {
            listEl.innerHTML = '<p class="p-4 text-center text-sm text-gray-500">No new notifications</p>';
        }
    }

    if (bootstrap) {
        const ids = loadNotifiedIds();
        notifications.forEach((n) => n.id && ids.add(n.id));
        saveNotifiedIds(ids);
        return count;
    }

    const fresh = getUnnotified(notifications);
    if (fresh.length) {
        await alertUser(fresh[0]);
    }

    return count;
}

function bindDropdownItemClicks(container) {
    container.querySelectorAll('.notification-dropdown-item[data-notification-id]').forEach(el => {
        el.addEventListener('click', async () => {
            const id = el.dataset.notificationId;
            if (!id) return;
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json',
                },
            });
            el.classList.remove('bg-blue-50');
        });
    });
}

function hidePermissionBanner() {
    document.getElementById('notification-permission-banner')?.classList.add('hidden');
}

function showPermissionBanner() {
    if (Notification.permission !== 'default') return;
    document.getElementById('notification-permission-banner')?.classList.remove('hidden');
}

export async function enablePushNotifications() {
    hidePermissionBanner();

    if (typeof Notification === 'undefined') {
        alert('Notifications are not supported in this browser.');
        return;
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        alert('Please allow notifications in browser settings to get message alerts with sound.');
        return;
    }

    await initFirebasePush();
}

window.enablePushNotifications = enablePushNotifications;

export function initNotificationBell() {
    const bellWrap = document.getElementById('notification-bell');
    const dropdown = document.getElementById('notification-dropdown');
    if (!bellWrap) return;

    const refresh = async (bootstrap = false) => {
        await refreshNotificationUI({ bootstrap });
    };

    bellWrap.querySelector('button')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropdown?.classList.toggle('hidden');
        if (dropdown && !dropdown.classList.contains('hidden')) {
            refresh(false);
        }
    });

    document.addEventListener('click', (e) => {
        if (dropdown && !bellWrap.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    document.getElementById('mark-all-read-dropdown')?.addEventListener('click', async (e) => {
        e.preventDefault();
        await fetch('/notifications/read-all', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        });
        await refresh(false);
    });

    refresh(true);

    setInterval(() => refresh(false), 8000);

    if (window.Echo && typeof window.Echo.private === 'function' && window.authUserId) {
        try {
            window.Echo.private(`notification.${window.authUserId}`)
                .listen('.NotificationEvent', async (payload) => {
                    if (payload?.id && loadNotifiedIds().has(payload.id)) {
                        await refresh(false);
                        return;
                    }
                    await alertUser(payload || {});
                    await refresh(false);
                });
        } catch (e) {
            console.warn('Echo notification channel unavailable, using polling only.');
        }
    }

    if (Notification.permission === 'default') {
        setTimeout(showPermissionBanner, 3000);
    }

    initFirebasePush();
}

async function initFirebasePush() {
    try {
        if ('serviceWorker' in navigator) {
            const registrations = await navigator.serviceWorker.getRegistrations();
            await Promise.all(registrations.map((registration) => registration.update().catch(() => {})));
        }

        const { initFirebase, registerFirebaseMessaging, onMessage } = await import('./firebase');
        const firebase = await initFirebase();

        if (!firebase?.messaging) {
            return;
        }

        const token = await registerFirebaseMessaging(firebase.messaging, firebase.vapidKey);

        if (token) {
            await fetch('/notifications/device-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ token, platform: 'web' }),
            });
        }

        onMessage(firebase.messaging, async (payload) => {
            const notification = {
                id: payload.data?.notification_id ? parseInt(payload.data.notification_id, 10) : null,
                title: payload.notification?.title || payload.data?.title,
                message: payload.notification?.body || payload.data?.body,
                url: payload.data?.url,
                type: payload.data?.type,
                sender_id: payload.data?.sender_id,
            };
            if (!notification.id || !loadNotifiedIds().has(notification.id)) {
                await alertUser(notification);
            }
            await refreshNotificationUI({ bootstrap: false });
        });
    } catch (e) {
        console.warn('Firebase push not available:', e);
    }
}
