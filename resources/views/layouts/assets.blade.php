@if (file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    <script src="https://cdn.tailwindcss.com"></script>
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
            const toastEl = document.getElementById('notification-toast');
            if (!countEl) return;
            let lastCount = 0;
            const poll = async () => {
                try {
                    const res = await fetch('/notifications/unread', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data.count > 0) {
                        countEl.textContent = data.count > 9 ? '9+' : data.count;
                        countEl.classList.remove('hidden');
                    } else {
                        countEl.classList.add('hidden');
                    }
                    if (data.count > lastCount && data.notifications?.length && toastEl) {
                        const latest = data.notifications[0];
                        toastEl.innerHTML = '<p class="font-semibold text-sm">' + (latest.data?.message || 'New notification') + '</p><p class="text-xs text-gray-500">' + latest.created_at + '</p>';
                        toastEl.classList.remove('hidden');
                        setTimeout(() => toastEl.classList.add('hidden'), 5000);
                    }
                    lastCount = data.count;
                } catch (e) {}
            };
            poll();
            setInterval(poll, 10000);
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
    </script>
@endif
