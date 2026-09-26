{{-- Notification Sound Script --}}
{{--
    Играет звук ТОЛЬКО для broadcast-уведомлений с флагом sound: true
    (см. App\Notifications\Traits\BroadcastsNotification и свойство $broadcastSound
    в классах уведомлений). Toast'ы-фидбек действий («Сохранено» и т.п.) беззвучны.
--}}
@auth
    @php
        $user = auth()->user();
        $soundChannel = method_exists($user, 'receivesBroadcastNotificationsOn')
            ? $user->receivesBroadcastNotificationsOn()
            : str_replace('\\', '.', $user::class) . '.' . $user->getKey();
    @endphp
    <script>
        (function () {
            // Защита от повторного выполнения (SPA-навигация, дублирование хука)
            if (window.__serdalNotificationSound) return;
            window.__serdalNotificationSound = true;

            const audio = new Audio('/sounds/notification.mp3');
            audio.preload = 'auto';

            let audioUnlocked = false;

            // Разблокировка автоплея первым жестом пользователя.
            // ВАЖНО: на iOS свойство volume игнорируется (read-only), поэтому
            // разблокируем через muted — беззвучно на всех платформах.
            function unlockAudio() {
                if (audioUnlocked) return;

                audio.muted = true;
                audio.play().then(() => {
                    audio.pause();
                    audio.currentTime = 0;
                    audio.muted = false;
                    audioUnlocked = true;
                }).catch(() => {
                    // Автоплей заблокирован — попробуем на следующем жесте
                    ['click', 'touchstart', 'keydown'].forEach((event) => {
                        document.addEventListener(event, unlockAudio, { once: true, passive: true });
                    });
                });
            }

            ['click', 'touchstart', 'keydown'].forEach((event) => {
                document.addEventListener(event, unlockAudio, { once: true, passive: true });
            });

            // Дедупликация: Filament может подписаться на канал дважды
            // (повторные EchoLoaded после Livewire-морфов), а id уведомления
            // стабилен — Laravel кладёт его в payload события.
            const playedIds = new Set();

            function playNotificationSound(id) {
                if (!audioUnlocked) return;

                if (id) {
                    if (playedIds.has(id)) return;
                    playedIds.add(id);
                }

                audio.muted = false;
                audio.volume = 0.5;
                audio.currentTime = 0;
                audio.play().catch(() => {});
            }

            let subscribed = false;

            function subscribe() {
                if (subscribed || !window.Echo) return;
                subscribed = true;

                window.Echo.private(@js($soundChannel)).notification((notification) => {
                    if (notification && notification.sound === true) {
                        playNotificationSound(notification.id);
                    }
                });
            }

            subscribe();
            document.addEventListener('DOMContentLoaded', subscribe);
            window.addEventListener('EchoLoaded', subscribe);
        })();
    </script>
@endauth
