import './bootstrap';
import './image-compress';
import { initNotificationBell } from './notifications';
// calls.js is loaded inline from layouts/assets.blade.php so live always gets latest call code

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
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;
        await registration.update().catch(() => {});
        return registration;
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
    initAjaxLikeAndComment();
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

function initAjaxLikeAndComment() {
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function createCommentHtml(comment, postActionUrl) {
        const parentId = comment.parent_id;
        const mlClass = parentId ? 'ml-10' : '';
        const replyFormHtml = !parentId ? `
          <form action="${postActionUrl}" method="POST"
            class="mt-2 flex gap-2 hidden comment-form" id="reply-form-${comment.id}">
            <input type="hidden" name="parent_id" value="${comment.id}">
            <img src="${comment.user.avatar_url}" alt="" class="w-7 h-7 rounded-full object-cover flex-shrink-0">
            <input type="text" name="content" placeholder="Reply to ${comment.user.name}..." required
              class="flex-1 bg-fb-gray rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fb-blue">
            <button type="submit" class="text-fb-blue font-semibold text-sm whitespace-nowrap">Reply</button>
          </form>
        ` : '';

        const replyBtnHtml = !parentId ? `
            <button type="button"
              onclick="document.getElementById('reply-form-${comment.id}').classList.toggle('hidden')"
              class="text-xs font-semibold text-gray-600 hover:text-fb-blue">
              Reply
            </button>
        ` : '';

        return `
        <div class="space-y-2" id="comment-item-${comment.id}">
          <div class="flex gap-2 ${mlClass}">
            <img src="${comment.user.avatar_url}" alt="" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
            <div class="flex-1 min-w-0">
              <div class="bg-fb-gray rounded-2xl px-3 py-2 inline-block max-w-full">
                <a href="${comment.user.profile_url}" class="font-semibold text-sm hover:underline">${comment.user.name}</a>
                <p class="text-sm whitespace-pre-wrap break-words">${escapeHtml(comment.content)}</p>
              </div>
              <div class="flex items-center gap-3 mt-1 ml-3">
                <span class="text-xs text-gray-500">${comment.created_at_human || 'Just now'}</span>
                ${replyBtnHtml}
              </div>
              ${replyFormHtml}
            </div>
          </div>
          <div class="replies-container space-y-2" id="replies-container-${comment.id}"></div>
        </div>
        `;
    }

    document.addEventListener('submit', async (e) => {
        const form = e.target;
        const action = form.action || '';
        let pathname = '';
        let fetchUrl = action;
        try {
            const urlObj = new URL(action);
            pathname = urlObj.pathname;
            urlObj.protocol = window.location.protocol;
            fetchUrl = urlObj.toString();
        } catch (err) {
            pathname = action;
        }

        // --- 1. HANDLE LIKES ---
        if (pathname.endsWith('/like') || pathname.endsWith('/like/') || pathname.includes('/like/')) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch(fetchUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                });

                if (!res.ok) return;
                const data = await res.json();

                const isPostCard = form.classList.contains('like-form');
                if (isPostCard) {
                    const postId = form.dataset.postId;
                    const likeButton = form.querySelector('.like-btn');
                    if (likeButton) {
                        likeButton.className = `like-btn w-full flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-gray-100 ${data.liked ? 'text-fb-blue' : 'text-gray-600'}`;
                        const svg = likeButton.querySelector('svg');
                        if (svg) svg.setAttribute('fill', data.liked ? 'currentColor' : 'none');
                    }

                    const statWrapper = document.querySelector(`.likes-count-wrapper[data-post-id="${postId}"]`);
                    if (statWrapper) {
                        if (data.likes_count > 0) {
                            statWrapper.innerHTML = `
                                <button type="button" onclick="openLikersModal(${postId})" class="hover:underline cursor-pointer text-left">
                                    ${data.likes_count} ${data.likes_count === 1 ? 'like' : 'likes'}
                                </button>`;
                        } else {
                            statWrapper.innerHTML = '<span>0 likes</span>';
                        }
                    }
                } else {
                    const countSpan = form.querySelector('span.text-xs');
                    const wrapperDiv = form.querySelector('.rounded-full');
                    const svg = form.querySelector('svg');

                    if (countSpan) countSpan.textContent = data.likes_count;
                    if (wrapperDiv) {
                        wrapperDiv.className = `w-11 h-11 rounded-full bg-white/20 flex items-center justify-center ${data.liked ? 'text-red-500' : ''}`;
                    }
                    if (svg) svg.setAttribute('fill', data.liked ? 'currentColor' : 'none');
                }
            } catch (err) {
                console.error('Like action failed:', err);
            } finally {
                if (btn) btn.disabled = false;
            }
            return;
        }

        // --- 2. HANDLE COMMENTS ---
        if (pathname.endsWith('/comment') || pathname.endsWith('/comment/') || pathname.includes('/comment/')) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const input = form.querySelector('input[name="content"]');
            if (!input || !input.value.trim()) return;

            if (btn) btn.disabled = true;

            try {
                const res = await fetch(fetchUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                });

                if (!res.ok) return;
                const data = await res.json();

                if (data.success) {
                    input.value = '';

                    const isReelOrVideoComment = form.closest('[id^="reel-comment-"]') || form.closest('[id^="video-comment-"]');
                    
                    if (isReelOrVideoComment) {
                        const parentContainer = form.closest('[data-reel-id], [data-video-id]');
                        if (parentContainer) {
                            const commentBtn = parentContainer.querySelector('button[onclick*="comment"]');
                            if (commentBtn) {
                                const countSpan = commentBtn.querySelector('span');
                                if (countSpan && data.comments_count !== undefined) {
                                    countSpan.textContent = data.comments_count;
                                }
                            }
                        }
                        const panel = form.closest('.bg-white.rounded-t-xl, .bg-white.rounded-t-xl.p-3');
                        if (panel) panel.classList.add('hidden');
                    } else {
                        const postId = form.dataset.postId || form.closest('.bg-white.rounded-lg.shadow')?.id.replace('post-', '');
                        
                        if (postId) {
                            const commentsCountLabel = document.querySelector(`.comments-count-label[data-post-id="${postId}"]`);
                            if (commentsCountLabel && data.comments_count !== undefined) {
                                commentsCountLabel.textContent = data.comments_count;
                            }
                        }

                        const commentData = data.comment;
                        const commentHtml = createCommentHtml(commentData, action);

                        if (commentData.parent_id) {
                            const repliesContainer = document.getElementById(`replies-container-${commentData.parent_id}`);
                            if (repliesContainer) {
                                repliesContainer.insertAdjacentHTML('beforeend', commentHtml);
                            }
                            form.classList.add('hidden');
                        } else {
                            const commentsContainer = document.getElementById(`comments-container-${postId}`);
                            if (commentsContainer) {
                                commentsContainer.insertAdjacentHTML('beforeend', commentHtml);
                            }
                        }
                    }
                }
            } catch (err) {
                console.error('Comment action failed:', err);
            } finally {
                if (btn) btn.disabled = false;
            }
        }
    });
}
