@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="max-w-lg mx-auto p-4 space-y-4 pb-24 md:pb-8">
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h1 class="text-2xl font-bold">Settings</h1>
            <p class="text-sm text-gray-500 mt-1">App permissions and preferences</p>
        </div>

        <div class="divide-y">
            {{-- Notifications --}}
            <div class="p-4 flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-fb-blue/10 text-fb-blue flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold">Notifications</p>
                    <p class="text-sm text-gray-500" id="perm-notifications-status">Checking…</p>
                    <button type="button" id="perm-notifications-btn" class="mt-2 text-sm font-semibold text-fb-blue hover:underline">Enable notifications</button>
                </div>
            </div>

            {{-- Camera --}}
            <div class="p-4 flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-fb-blue/10 text-fb-blue flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold">Camera</p>
                    <p class="text-sm text-gray-500" id="perm-camera-status">Checking…</p>
                    <button type="button" id="perm-camera-btn" class="mt-2 text-sm font-semibold text-fb-blue hover:underline">Allow camera</button>
                </div>
            </div>

            {{-- Microphone --}}
            <div class="p-4 flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-fb-blue/10 text-fb-blue flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold">Microphone</p>
                    <p class="text-sm text-gray-500" id="perm-mic-status">Checking…</p>
                    <button type="button" id="perm-mic-btn" class="mt-2 text-sm font-semibold text-fb-blue hover:underline">Allow microphone</button>
                </div>
            </div>

            {{-- Location (optional display) --}}
            <div class="p-4 flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-fb-blue/10 text-fb-blue flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold">Location</p>
                    <p class="text-sm text-gray-500" id="perm-location-status">Checking…</p>
                    <button type="button" id="perm-location-btn" class="mt-2 text-sm font-semibold text-fb-blue hover:underline">Allow location</button>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4 text-sm text-gray-500">
        <p class="font-semibold text-gray-800 mb-1">Browser tip</p>
        <p>If a permission is blocked, open your browser site settings (lock icon in the address bar) and allow it there, then reload.</p>
    </div>
</div>

<script>
(function () {
    function setStatus(id, text, ok) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = text;
        el.className = 'text-sm ' + (ok === true ? 'text-green-600' : ok === false ? 'text-rose-600' : 'text-gray-500');
    }

    function labelState(state) {
        if (state === 'granted') return ['Allowed', true];
        if (state === 'denied') return ['Blocked — enable in browser settings', false];
        if (state === 'prompt') return ['Not set yet', null];
        return ['Unknown', null];
    }

    async function queryPermission(name) {
        if (!navigator.permissions?.query) return null;
        try {
            const result = await navigator.permissions.query({ name });
            return result.state;
        } catch (e) {
            return null;
        }
    }

    async function refresh() {
        const notif = typeof Notification !== 'undefined' ? Notification.permission : 'denied';
        const [nText, nOk] = labelState(notif === 'default' ? 'prompt' : notif);
        setStatus('perm-notifications-status', nText, nOk);

        const cam = await queryPermission('camera');
        if (cam) {
            const [t, ok] = labelState(cam);
            setStatus('perm-camera-status', t, ok);
        } else {
            setStatus('perm-camera-status', 'Tap Allow to request', null);
        }

        const mic = await queryPermission('microphone');
        if (mic) {
            const [t, ok] = labelState(mic);
            setStatus('perm-mic-status', t, ok);
        } else {
            setStatus('perm-mic-status', 'Tap Allow to request', null);
        }

        const loc = await queryPermission('geolocation');
        if (loc) {
            const [t, ok] = labelState(loc);
            setStatus('perm-location-status', t, ok);
        } else {
            setStatus('perm-location-status', 'Tap Allow to request', null);
        }
    }

    document.getElementById('perm-notifications-btn')?.addEventListener('click', async () => {
        if (typeof Notification === 'undefined') {
            alert('Notifications are not supported in this browser.');
            return;
        }
        const result = await Notification.requestPermission();
        if (result === 'granted' && window.registerFirebaseMessaging) {
            try { await window.registerFirebaseMessaging(); } catch (e) {}
        }
        refresh();
    });

    document.getElementById('perm-camera-btn')?.addEventListener('click', async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            stream.getTracks().forEach((t) => t.stop());
            setStatus('perm-camera-status', 'Allowed', true);
        } catch (e) {
            setStatus('perm-camera-status', 'Blocked — enable in browser settings', false);
        }
        refresh();
    });

    document.getElementById('perm-mic-btn')?.addEventListener('click', async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            stream.getTracks().forEach((t) => t.stop());
            setStatus('perm-mic-status', 'Allowed', true);
        } catch (e) {
            setStatus('perm-mic-status', 'Blocked — enable in browser settings', false);
        }
        refresh();
    });

    document.getElementById('perm-location-btn')?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            setStatus('perm-location-status', 'Not supported', false);
            return;
        }
        navigator.geolocation.getCurrentPosition(
            () => { setStatus('perm-location-status', 'Allowed', true); refresh(); },
            () => { setStatus('perm-location-status', 'Blocked — enable in browser settings', false); refresh(); }
        );
    });

    refresh();
})();
</script>
@endsection
