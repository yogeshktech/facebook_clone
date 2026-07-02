@if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    <script src="https://cdn.tailwindcss.com"></script>
    @auth
        @include('layouts.echo-cdn')
    @endauth
    @include('layouts.image-compress')
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'fb-blue': '#6366F1',
                        'fb-blue-dark': '#4F46E5',
                        'fb-green': '#42B72A',
                        'fb-gray': '#F0F2F5',
                    },
                    fontFamily: {
                        sans: ['Segoe UI', 'Helvetica', 'Arial', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <style>
        .nav-icon { display: flex; width: 3rem; height: 3rem; align-items: center; justify-content: center; border-radius: 0.5rem; color: #6b7280; transition: background 0.15s; }
        .nav-icon:hover { background: #F0F2F5; }
        .nav-icon.active { color: #6366F1; border-bottom: 4px solid #6366F1; border-radius: 0; }
        .nav-icon.active:hover { background: transparent; }
        .mobile-nav-icon { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; min-width: 3.5rem; padding: 0.25rem 0.5rem; border-radius: 0.5rem; color: #6b7280; font-size: 0.625rem; font-weight: 600; transition: color 0.15s; }
        .mobile-nav-icon.active { color: #6366F1; }
        .safe-area-bottom { padding-bottom: env(safe-area-inset-bottom, 0); }
        .sidebar-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; transition: background 0.15s; }
        .sidebar-link:hover { background: #e5e7eb; }
        .btn-primary { background: #6366F1; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; transition: background 0.15s; }
        .btn-primary:hover { background: #4F46E5; }
        .btn-secondary { background: #F0F2F5; color: #1f2937; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; transition: background 0.15s; }
        .btn-secondary:hover { background: #e5e7eb; }
        .input-field { width: 100%; padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .input-field:focus { outline: none; box-shadow: 0 0 0 2px #6366F1; }
    </style>
    <script>
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
            const countEl = document.getElementById('notification-count');
            const mobileCountEl = document.getElementById('mobile-notification-count');
            const toastEl = document.getElementById('notification-toast');
            if (!countEl && !mobileCountEl) return;

            const NOTIFIED_KEY = 'newbook_notified_ids';
            const loadNotified = () => {
                try {
                    return new Set(JSON.parse(localStorage.getItem(NOTIFIED_KEY) || '[]'));
                } catch (e) {
                    return new Set();
                }
            };
            const saveNotified = (ids) => {
                localStorage.setItem(NOTIFIED_KEY, JSON.stringify([...ids].slice(-200)));
            };
            let notifiedIds = loadNotified();
            let bootstrapped = false;

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
            const playNotificationSound = () => {
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
                    if (navigator.vibrate) navigator.vibrate([120, 60, 120]);
                } catch (e) {}
            };
            const showSystemNotification = async (title, body, url) => {
                if (typeof Notification === 'undefined' || Notification.permission !== 'granted') return;
                const options = {
                    body: body || '',
                    icon: '/icons/icon-192.png',
                    badge: '/icons/icon-192.png',
                    vibrate: [150, 80, 150],
                    silent: false,
                    tag: 'newbook-alert',
                    data: { url: url || '/notifications' },
                };
                try {
                    if ('serviceWorker' in navigator) {
                        const reg = await navigator.serviceWorker.ready;
                        await reg.showNotification(title, options);
                    } else {
                        new Notification(title, options);
                    }
                } catch (e) {}
            };
            const poll = async () => {
                try {
                    const res = await fetch('/notifications/unread', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    updateCount(data.count || 0);

                    const notifications = data.notifications || [];

                    if (!bootstrapped) {
                        notifications.forEach((n) => n.id && notifiedIds.add(n.id));
                        saveNotified(notifiedIds);
                        bootstrapped = true;
                        return;
                    }

                    const fresh = notifications.filter((n) => n.id && !notifiedIds.has(n.id));
                    if (fresh.length && toastEl) {
                        const latest = fresh[0];
                        notifiedIds.add(latest.id);
                        saveNotified(notifiedIds);
                        const title = latest.title || latest.message || 'NEWBOOK';
                        toastEl.innerHTML = '<p class="font-semibold text-sm">' + title + '</p><p class="text-xs text-gray-500">' + (latest.created_at_human || 'Just now') + '</p>';
                        toastEl.classList.remove('hidden');
                        setTimeout(() => toastEl.classList.add('hidden'), 5000);
                        playNotificationSound();
                        showSystemNotification(title, latest.message || '', latest.url || '/notifications');
                    }
                } catch (e) {}
            };
            poll();
            setInterval(poll, 8000);
            ['flash-success', 'flash-error'].forEach(id => {
                const el = document.getElementById(id);
                if (el) setTimeout(() => el.remove(), 4000);
            });
        });

        window.openShareModal = function (postId) {
            document.getElementById('share-modal-' + postId)?.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        };
        window.closeShareModal = function (postId) {
            document.getElementById('share-modal-' + postId)?.classList.add('hidden');
            document.body.style.overflow = '';
        };

        let deferredPwaPrompt = null;
        const PWA_DISMISS_KEY = 'pwa-install-dismissed';

        function isPwaInstalled() {
            return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        }
        function isIosDevice() {
            return /iphone|ipad|ipod/i.test(navigator.userAgent);
        }
        function wasPwaDismissedRecently() {
            const d = localStorage.getItem(PWA_DISMISS_KEY);
            if (!d) return false;
            return (Date.now() - parseInt(d, 10)) / 86400000 < 7;
        }

        window.showPwaInstallModal = function (iosMode) {
            if (isPwaInstalled()) return;
            const modal = document.getElementById('pwa-install-modal');
            const actions = document.getElementById('pwa-install-actions');
            const iosInstructions = document.getElementById('pwa-ios-instructions');
            if (!modal) return;
            if (iosMode || (isIosDevice() && !deferredPwaPrompt)) {
                actions?.classList.add('hidden');
                iosInstructions?.classList.remove('hidden');
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
                list.innerHTML = data.likers.map((user) =>
                    '<a href="/profile/' + user.id + '" class="flex items-center gap-3 p-3 hover:bg-fb-gray rounded-lg">' +
                    '<img src="' + user.avatar_url + '" alt="" class="w-10 h-10 rounded-full object-cover">' +
                    '<span class="font-semibold text-sm">' + user.name + '</span></a>'
                ).join('');
            } catch (e) {
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
                await deferredPwaPrompt.userChoice;
                deferredPwaPrompt = null;
                dismissPwaModal();
                return;
            }
            if (isIosDevice()) showPwaInstallModal(true);
        };

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
                if (btn) { btn.disabled = true; btn.textContent = 'Posting...'; }
                form.querySelectorAll('input[type="text"], textarea').forEach((input) => { input.readOnly = true; });
            });
        });

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
        }

        if (!isPwaInstalled()) {
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPwaPrompt = e;
                if (!wasPwaDismissedRecently()) {
                    setTimeout(() => showPwaInstallModal(false), 1500);
                }
            });
            if (isIosDevice() && /mobile/i.test(navigator.userAgent) && !wasPwaDismissedRecently()) {
                setTimeout(() => showPwaInstallModal(true), 2000);
            }
        }

        initAjaxLikeAndComment();

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

    // Tone Generator to synthesize ringtones client-side
    class ToneGenerator {
        constructor() {
            this.ctx = null;
            this.osc1 = null;
            this.osc2 = null;
            this.gainNode = null;
            this.timer = null;
        }

        startRing() {
            this.stop();
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                this.ctx = new AudioCtx();
                this.gainNode = this.ctx.createGain();
                this.gainNode.gain.setValueAtTime(0, this.ctx.currentTime);
                this.gainNode.connect(this.ctx.destination);

                this.osc1 = this.ctx.createOscillator();
                this.osc1.type = 'sine';
                this.osc1.frequency.value = 440;
                this.osc1.connect(this.gainNode);

                this.osc2 = this.ctx.createOscillator();
                this.osc2.type = 'sine';
                this.osc2.frequency.value = 480;
                this.osc2.connect(this.gainNode);

                this.osc1.start(0);
                this.osc2.start(0);

                const playRing = () => {
                    if (!this.ctx) return;
                    const now = this.ctx.currentTime;
                    this.gainNode.gain.setValueAtTime(0, now);
                    this.gainNode.gain.linearRampToValueAtTime(0.15, now + 0.1);
                    this.gainNode.gain.setValueAtTime(0.15, now + 2.0);
                    this.gainNode.gain.linearRampToValueAtTime(0, now + 2.1);
                };

                playRing();
                this.timer = setInterval(playRing, 4000);
            } catch (e) {
                console.error('Failed to generate ring tone', e);
            }
        }

        startDial() {
            this.stop();
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                this.ctx = new AudioCtx();
                this.gainNode = this.ctx.createGain();
                this.gainNode.gain.setValueAtTime(0, this.ctx.currentTime);
                this.gainNode.connect(this.ctx.destination);

                this.osc1 = this.ctx.createOscillator();
                this.osc1.type = 'sine';
                this.osc1.frequency.value = 350;
                this.osc1.connect(this.gainNode);

                this.osc2 = this.ctx.createOscillator();
                this.osc2.type = 'sine';
                this.osc2.frequency.value = 440;
                this.osc2.connect(this.gainNode);

                this.osc1.start(0);
                this.osc2.start(0);

                const playDial = () => {
                    if (!this.ctx) return;
                    const now = this.ctx.currentTime;
                    this.gainNode.gain.setValueAtTime(0, now);
                    this.gainNode.gain.linearRampToValueAtTime(0.1, now + 0.1);
                    this.gainNode.gain.setValueAtTime(0.1, now + 1.2);
                    this.gainNode.gain.linearRampToValueAtTime(0, now + 1.3);
                };

                playDial();
                this.timer = setInterval(playDial, 3000);
            } catch (e) {
                console.error('Failed to generate dial tone', e);
            }
        }

        stop() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
            try {
                if (this.osc1) this.osc1.stop();
                if (this.osc2) this.osc2.stop();
                if (this.ctx) this.ctx.close();
            } catch (e) {}
            this.osc1 = null;
            this.osc2 = null;
            this.ctx = null;
            this.gainNode = null;
        }
    }

    // WebRTC Call Manager class
    class WebRTCCallManager {
        constructor() {
            this.peerConnection = null;
            this.localStream = null;
            this.remoteUserId = null;
            this.isVideo = false;
            this.toneGen = new ToneGenerator();
            this.iceCandidatesQueue = [];
            this.isIncoming = false;
            this.isCalling = false;
            this.isCallActive = false;
            this.incomingOfferSdp = null;

            // UI references
            this.overlay = null;
            this.avatar = null;
            this.userName = null;
            this.status = null;
            this.videosContainer = null;
            this.audioPulse = null;
            this.localVideo = null;
            this.remoteVideo = null;
            this.remoteAudio = null;
            this._endingCall = false;
            this._disconnectTimer = null;

            this.declineBtn = null;
            this.acceptBtn = null;
            this.muteBtn = null;
            this.videoBtn = null;
            this.hangupBtn = null;
        }

        init() {
            this.overlay = document.getElementById('call-overlay');
            if (!this.overlay) return;

            this.avatar = document.getElementById('call-user-avatar');
            this.userName = document.getElementById('call-user-name');
            this.status = document.getElementById('call-status');
            this.videosContainer = document.getElementById('call-videos-container');
            this.audioPulse = document.getElementById('call-audio-pulse');
            this.localVideo = document.getElementById('local-video');
            this.remoteVideo = document.getElementById('remote-video');
            this.remoteAudio = document.getElementById('remote-audio');

            this.declineBtn = document.getElementById('decline-call-btn');
            this.acceptBtn = document.getElementById('accept-call-btn');
            this.muteBtn = document.getElementById('toggle-mute-btn');
            this.videoBtn = document.getElementById('toggle-video-btn');
            this.hangupBtn = document.getElementById('hangup-call-btn');

            this.declineBtn.onclick = () => this.declineCall();
            this.acceptBtn.onclick = () => this.acceptCall();
            this.muteBtn.onclick = () => this.toggleMute();
            this.videoBtn.onclick = () => this.toggleVideo();
            this.hangupBtn.onclick = () => this.hangupCall();

            this.bindChatCallButtons();
            this.registerSignalingListener();
        }

        registerSignalingListener() {
            if (!window.Echo || !window.authUserId) {
                console.warn('WebRTC calls need Reverb/Echo. Set BROADCAST_CONNECTION=reverb and run reverb:start.');
                return;
            }
            if (this._signalingBound) return;
            this._signalingBound = true;
            try {
                window.Echo.private(`user-signaling.${window.authUserId}`)
                    .listen('.call.signal', (payload) => {
                        this.handleIncomingSignal(payload);
                    });
            } catch (e) {
                console.warn('WebRTC signaling listener could not be registered.', e);
            }
        }

        bindChatCallButtons() {
            const wire = (buttonId, isVideo) => {
                const btn = document.getElementById(buttonId);
                if (!btn || btn.dataset.callBound === '1') return;
                btn.dataset.callBound = '1';
                btn.addEventListener('click', () => {
                    const userId = parseInt(btn.dataset.targetUserId, 10);
                    if (!userId) return;
                    if (!window.Echo) {
                        alert('Voice/video calls need real-time server (Reverb). Enable REVERB_* in .env and run php artisan reverb:start.');
                        return;
                    }
                    this.startCall(userId, isVideo, {
                        name: btn.dataset.targetName || '',
                        avatar: btn.dataset.targetAvatar || '',
                    });
                });
            };
            wire('audio-call-btn', false);
            wire('video-call-btn', true);
        }

        async sendSignal(type, data = null) {
            if (!this.remoteUserId) return;
            try {
                await fetch('/chat/call/signal', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        to_user_id: this.remoteUserId,
                        type: type,
                        data: data
                    })
                });
            } catch (e) {
                console.error('Failed to send call signal:', e);
            }
        }

        async startCall(remoteUserId, isVideo = false) {
            if (this.isCallActive || this.isCalling || this.isIncoming) return;
            this.isCalling = true;
            this.remoteUserId = remoteUserId;
            this.isVideo = isVideo;

            this.showOverlay();
            this.status.textContent = 'Calling...';
            this.declineBtn.classList.add('hidden');
            this.acceptBtn.classList.add('hidden');
            this.hangupBtn.classList.remove('hidden');
            this.muteBtn.classList.add('hidden');
            this.videoBtn.classList.add('hidden');

            this.toneGen.startDial();

            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: isVideo
                });

                if (isVideo && this.localVideo) {
                    this.localVideo.srcObject = this.localStream;
                }

                this.createPeerConnection();

                const offer = await this.peerConnection.createOffer();
                await this.peerConnection.setLocalDescription(offer);
                await this.sendSignal('offer', { sdp: offer.sdp, isVideo: isVideo });

            } catch (e) {
                console.error('Failed to start call:', e);
                this.cleanup();
                alert('Could not start call. Please check your camera/microphone permissions.');
            }
        }

        createPeerConnection() {
            const peerConfig = {
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ]
            };

            this.peerConnection = new RTCPeerConnection(peerConfig);

            if (this.localStream) {
                this.localStream.getTracks().forEach(track => {
                    this.peerConnection.addTrack(track, this.localStream);
                });
            }

            this.peerConnection.ontrack = (event) => {
                const stream = event.streams?.[0];
                if (!stream) return;
                if (this.isVideo && this.remoteVideo) {
                    this.remoteVideo.srcObject = stream;
                    this.remoteVideo.play().catch(() => {});
                }
                if (this.remoteAudio) {
                    this.remoteAudio.srcObject = stream;
                    this.remoteAudio.play().catch(() => {});
                }
            };

            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.sendSignal('candidate', event.candidate);
                }
            };

            this.peerConnection.onconnectionstatechange = () => {
                if (!this.peerConnection) return;
                const state = this.peerConnection.connectionState;
                if (state === 'connected') {
                    this.status.textContent = 'Connected';
                    this.isCallActive = true;
                    this.isCalling = false;
                    this.toneGen.stop();
                    this.updateCallUI();
                } else if (state === 'disconnected') {
                    if (this._disconnectTimer) clearTimeout(this._disconnectTimer);
                    this._disconnectTimer = setTimeout(() => {
                        if (this.peerConnection?.connectionState === 'disconnected') {
                            this.cleanup();
                        }
                    }, 4000);
                } else if (state === 'failed' || state === 'closed') {
                    this.cleanup();
                }
            };
        }

        async handleIncomingSignal(payload) {
            const { from_user, type, data } = payload;

            switch (type) {
                case 'offer':
                    if (this.isCallActive || this.isCalling || this.isIncoming) {
                        this.remoteUserId = from_user.id;
                        await this.sendSignal('decline', { reason: 'busy' });
                        this.remoteUserId = null;
                        return;
                    }
                    this.isIncoming = true;
                    this.remoteUserId = from_user.id;
                    this.isVideo = data.isVideo;
                    this.incomingOfferSdp = data.sdp;

                    this.showOverlay();
                    this.avatar.src = from_user.avatar_url || '';
                    this.userName.textContent = from_user.name;
                    this.status.textContent = `Incoming ${this.isVideo ? 'Video' : 'Audio'} Call...`;

                    this.declineBtn.classList.remove('hidden');
                    this.acceptBtn.classList.remove('hidden');
                    this.hangupBtn.classList.add('hidden');
                    this.muteBtn.classList.add('hidden');
                    this.videoBtn.classList.add('hidden');

                    this.toneGen.startRing();
                    break;

                case 'answer':
                    if (!this.peerConnection) return;
                    await this.peerConnection.setRemoteDescription(new RTCSessionDescription({
                        type: 'answer',
                        sdp: data.sdp
                    }));
                    while (this.iceCandidatesQueue.length > 0) {
                        const cand = this.iceCandidatesQueue.shift();
                        await this.peerConnection.addIceCandidate(new RTCIceCandidate(cand));
                    }
                    break;

                case 'candidate':
                    if (this.peerConnection && this.peerConnection.remoteDescription) {
                        await this.peerConnection.addIceCandidate(new RTCIceCandidate(data));
                    } else {
                        this.iceCandidatesQueue.push(data);
                    }
                    break;

                case 'decline':
                    if (!(this.isCallActive || this.isCalling || this.isIncoming)) break;
                    this.cleanup();
                    if (from_user?.id !== window.authUserId) {
                        alert('User is busy or declined the call.');
                    }
                    break;

                case 'hangup':
                    if (!(this.isCallActive || this.isCalling || this.isIncoming)) break;
                    this.cleanup();
                    break;
            }
        }

        async acceptCall() {
            if (!this.isIncoming || !this.remoteUserId || !this.incomingOfferSdp) return;
            this.toneGen.stop();
            this.acceptBtn.classList.add('hidden');
            this.declineBtn.classList.add('hidden');
            this.status.textContent = 'Connecting...';

            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: this.isVideo
                });

                if (this.isVideo && this.localVideo) {
                    this.localVideo.srcObject = this.localStream;
                }

                this.createPeerConnection();

                await this.peerConnection.setRemoteDescription(new RTCSessionDescription({
                    type: 'offer',
                    sdp: this.incomingOfferSdp
                }));

                while (this.iceCandidatesQueue.length > 0) {
                    const cand = this.iceCandidatesQueue.shift();
                    await this.peerConnection.addIceCandidate(new RTCIceCandidate(cand));
                }

                const answer = await this.peerConnection.createAnswer();
                await this.peerConnection.setLocalDescription(answer);
                await this.sendSignal('answer', { sdp: answer.sdp });

            } catch (e) {
                console.error('Failed to accept call:', e);
                this.cleanup();
                alert('Failed to connect call. Please check device permissions.');
            }
        }

        async endCall(signalType = 'hangup', extra = {}) {
            if (this._endingCall) return;
            this._endingCall = true;
            const remoteId = this.remoteUserId;
            try {
                if (remoteId) {
                    await this.sendSignal(signalType, extra);
                }
            } catch (e) {
                console.error('Failed to send call end signal:', e);
            } finally {
                this.cleanup();
                this._endingCall = false;
            }
        }

        async declineCall() {
            await this.endCall('decline', { reason: 'declined' });
        }

        async hangupCall() {
            await this.endCall('hangup');
        }

        toggleMute() {
            if (!this.localStream) return;
            const audioTrack = this.localStream.getAudioTracks()[0];
            if (audioTrack) {
                audioTrack.enabled = !audioTrack.enabled;
                this.muteBtn.classList.toggle('bg-red-600', !audioTrack.enabled);
                this.muteBtn.classList.toggle('hover:bg-red-700', !audioTrack.enabled);
            }
        }

        toggleVideo() {
            if (!this.localStream || !this.isVideo) return;
            const videoTrack = this.localStream.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.enabled = !videoTrack.enabled;
                this.videoBtn.classList.toggle('bg-red-600', !videoTrack.enabled);
                this.videoBtn.classList.toggle('hover:bg-red-700', !videoTrack.enabled);
            }
        }

        showOverlay() {
            if (this.overlay) {
                this.overlay.classList.remove('hidden');
            }
            document.body.style.overflow = 'hidden';
        }

        updateCallUI() {
            this.declineBtn.classList.add('hidden');
            this.acceptBtn.classList.add('hidden');
            this.hangupBtn.classList.remove('hidden');

            if (this.isVideo) {
                this.videosContainer.classList.remove('hidden');
                this.audioPulse.classList.add('hidden');
                this.videoBtn.classList.remove('hidden');
            } else {
                this.videosContainer.classList.add('hidden');
                this.audioPulse.classList.remove('hidden');
                this.videoBtn.classList.add('hidden');
            }
            this.muteBtn.classList.remove('hidden');
        }

        cleanup() {
            this.toneGen.stop();
            if (this._disconnectTimer) {
                clearTimeout(this._disconnectTimer);
                this._disconnectTimer = null;
            }
            this.isIncoming = false;
            this.isCalling = false;
            this.isCallActive = false;
            this.incomingOfferSdp = null;

            if (this.peerConnection) {
                this.peerConnection.onconnectionstatechange = null;
                this.peerConnection.onicecandidate = null;
                this.peerConnection.ontrack = null;
                try { this.peerConnection.close(); } catch (e) {}
                this.peerConnection = null;
            }

            if (this.localStream) {
                this.localStream.getTracks().forEach((track) => track.stop());
                this.localStream = null;
            }

            [this.remoteVideo, this.remoteAudio].forEach((element) => {
                if (!element?.srcObject) return;
                element.srcObject.getTracks().forEach((track) => track.stop());
                element.srcObject = null;
            });

            if (this.localVideo) this.localVideo.srcObject = null;

            if (this.overlay) {
                this.overlay.classList.add('hidden');
            }
            document.body.style.overflow = '';

            this.declineBtn?.classList.add('hidden');
            this.acceptBtn?.classList.add('hidden');
            this.muteBtn?.classList.add('hidden');
            this.videoBtn?.classList.add('hidden');
            this.hangupBtn?.classList.add('hidden');
            this.videosContainer?.classList.add('hidden');
            this.audioPulse?.classList.add('hidden');

            this.iceCandidatesQueue = [];
            this.remoteUserId = null;
        }
    }

    window.CallManager = new WebRTCCallManager();
    document.addEventListener('DOMContentLoaded', () => {
        if (window.CallManager) {
            window.CallManager.init();
        }
    });
</script>
@endif
