{{-- «Первые шаги» на пустой главной ученика (макет SyEmptyStudent). $steps — Home::steps().
     Уведомления: сервер знает о подписке хотя бы на одном устройстве, браузер уточняет для этого устройства.
     Нажатие включает их здесь же (браузер спросит разрешение); запрещены — окно «Уведомления заблокированы» (push-blocked);
     браузер без поддержки — переход в профиль. --}}
<div x-data="{
        push: {{ $steps['push'] ? 'true' : 'false' }},
        busy: false,
        async init() {
            const pn = window.PushNotifications;
            if (this.push || ! @js($steps['vapid']) || ! pn || ! pn.isSupported() || ! ('Notification' in window)) return;
            await pn.init(@js($steps['vapid']));
            if (Notification.permission === 'granted' && pn.isSubscribed) this.push = true;
        },
        async enable() {
            const pn = window.PushNotifications;
            if (this.push || this.busy) return;
            if (! @js($steps['vapid']) || ! pn || ! pn.isSupported() || ! ('Notification' in window)) { window.location = @js($steps['profileUrl']); return; }
            if (Notification.permission === 'denied') { $dispatch('push-blocked'); return; }
            this.busy = true;
            if (! pn.swRegistration) await pn.init(@js($steps['vapid']));
            const ok = await pn.subscribe();
            this.busy = false;
            if (ok) { this.push = true; $dispatch('toast', { message: 'Уведомления включены' }) }
            else if (Notification.permission === 'denied') { $dispatch('push-blocked') }
        },
    }">
<x-ui.card aria-labelledby="e-steps">
    <x-ui.card-head id="e-steps" title="Первые шаги">
        <x-slot:action>
            <span class="text-t2 text-muted"><x-ui.em><span x-text="(push ? 1 : 0) + {{ $steps['profile'] ? 1 : 0 }}">{{ ($steps['push'] ? 1 : 0) + ($steps['profile'] ? 1 : 0) }}</span> из 2</x-ui.em> готово</span>
        </x-slot:action>
    </x-ui.card-head>
    <x-ui.list>
        <x-ui.row :href="$steps['profileUrl']" x-on:click.prevent="enable()" x-bind:aria-busy="busy">
            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-ok-bg text-ok-fg" x-show="push" {!! $steps['push'] ? '' : 'x-cloak' !!}><x-ui.icon name="check" size="s" /></span>
            <span class="size-6 shrink-0 rounded-full shadow-outline" x-show="! push" {!! $steps['push'] ? 'x-cloak' : '' !!}></span>
            <x-ui.text title="Включите уведомления" sub="Чтобы не пропустить начало занятия" />
        </x-ui.row>
        <x-ui.row :href="$steps['profileUrl']">
            @if ($steps['profile'])
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-ok-bg text-ok-fg"><x-ui.icon name="check" size="s" /></span>
            @else
                <span class="size-6 shrink-0 rounded-full shadow-outline"></span>
            @endif
            <x-ui.text title="Заполните профиль" sub="Класс и цель занятий — учителю будет проще подготовиться" />
        </x-ui.row>
    </x-ui.list>
</x-ui.card>
</div>
