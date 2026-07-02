{{-- Loaded when Vite build is missing (CDN fallback path). Provides Reverb/Echo for chat + calls. --}}
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@2.3.7/dist/echo.iife.js"></script>
<script>
(function () {
    if (!window.authUserId || window.Echo) return;

    const runtime = window.reverbConfig || {};
    const reverbKey = runtime.key;
    if (!reverbKey) {
        console.warn('Reverb key missing — set REVERB_APP_KEY in .env and run php artisan config:clear');
        return;
    }

    const configuredHost = runtime.host;
    const reverbHost = (!configuredHost || configuredHost === 'localhost' || configuredHost === '127.0.0.1')
        ? window.location.hostname
        : configuredHost;
    const defaultPort = window.location.protocol === 'https:' ? 443 : 8080;
    const reverbPort = runtime.port || defaultPort;
    const reverbScheme = runtime.scheme || (window.location.protocol === 'https:' ? 'https' : 'http');

    window.Pusher = Pusher;
    const EchoClass = Echo.default || Echo;
    window.Echo = new EchoClass({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        },
    });
})();
</script>
