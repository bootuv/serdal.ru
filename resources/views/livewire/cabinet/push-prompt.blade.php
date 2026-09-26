{{-- Окна про уведомления в браузере. Показ «Включить?» решает браузер: только если уведомления поддерживаются и ещё не включены здесь. --}}
<div x-data="{
        ask: false,
        busy: false,
        async init() {
            if (! $wire.mayAsk || ! @js($vapid) || ! window.PushNotifications || ! window.PushNotifications.isSupported() || ! ('Notification' in window)) return;
            if (Notification.permission === 'denied') return;
            await window.PushNotifications.init(@js($vapid));
            if (Notification.permission === 'granted' && window.PushNotifications.isSubscribed) return;
            setTimeout(() => this.ask = true, 1500);
        },
        async enable() {
            this.busy = true;
            const ok = await window.PushNotifications.subscribe();
            this.busy = false;
            this.ask = false;
            if (ok) { $wire.enabled() } else if (Notification.permission === 'denied') { $wire.showBlocked() }
        },
    }">
    <template x-if="ask">
        <div class="fixed inset-0 z-20 flex items-center justify-center bg-scrim p-4" x-on:keydown.escape.window="ask = false; $wire.later()">
            <div role="dialog" aria-modal="true" aria-labelledby="push-title" class="flex w-full max-w-modal-s flex-col overflow-hidden rounded-xl bg-white shadow-modal">
                <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
                    <h2 id="push-title" class="text-h2 font-medium">Включить уведомления?</h2>
                    <x-ui.btn square icon="x" x-on:click="ask = false; $wire.later()" aria-label="Закрыть" />
                </div>
                <p class="p-6 text-t1">Скажем, когда начнётся занятие, придёт работа или сообщение, — даже если вкладка Serdal закрыта.</p>
                <div class="flex items-center justify-between gap-4 border-t border-line px-6 py-4">
                    <span class="text-t2 text-muted">Браузер спросит разрешение</span>
                    <div class="flex shrink-0 items-center gap-2">
                        <x-ui.btn x-on:click="ask = false; $wire.later()">Не сейчас</x-ui.btn>
                        <x-ui.btn variant="primary" x-on:click="enable()" x-bind:disabled="busy">Включить</x-ui.btn>
                    </div>
                </div>
            </div>
        </div>
    </template>

    @if ($blocked)
        <x-ui.modal title="Уведомления заблокированы" close="closeBlocked" width="s">
            <p class="text-t1">Их запретили в настройках браузера, поэтому можно пропустить начало занятия. Чтобы включить:</p>
            <ol class="flex list-decimal flex-col gap-2 pl-6 text-t1">
                <li>Нажмите на значок <x-ui.icon name="lock" size="s" class="inline" /> слева от адреса serdal.ru</li>
                <li>В пункте «Уведомления» выберите «Разрешить»</li>
                <li>Обновите страницу</li>
            </ol>
            <p class="text-t2 text-muted">На телефоне — в настройках браузера или телефона.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closeBlocked">Закрыть</x-ui.btn>
                <x-ui.btn variant="primary" icon="repeat" x-on:click="window.location.reload()">Обновить страницу</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
