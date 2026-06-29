import './echo';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

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
        showNotificationToast(data.notifications[0]);
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

export function initNotificationBell() {
    const bellWrap = document.getElementById('notification-bell');
    const dropdown = document.getElementById('notification-dropdown');
    if (!bellWrap) return;

    let lastCount = 0;
    let pollTimer = null;

    const refresh = async (showToast = false) => {
        lastCount = await refreshNotificationUI({ showToastOnNew: showToast, previousCount: lastCount });
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

    // Poll every 8s — works without Reverb/WebSocket on live server
    pollTimer = setInterval(() => refresh(true), 8000);

    if (window.Echo && window.authUserId) {
        try {
            window.Echo.private(`notification.${window.authUserId}`)
                .listen('.NotificationEvent', () => refresh(true));
        } catch (e) {
            console.warn('Echo notification channel unavailable, using polling only.');
        }
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

        onMessage(firebase.messaging, (payload) => {
            const title = payload.notification?.title || 'New notification';
            const body = payload.notification?.body || '';
            showNotificationToast({ title, message: body, created_at_human: 'Just now' });
            refreshNotificationUI({ showToastOnNew: false });
        });
    } catch (e) {
        console.warn('Firebase push not available:', e);
    }
}
