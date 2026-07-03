import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const runtime = window.reverbConfig || {};
// Server-injected config wins over Vite build-time vars (local npm build → live deploy)
const reverbKey = runtime.key || import.meta.env.VITE_REVERB_APP_KEY;
const configuredHost = runtime.host || import.meta.env.VITE_REVERB_HOST;
const reverbHost = (!configuredHost || configuredHost === 'localhost' || configuredHost === '127.0.0.1')
    ? window.location.hostname
    : configuredHost;
const defaultPort = window.location.protocol === 'https:' ? 443 : 8080;
const reverbPort = runtime.port || import.meta.env.VITE_REVERB_PORT || defaultPort;
const reverbScheme = runtime.scheme || import.meta.env.VITE_REVERB_SCHEME
    || (window.location.protocol === 'https:' ? 'https' : 'http');

if (!(window.Echo && typeof window.Echo.private === 'function') && reverbKey && window.authUserId) {
    window.Echo = new Echo({
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
}
