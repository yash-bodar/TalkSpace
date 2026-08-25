import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

// YB - 25-08-2026 - Inject socket ID into all Axios requests so broadcast()->toOthers() excludes sender correctly
window.axios.interceptors.request.use((config) => {
    if (window.Echo && window.Echo.socketId()) {
        config.headers['X-Socket-ID'] = window.Echo.socketId();
    }
    return config;
});


window.Pusher = Pusher;

// YB - 25-08-2026 - Dynamically adapt WebSocket connection to active protocol (HTTP/ws vs HTTPS/wss)
const isHttps = window.location.protocol === 'https:';
const host = window.location.hostname;
const port = Number(import.meta.env.VITE_REVERB_PORT) || 8080;

const getAuthEndpoint = () => {
    const path = window.location.pathname;
    const subPathMatch = path.match(/^(.*\/public)/i);
    if (subPathMatch) {
        return `${subPathMatch[1]}/broadcasting/auth`;
    }
    return '/broadcasting/auth';
};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: isHttps,
    enabledTransports: ['ws', 'wss'],
    enableStats: false,
    authEndpoint: getAuthEndpoint(),
});

