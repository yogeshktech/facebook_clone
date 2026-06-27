import './bootstrap';
import './image-compress';
import { initNotificationBell } from './notifications';

window.togglePassword = function (inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.querySelector('.eye-open')?.classList.toggle('hidden', show);
    btn.querySelector('.eye-closed')?.classList.toggle('hidden', !show);
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
};

document.addEventListener('DOMContentLoaded', () => {
    initNotificationBell();

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
