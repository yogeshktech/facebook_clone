import './echo';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

const NOTIFICATION_ICON = '/icons/icon-192.png';

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

        const now = ctx.currentTime;
        playTone(880, now, 0.15);
        playTone(1175, now + 0.18, 0.2);

        if (navigator.vibrate) {
            navigator.vibrate([120, 60, 120]);
        }
    } catch {
        // ignore if audio blocked
    }
}

export async function showSystemNotification({ title, body, url, tag }) {
    if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
        return;
    }

    const options = {
        body: body || '',
        icon: NOTIFICATION_ICON,
        badge: NOTIFICATION_ICON,
        vibrate: [150, 80, 150],
        silent: false,
        renotify: true,
        requireInteraction: false,
        tag: tag || 'newbook-alert',
        data: { url: url || '/notifications' },
    };

    try {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.ready;
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

    showNotificationToast(notification);
    playNotificationSound();

    if (Notification.permission === 'granted') {
        await showSystemNotification({ title, body, url, tag });
    }
}

export function showNotificationToast(notification) {
    const toastEl = document.getElementById('notification-toast');
    if (!toastEl) return;

    const avatar = notification.sender?.avatar_url
        ? `<img src="${notification.sender.avatar_url}" class="w-8 h-8 rounded-full object-cover" alt="">`
        : '';

    toastEl.innerHTML = `
        <div class="flex gap-3 items-start">
            ${avatar}
            <div>
                <p class="font-semibold text-sm">${notification.title || notification.message}</p>
                <p class="text-xs text-gray-500">${notification.created_at_human || 'Just now'}</p>
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

export async function refreshNotificationUI({ showToastOnNew = false, previousCount = 0 } = {}) {
    const data = await loadNotifications();
    if (!data) return previousCount;

    const count = data.count || 0;
    updateNotificationBadges(count);

    const listEl = document.getElementById('notification-dropdown-list');
    if (listEl) {
        if (data.notifications?.length) {
            listEl.innerHTML = data.notifications.map(renderNotificationDropdownItem).join('');
            bindDropdownItemClicks(listEl);
        } else {
            listEl.innerHTML = '<p class="p-4 text-center text-sm text-gray-500">No new notifications</p>';
        }
    }

    if (showToastOnNew && count > previousCount && data.notifications?.length) {
        await alertUser(data.notifications[0]);
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

    let lastCount = 0;

    const refresh = async (showAlert = false) => {
        lastCount = await refreshNotificationUI({ showToastOnNew: showAlert, previousCount: lastCount });
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

    refresh(false);

    setInterval(() => refresh(true), 8000);

    if (window.Echo && window.authUserId) {
        try {
            window.Echo.private(`notification.${window.authUserId}`)
                .listen('.NotificationEvent', async (payload) => {
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
            await alertUser({
                title: payload.notification?.title,
                message: payload.notification?.body,
                url: payload.data?.url,
            });
            await refreshNotificationUI({ showToastOnNew: false });
        });
    } catch (e) {
        console.warn('Firebase push not available:', e);
    }
}
