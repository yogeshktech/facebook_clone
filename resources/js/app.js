import './bootstrap';
import './image-compress';

window.togglePassword = function (inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.querySelector('.eye-open')?.classList.toggle('hidden', show);
    btn.querySelector('.eye-closed')?.classList.toggle('hidden', !show);
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
};

// Live notification polling
document.addEventListener('DOMContentLoaded', () => {
    const countEl = document.getElementById('notification-count');
    const mobileCountEl = document.getElementById('mobile-notification-count');
    const toastEl = document.getElementById('notification-toast');
    if (!countEl && !mobileCountEl) return;

    let lastCount = 0;

    const updateCount = (count) => {
        const label = count > 9 ? '9+' : count;
        [countEl, mobileCountEl].forEach(el => {
            if (!el) return;
            if (count > 0) {
                el.textContent = label;
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
    };

    const pollNotifications = async () => {
        try {
            const res = await fetch('/notifications/unread', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();

            updateCount(data.count || 0);

            if (data.count > lastCount && data.notifications?.length && toastEl) {
                const latest = data.notifications[0];
                toastEl.innerHTML = `<p class="font-semibold text-sm">${latest.data?.message || 'New notification'}</p><p class="text-xs text-gray-500">${latest.created_at}</p>`;
                toastEl.classList.remove('hidden');
                setTimeout(() => toastEl.classList.add('hidden'), 5000);
            }
            lastCount = data.count;
        } catch (e) {
            // Silently fail if not authenticated
        }
    };

    pollNotifications();
    setInterval(pollNotifications, 10000);

    // Auto-hide flash messages
    ['flash-success', 'flash-error'].forEach(id => {
        const el = document.getElementById(id);
        if (el) setTimeout(() => el.remove(), 4000);
    });

    window.openShareModal = function (postId) {
        document.getElementById('share-modal-' + postId)?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    };
    window.closeShareModal = function (postId) {
        document.getElementById('share-modal-' + postId)?.classList.add('hidden');
        document.body.style.overflow = '';
    };
});
