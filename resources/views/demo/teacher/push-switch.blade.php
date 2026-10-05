{{-- Демо (App\Demo\Teacher\Profile): переключатель уведомлений из livewire/cabinet/push-switch без подписки в браузере —
     настоящий ходит в window.PushNotifications и @entangle, которых в демо нет. Переключается только на экране. --}}
<div class="flex flex-col gap-4" x-data="{ on: true }">
    <div class="flex min-h-9 items-center justify-between gap-4">
        <h2 id="pf-notify" class="text-h2 font-medium">Уведомления</h2>
        <div class="flex shrink-0">
            <x-ui.switch checked label="Уведомления" x-show="on" x-on:click="on = false" />
            <x-ui.switch label="Уведомления" x-show="! on" x-cloak x-on:click="on = true" />
        </div>
    </div>
    <div class="flex flex-col gap-1">
        <span class="text-t1 font-medium" x-text="on ? 'Включены на этом устройстве' : 'Выключены на этом устройстве'">Включены на этом устройстве</span>
        <span class="text-t2 text-muted">Начало занятия, задания и оценки, сообщения, расписание и оплата</span>
    </div>
</div>
