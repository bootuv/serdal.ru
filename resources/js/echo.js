import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Ключ Reverb запекается в бандл на этапе `npm run build` из VITE_REVERB_*.
// Если сборка шла без этих переменных (например, в CI без .env), Pusher бросает
// исключение «You must pass your app key» прямо при импорте — и вместе с ним
// падает весь app.js: не инициализируются push-уведомления и остальные скрипты.
// Поэтому без ключа Echo не создаём, а только предупреждаем в консоли.
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (error) {
        console.error('Laravel Echo не инициализирован:', error);
    }
} else {
    console.warn('VITE_REVERB_APP_KEY не задан при сборке — WebSocket (чат, уведомления в реальном времени) отключён.');
}
