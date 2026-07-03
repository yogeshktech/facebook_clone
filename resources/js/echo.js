import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const runtime = window.reverbConfig || {};
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || runtime.key;
const configuredHost = import.meta.env.VITE_REVERB_HOST || runtime.host;
const reverbHost = (!configuredHost || configuredHost === 'localhost' || configuredHost === '127.0.0.1')
    ? window.location.hostname
    : configuredHost;
const defaultPort = window.location.protocol === 'https:' ? 443 : 8080;
const reverbPort = import.meta.env.VITE_REVERB_PORT || runtime.port || defaultPort;
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || runtime.scheme
    || (window.location.protocol === 'https:' ? 'https' : 'http');

if (!window.Echo && reverbKey && window.authUserId) {
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
