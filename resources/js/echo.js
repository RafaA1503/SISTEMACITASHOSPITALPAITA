import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const reverbUsesTLS = (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: reverbUsesTLS,
    // Sin esto, pusher-js intenta wss:// igual aunque forceTLS sea false
    // (falla contra el CSP y contra un Reverb local sin TLS).
    enabledTransports: reverbUsesTLS ? ['ws', 'wss'] : ['ws'],
});
