{{-- Серверы видеосвязи: список с нагрузкой (строка открывает окно сервера), окно сервера, удаление. --}}
<div>
    <x-ui.card aria-labelledby="h-srv">
        <x-ui.card-head id="h-srv" title="Серверы видеосвязи">
            <x-slot:action><x-ui.btn size="s" icon="plus" wire:click="edit">Добавить</x-ui.btn></x-slot:action>
        </x-ui.card-head>
        @if ($servers->isEmpty())
            <p class="text-t2 text-muted">Пока пусто — добавьте сервер, иначе занятия не начнутся.</p>
        @else
            <x-ui.list>
                @foreach ($servers as $s)
                    <button type="button" wire:key="srv-{{ $s['id'] }}" wire:click="edit({{ $s['id'] }})" class="group flex items-center gap-4 border-t border-line py-4 text-left text-ink first:border-t-0 first:pt-0 last:pb-0">
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="flex min-w-0 flex-wrap items-center gap-2">
                                <span @class(['truncate text-t1 font-medium group-hover:underline', 'text-muted' => ! $s['enabled']])>{{ $s['name'] }}</span>
                                @if ($s['offline'])<x-ui.badge tone="danger">Не отвечает</x-ui.badge>@endif
                                @unless ($s['enabled'])<x-ui.badge>Не принимает занятия</x-ui.badge>@endunless
                            </span>
                            <span class="truncate text-t2 text-muted">{{ $s['sub'] }}</span>
                            <span class="text-t2 text-muted">{{ $s['load'] }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                    </button>
                @endforeach
            </x-ui.list>
            <span class="border-t border-line pt-4 text-t2 text-muted">Новое занятие начинается на сервере, где меньше всего занятых мест. Состояние обновляется раз в минуту.</span>
        @endif
    </x-ui.card>

    @if ($serverId !== null)
        <x-ui.modal :title="$serverId ? 'Сервер видеосвязи' : 'Новый сервер'" :sub="$editing?->user ? 'Только для учителя ' . $editing->user->name : 'Занятия делятся между серверами по нагрузке'" close="close">
            <x-ui.field label="Название" name="name" placeholder="Основной" wire:model="name" />
            <x-ui.field label="Адрес сервера" name="url" type="url" hint="Целиком, как его показывает команда установки сервера" wire:model="url" />
            <x-ui.password label="Секретный ключ" name="secret" wire:model="secret" autocomplete="off" :hint="$serverId ? 'Оставьте пустым, чтобы не менять' : null" />
            <x-ui.unit-field label="Вместимость" name="capacity" unit="участников" hint="Сколько человек сервер выдерживает одновременно — по ней делится нагрузка" wire:model="capacity" />
            <div class="flex items-start justify-between gap-4 rounded-lg p-4 shadow-line">
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="text-t1-s font-medium">Принимает новые занятия</span>
                    <span class="text-t2 text-muted">{{ $enabled ? 'Сервер получает новые занятия по нагрузке' : 'Идущие занятия доработают, новые сюда не пойдут' }}</span>
                </div>
                <x-ui.switch :checked="$enabled" label="Принимает новые занятия" wire:click="$toggle('enabled')" />
            </div>
            <x-slot:note>
                @if ($serverId)<button type="button" wire:click="askDelete({{ $serverId }})" class="link">Удалить сервер</button>@endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="close">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($deleting)
        <x-ui.modal title="Удалить сервер?" :sub="$deleting->name" close="cancelDelete" width="s">
            @if ($deletingRunning)
                <p class="text-t1-s">{{ $deletingRunning > 1 ? 'Сейчас на сервере идут занятия — удалить его можно, когда они закончатся.' : 'Сейчас на сервере идёт занятие — удалить его можно, когда оно закончится.' }}</p>
                <p class="text-t2 text-muted">Чтобы новые занятия сюда не шли, выключите «Принимает новые занятия».</p>
                <x-slot:footer>
                    <x-ui.btn wire:click="cancelDelete">Понятно</x-ui.btn>
                </x-slot:footer>
            @else
                <p class="text-t1-s">Новые занятия на него не пойдут. Записи занятий, которые остались на сервере, из списка не пропадут.</p>
                <x-slot:footer>
                    <x-ui.btn wire:click="cancelDelete">Отмена</x-ui.btn>
                    <x-ui.btn variant="dark" wire:click="delete">Удалить</x-ui.btn>
                </x-slot:footer>
            @endif
        </x-ui.modal>
    @endif
</div>
