import './bootstrap';
import './image-compress';
import { initNotificationBell } from './notifications';

let deferredPwaPrompt = null;

// Capture install prompt as early as possible (before DOMContentLoaded)
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPwaPrompt = e;
});

const PWA_DISMISS_KEY = 'pwa-install-dismissed';
const PWA_DISMISS_DAYS = 7;

function isPwaInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function isIosDevice() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

function isMobileDevice() {
    return /android|iphone|ipad|ipod|mobile/i.test(navigator.userAgent);
}

function wasPwaDismissedRecently() {
    const dismissed = localStorage.getItem(PWA_DISMISS_KEY);
    if (!dismissed) return false;
    const days = (Date.now() - parseInt(dismissed, 10)) / (1000 * 60 * 60 * 24);
    return days < PWA_DISMISS_DAYS;
}

window.showPwaInstallModal = function (iosMode = false) {
    if (isPwaInstalled()) return;

    const modal = document.getElementById('pwa-install-modal');
    const actions = document.getElementById('pwa-install-actions');
    const iosInstructions = document.getElementById('pwa-ios-instructions');
    const message = document.getElementById('pwa-install-message');
    if (!modal) return;

    if (iosMode || (isIosDevice() && !deferredPwaPrompt)) {
        actions?.classList.add('hidden');
        iosInstructions?.classList.remove('hidden');
        if (message) message.textContent = 'Get the full app experience on your device:';
    } else {
        actions?.classList.remove('hidden');
        iosInstructions?.classList.add('hidden');
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.dismissPwaModal = function () {
    document.getElementById('pwa-install-modal')?.classList.add('hidden');
    document.body.style.overflow = '';
    localStorage.setItem(PWA_DISMISS_KEY, String(Date.now()));
};

window.togglePassword = function (inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.querySelector('.eye-open')?.classList.toggle('hidden', show);
    btn.querySelector('.eye-closed')?.classList.toggle('hidden', !show);
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
};

window.openMobileMenu = function () {
    document.getElementById('mobile-offcanvas')?.classList.remove('translate-x-full');
    document.getElementById('mobile-offcanvas-backdrop')?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeMobileMenu = function () {
    document.getElementById('mobile-offcanvas')?.classList.add('translate-x-full');
    document.getElementById('mobile-offcanvas-backdrop')?.classList.add('hidden');
    document.body.style.overflow = '';
};

window.openLikersModal = async function (postId) {
    const modal = document.getElementById('likers-modal-' + postId);
    const list = document.getElementById('likers-list-' + postId);
    if (!modal || !list) return;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    list.innerHTML = '<p class="text-center text-gray-500 py-4 text-sm">Loading...</p>';

    try {
        const res = await fetch('/posts/' + postId + '/likers', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();

        if (!data.likers?.length) {
            list.innerHTML = '<p class="text-center text-gray-500 py-4 text-sm">No likes yet</p>';
            return;
        }

        list.innerHTML = data.likers.map((user) => `
            <a href="/profile/${user.id}" class="flex items-center gap-3 p-3 hover:bg-fb-gray rounded-lg">
                <img src="${user.avatar_url}" alt="" class="w-10 h-10 rounded-full object-cover">
                <span class="font-semibold text-sm">${user.name}</span>
            </a>
        `).join('');
    } catch {
        list.innerHTML = '<p class="text-center text-red-500 py-4 text-sm">Failed to load</p>';
    }
};

window.closeLikersModal = function (postId) {
    document.getElementById('likers-modal-' + postId)?.classList.add('hidden');
    document.body.style.overflow = '';
};

window.installPwa = async function () {
    if (deferredPwaPrompt) {
        deferredPwaPrompt.prompt();
        const { outcome } = await deferredPwaPrompt.userChoice;
        deferredPwaPrompt = null;
        dismissPwaModal();
        if (outcome === 'accepted') {
            document.getElementById('pwa-install-btn')?.classList.add('hidden');
        }
        return;
    }

    if (isIosDevice()) {
        showPwaInstallModal(true);
        return;
    }

    alert('Install is not available right now. Try using Chrome on Android or add to Home Screen from your browser menu.');
};

function initFormDedup() {
    document.querySelectorAll('.comment-form').forEach((form) => {
        if (form.dataset.dedupBound === '1') return;
        form.dataset.dedupBound = '1';

        form.addEventListener('submit', function (e) {
            if (form.dataset.submitting === '1') {
                e.preventDefault();
                return;
            }
            form.dataset.submitting = '1';

            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Posting...';
            }
            form.querySelectorAll('input[type="text"], textarea').forEach((input) => {
                input.readOnly = true;
            });
        });
    });
}

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;
    try {
        const existing = await navigator.serviceWorker.getRegistration('/');
        return existing || await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    } catch (e) {
        console.warn('SW registration failed:', e);
        return null;
    }
}

function initPwa() {
    if (isPwaInstalled()) return;

    registerServiceWorker();

    if (deferredPwaPrompt && !wasPwaDismissedRecently()) {
        setTimeout(() => showPwaInstallModal(false), 1500);
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPwaPrompt = e;
        document.getElementById('pwa-install-btn')?.classList.remove('hidden');

        if (!wasPwaDismissedRecently()) {
            setTimeout(() => showPwaInstallModal(false), 1500);
        }
    });

    window.addEventListener('appinstalled', () => {
        deferredPwaPrompt = null;
        dismissPwaModal();
        document.getElementById('pwa-install-btn')?.classList.add('hidden');
    });

    if (isIosDevice() && isMobileDevice() && !wasPwaDismissedRecently()) {
        setTimeout(() => showPwaInstallModal(true), 2000);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initNotificationBell();
    initFormDedup();
    initPwa();

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
