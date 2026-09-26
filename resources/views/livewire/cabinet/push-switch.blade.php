{{-- Переключатель push-уведомлений в профиле (новые кабинеты). Компонент App\Livewire\PushNotificationToggle, подписка — window.PushNotifications. --}}
<div class="flex flex-col gap-4" x-data="{
        on: @entangle('isSubscribed'),
        busy: false,
        supported: true,
        denied: false,

        async init() {
            this.supported = 'serviceWorker' in navigator && 'PushManager' in window;
            if (! this.supported) return;

            if (Notification.permission === 'denied') {
                this.denied = true;
                return;
            }

            const vapidKey = '{{ $this->getVapidPublicKey() }}';
            if (vapidKey && window.PushNotifications) {
                await window.PushNotifications.init(vapidKey);
                this.on = await window.PushNotifications.checkSubscription();
            }
        },

        async toggle() {
            if (! this.supported || this.denied || ! window.PushNotifications) return;
            this.busy = true;
            try {
                const success = await window.PushNotifications.toggle();
                if (success) {
                    this.on = ! this.on;
                } else if (Notification.permission === 'denied') {
                    this.denied = true;
                    $dispatch('push-blocked');
                }
            } catch (error) {
                console.error('Failed to toggle push subscription:', error);
            } finally {
                this.busy = false;
            }
        }
    }">
    <div class="flex min-h-9 items-center justify-between gap-4">
        <h2 id="pf-notify" class="text-h2 font-medium">Уведомления</h2>
        {{-- Состояние меняется в браузере: два положения переключателя, видно одно --}}
        <div class="flex shrink-0" x-show="supported && ! denied" x-cloak>
            <x-ui.switch checked label="Уведомления" x-show="on" x-on:click="toggle()" x-bind:disabled="busy" class="disabled:opacity-50" />
            <x-ui.switch label="Уведомления" x-show="! on" x-on:click="toggle()" x-bind:disabled="busy" class="disabled:opacity-50" />
        </div>
    </div>
    <div class="flex flex-col gap-1">
        <span class="text-t1 font-medium"
              x-text="denied ? 'Заблокированы в настройках браузера' : (! supported ? 'Этот браузер не поддерживает уведомления' : (on ? 'Включены на этом устройстве' : 'Выключены на этом устройстве'))">{{ $isSubscribed ? 'Включены на этом устройстве' : 'Выключены на этом устройстве' }}</span>
        <span class="text-t2 text-muted"
              x-text="denied ? 'Разрешите уведомления для сайта в настройках браузера и обновите страницу.' : 'Начало занятия, задания и оценки, сообщения, расписание и оплата'">Начало занятия, задания и оценки, сообщения, расписание и оплата</span>
        <button type="button" class="link self-start text-t2" x-show="denied" x-cloak x-on:click="$dispatch('push-blocked')">Как включить</button>
    </div>
</div>
