{{-- Записи занятий учителя. Макет: TeacherRecordings. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Записи" :sub="$sub">
        @unless ($isEmpty)
            <x-slot:actions>
                @if ($selecting)
                    <span class="hidden text-t2 lg:inline"><x-ui.em>{{ $picked ? 'Выбрано: ' . count($picked) : 'Выберите записи' }}</x-ui.em></span>
                    <x-ui.btn wire:click="cancelSelect">Отмена</x-ui.btn>
                    <x-ui.btn variant="dark" wire:click="askDelete" :disabled="! $picked">Удалить</x-ui.btn>
                @else
                    <x-ui.btn wire:click="startSelect">Выбрать</x-ui.btn>
                @endif
            </x-slot:actions>
        @endunless
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        @if ($studentOptions)
            <div class="w-full lg:w-sidebar">
                <x-ui.select name="student" :options="$studentOptions" wire:model.live="student" aria-label="Ученик" />
            </div>
        @endif

        {{-- Плеер открытой записи --}}
        @if ($current)
            <section class="flex flex-col gap-6 lg:flex-row" aria-label="Открытая запись" wire:key="player-{{ $current['id'] }}">
                <div class="w-full overflow-hidden rounded-lg bg-ink lg:max-w-form lg:shrink-0">
                    <video class="aspect-video w-full" src="{{ $current['video'] }}" controls preload="metadata" playsinline>
                        Ваш браузер не поддерживает воспроизведение видео.
                    </video>
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-6 lg:pt-2">
                    <div class="flex flex-col gap-2">
                        <h2 class="text-h2 font-medium">{{ $current['title'] }}</h2>
                        <span class="text-t2 text-muted">{{ $current['meta'] }}</span>
                        @if ($current['expires'])
                            <span class="text-t2 text-muted">{{ $current['soon'] ? 'Удалится' : 'Хранится' }} <x-ui.em>{{ $current['expires'] }}</x-ui.em></span>
                        @endif
                    </div>
                    <div class="mt-auto flex gap-2">
                        @if ($current['downloadUrl'])
                            <x-ui.btn icon="download" :href="$current['downloadUrl']">Скачать</x-ui.btn>
                        @endif
                        <x-ui.btn square icon="x" wire:click="close" aria-label="Закрыть запись" />
                    </div>
                </div>
            </section>
        @endif

        @if ($isEmpty)
            <x-ui.empty icon="video" title="Записей пока нет"
                        :text="$student !== '' ? 'У этого ученика пока нет записанных занятий.' : 'Включите запись на занятии — после его окончания запись появится здесь.'" />
        @endif

        @if ($selecting && $picked)
            <p class="text-t2 lg:hidden"><x-ui.em>Выбрано: {{ count($picked) }}</x-ui.em></p>
        @endif

        {{-- Фокус-блок: записи, которые скоро удалятся --}}
        @if ($soon->isNotEmpty())
            <x-ui.card focus aria-labelledby="rec-soon">
                <x-ui.card-head id="rec-soon" title="Скоро удалятся">
                    <x-slot:action><span class="text-t2 text-muted">Скачайте, если нужны</span></x-slot:action>
                </x-ui.card-head>
                <x-ui.list>
                    @foreach ($soon as $r)
                        @include('livewire.cabinet.teacher.partials.recording-row', ['r' => $r, 'focus' => true])
                    @endforeach
                </x-ui.list>
            </x-ui.card>
        @endif

        @if ($weeks->isNotEmpty())
            <x-ui.card aria-label="Все записи">
                <div class="flex flex-col gap-6">
                    @foreach ($weeks as $week)
                        <div class="flex flex-col gap-4" wire:key="week-{{ $loop->index }}">
                            <h2 class="text-t2 font-medium text-muted">{{ $week['title'] }}</h2>
                            <x-ui.list>
                                @foreach ($week['items'] as $r)
                                    @include('livewire.cabinet.teacher.partials.recording-row', ['r' => $r, 'focus' => false])
                                @endforeach
                            </x-ui.list>
                        </div>
                    @endforeach
                </div>
                @if ($hasMore)
                    <x-ui.btn size="s" wire:click="showMore" class="self-start">Показать ещё</x-ui.btn>
                @endif
            </x-ui.card>
        @endif
    </div>

    {{-- Подтверждение удаления выбранных --}}
    @if ($confirmDelete && $picked)
        <x-ui.modal :title="'Удалить ' . plural_ru(count($picked), 'запись', 'записи', 'записей') . '?'" width="s" close="$set('confirmDelete', false)">
            <p class="text-t1">Записи удалятся безвозвратно — и у вас, и у учеников. Если какие-то нужны, сначала скачайте их.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('confirmDelete', false)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="deleteSelected">Удалить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
