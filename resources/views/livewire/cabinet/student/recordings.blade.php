<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Записи" :sub="$sub" />

    <div class="flex flex-col gap-6">
        @if ($current)
            <button type="button" wire:click="close" class="inline-flex items-center gap-2 self-start text-t1-s font-medium text-muted hover:text-ink"><x-ui.icon name="arrow-left" size="s" />Все записи</button>
        @elseif ($teacherFilter || $hasAny)
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                @if ($teacherFilter)
                    <x-ui.seg :items="$teacherFilter" model="teacher" :active="$teacher" aria-label="Учитель" fit />
                @endif
                @if ($hasAny)
                    <x-ui.search wire:model.live.debounce.400ms="search" placeholder="Поиск по занятию или учителю" />
                @endif
            </div>
        @endif

        {{-- Плеер открытой записи --}}
        @if ($current)
            <section class="flex flex-col gap-6 lg:flex-row" aria-label="Открытая запись" wire:key="player-{{ $current['id'] }}">
                <x-ui.video-player :src="$current['video']" :title="$current['title']" :nodownload="! $current['downloadUrl']" class="lg:max-w-form lg:shrink-0" />
                <div class="flex min-w-0 flex-1 flex-col gap-6 lg:pt-2">
                    <div class="flex flex-col gap-2">
                        <h2 class="text-h2 font-medium">{{ $current['title'] }}</h2>
                        <span class="text-t2 text-muted">{{ $current['when'] }}</span>
                        @if ($current['expires'])
                            <span class="text-t2 text-muted">{{ $current['soon'] ? 'Удалится' : 'Хранится' }} <x-ui.em>{{ $current['expires'] }}</x-ui.em></span>
                        @endif
                    </div>
                    <div class="mt-auto flex gap-2">
                        @if ($current['downloadUrl'])
                            <x-ui.btn icon="download" :href="$current['downloadUrl']">Скачать</x-ui.btn>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if ($soon->isEmpty() && $weeks->isEmpty() && $searching)
            <x-ui.empty icon="search" title="Ничего не нашлось" text="Проверьте написание или поищите по имени учителя.">
                <x-slot:action><x-ui.btn wire:click="$set('search', '')">Сбросить поиск</x-ui.btn></x-slot:action>
            </x-ui.empty>
        @elseif ($soon->isEmpty() && $weeks->isEmpty())
            <x-ui.empty icon="video" title="Записей пока нет" text="Когда учитель запишет занятие, запись появится здесь." />
        @endif

        {{-- Фокус-блок: записи, которые скоро удалятся --}}
        @if ($soon->isNotEmpty())
            <x-ui.card focus aria-labelledby="rec-soon">
                <x-ui.card-head id="rec-soon" title="Скоро удалятся" />
                <x-ui.list>
                    @foreach ($soon as $r)
                        @include('livewire.cabinet.student.partials.recording-row', ['r' => $r, 'open' => $open, 'focus' => true])
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
                                    @include('livewire.cabinet.student.partials.recording-row', ['r' => $r, 'open' => $open, 'focus' => false])
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
</div>
