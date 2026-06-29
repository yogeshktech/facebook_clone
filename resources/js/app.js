import './bootstrap';
import './image-compress';
import { initNotificationBell } from './notifications';

let deferredPwaPrompt = null;

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
    if (!deferredPwaPrompt) return;
    deferredPwaPrompt.prompt();
    await deferredPwaPrompt.userChoice;
    deferredPwaPrompt = null;
    document.getElementById('pwa-install-btn')?.classList.add('hidden');
};

function initFormDedup() {
    document.querySelectorAll('.comment-form').forEach((form) => {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Posting...';
            }
            form.querySelectorAll('input[type="text"]').forEach((input) => {
                input.readOnly = true;
            });
        });
    });
}

function initPwa() {
    if ('serviceWorker' in navigator) {
        const swPath = window.firebaseConfig?.apiKey ? '/firebase-messaging-sw.js' : '/sw.js';
        navigator.serviceWorker.register(swPath).catch(() => {});
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPwaPrompt = e;
        document.getElementById('pwa-install-btn')?.classList.remove('hidden');
    });
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
