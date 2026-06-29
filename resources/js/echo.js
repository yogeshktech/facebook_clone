import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const configuredHost = import.meta.env.VITE_REVERB_HOST;
const reverbHost = (!configuredHost || configuredHost === 'localhost')
    ? window.location.hostname
    : configuredHost;
const reverbPort = import.meta.env.VITE_REVERB_PORT ?? (window.location.protocol === 'https:' ? 443 : 8080);
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? (window.location.protocol === 'https:' ? 'https' : 'http');

if (reverbKey && window.authUserId) {
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
