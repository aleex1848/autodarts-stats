import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Debug: Zeige Konfiguration in Console
const echoConfig = {
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
};

console.log('🔌 Echo/Reverb Konfiguration:', {
    wsHost: echoConfig.wsHost,
    wsPort: echoConfig.wsPort,
    wssPort: echoConfig.wssPort,
    forceTLS: echoConfig.forceTLS,
    enabledTransports: echoConfig.enabledTransports,
    hasKey: !!echoConfig.key,
});

window.Echo = new Echo(echoConfig);

// Debug: Event-Handler für Verbindungsstatus
window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Reverb WebSocket verbunden');
});

window.Echo.connector.pusher.connection.bind('disconnected', () => {
    console.log('❌ Reverb WebSocket getrennt');
});

window.Echo.connector.pusher.connection.bind('error', (error) => {
    console.error('❌ Reverb WebSocket Fehler:', error);
});

window.Echo.connector.pusher.connection.bind('state_change', (states) => {
    console.log('🔄 Reverb WebSocket Status-Änderung:', {
        previous: states.previous,
        current: states.current,
    });
});

// Debug: Zeige Verbindungsversuch
window.Echo.connector.pusher.connection.bind('connecting', () => {
    console.log('🔄 Reverb WebSocket verbindet...');
});
