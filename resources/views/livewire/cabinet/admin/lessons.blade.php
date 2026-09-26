{{-- Админка · Занятия. Макеты: AdminLessons (расписание), AdminSessions (проведённые, запросы на удаление), AdminRecordings. --}}
<div class="grid grid-cols-1 gap-6 lg:gap-8">
    <x-ui.page-head title="Занятия" :sub="$sub">
        <x-slot:actions>
            @if ($tab === 'schedule')
                <x-ui.btn icon="plus" wire:click="openCreate" class="hidden lg:inline-flex">Создать занятие</x-ui.btn>
                <x-ui.btn square icon="plus" wire:click="openCreate" class="lg:hidden" aria-label="Создать занятие" />
            @elseif ($tab === 'recordings')
                <x-ui.btn icon="repeat" wire:click="sync" wire:loading.attr="disabled" wire:target="sync" class="hidden lg:inline-flex">Обновить с сервера</x-ui.btn>
                <x-ui.btn square icon="repeat" wire:click="sync" wire:loading.attr="disabled" wire:target="sync" class="lg:hidden" aria-label="Обновить с сервера" />
            @endif
        </x-slot:actions>
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        <x-ui.tabs :items="['schedule' => 'Расписание', 'sessions' => 'Проведённые', 'deletions' => 'Запросы на удаление', 'recordings' => 'Записи']"
                   model="tab" :active="$tab" :counts="['deletions' => $pending]" aria-label="Занятия" />

        @if ($tab === 'schedule')
            @include('livewire.cabinet.admin.partials.lessons-schedule')
        @elseif ($tab === 'sessions')
            @include('livewire.cabinet.admin.partials.lessons-sessions')
        @elseif ($tab === 'deletions')
            @include('livewire.cabinet.admin.partials.lessons-deletions')
        @else
            @include('livewire.cabinet.admin.partials.lessons-recordings')
        @endif
    </div>

    @include('livewire.cabinet.admin.partials.lessons-create-modal')
    @include('livewire.cabinet.admin.partials.lessons-decision-modals')
</div>
