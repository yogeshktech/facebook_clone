@if (file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    <script src="https://cdn.tailwindcss.com"></script>
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
                    if (data.count > lastCount && data.notifications?.length && toastEl) {
                        const latest = data.notifications[0];
                        const title = latest.title || latest.message || 'NEWBOOK';
                        toastEl.innerHTML = '<p class="font-semibold text-sm">' + title + '</p><p class="text-xs text-gray-500">' + (latest.created_at_human || 'Just now') + '</p>';
                        toastEl.classList.remove('hidden');
                        setTimeout(() => toastEl.classList.add('hidden'), 5000);
                        playNotificationSound();
                        showSystemNotification(title, latest.message || '', latest.url || '/notifications');
                    }
                    lastCount = data.count;
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
    </script>
@endif
