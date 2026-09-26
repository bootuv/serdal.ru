{{-- Записи занятий учителя. Макет: TeacherRecordings. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Записи" :sub="$sub" />

    <div class="flex flex-col gap-6">
        @if ($current)
            <button type="button" wire:click="close" class="inline-flex items-center gap-2 self-start text-t1-s font-medium text-muted hover:text-ink"><x-ui.icon name="arrow-left" size="s" />Все записи</button>
        @elseif ($studentOptions || $hasAny)
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                @if ($studentOptions)
                    <div class="w-full lg:w-sidebar">
                        <x-ui.select name="student" :options="$studentOptions" wire:model.live="student" aria-label="Ученик" />
                    </div>
                @endif
                @if ($hasAny)
                    <x-ui.search wire:model.live.debounce.400ms="search" placeholder="Поиск по занятию или ученику" />
                @endif
            </div>
        @endif

        {{-- Плеер открытой записи --}}
        @if ($current)
            <section class="flex flex-col gap-6 lg:flex-row" aria-label="Открытая запись" wire:key="player-{{ $current['id'] }}">
                <x-ui.video-player :src="$current['video']" :title="$current['title']" class="lg:max-w-form lg:shrink-0" />
                <div class="flex min-w-0 flex-1 flex-col gap-6 lg:pt-2">
                    <div class="flex flex-col gap-2">
                        <h2 class="text-h2 font-medium">{{ $current['title'] }}</h2>
                        <span class="text-t2 text-muted">{{ $current['meta'] }}</span>
                        @if ($current['expires'])
                            <span class="text-t2 text-muted">{{ $current['soon'] ? 'Удалится' : 'Хранится' }} <x-ui.em>{{ $current['expires'] }}</x-ui.em></span>
                        @endif
                    </div>
                    <div class="mt-auto flex items-center gap-2">
                        @if ($current['downloadUrl'])
                            <x-ui.btn icon="download" :href="$current['downloadUrl']">Скачать</x-ui.btn>
                        @endif
                        <x-ui.menu label="Действия с записью" align="left">
                            <x-ui.menu-item wire:click="askDelete">Удалить запись</x-ui.menu-item>
                        </x-ui.menu>
                    </div>
                </div>
            </section>
        @endif

        @if ($isEmpty && $searching)
            <x-ui.empty icon="search" title="Ничего не нашлось" text="Проверьте написание или поищите по имени ученика.">
                <x-slot:action><x-ui.btn wire:click="$set('search', '')">Сбросить поиск</x-ui.btn></x-slot:action>
            </x-ui.empty>
        @elseif ($isEmpty)
            <x-ui.empty icon="video" title="Записей пока нет"
                        :text="$student !== '' ? 'У этого ученика пока нет записанных занятий.' : 'Включите запись на занятии — после его окончания запись появится здесь.'" />
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

    {{-- Подтверждение удаления открытой записи --}}
    @if ($confirmDelete && $current)
        <x-ui.modal title="Удалить запись?" :sub="$current['title']" width="s" close="$set('confirmDelete', false)">
            <p class="text-t1">Запись удалится безвозвратно — и у вас, и у учеников. Если она нужна, сначала скачайте её.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('confirmDelete', false)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="deleteOpen">Удалить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
