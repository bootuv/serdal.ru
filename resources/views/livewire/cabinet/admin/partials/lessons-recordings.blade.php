{{-- Админка · Занятия · Записи (макет AdminRecordings): смотреть в окне, скачать, удалить (и с сервера видеосвязи). --}}
@include('livewire.cabinet.admin.partials.lessons-filters')

<x-ui.card class="gap-0" aria-label="Записи занятий">
    @if ($recordingRows->isEmpty())
        <p class="text-t2 text-muted">Записей за этот период нет. <button type="button" wire:click="resetFilters" class="link">Сбросить фильтры</button></p>
    @else
        <div class="hidden gap-4 pb-3 text-t3 font-medium text-muted lg:grid lg:grid-cols-12">
            <span class="col-span-3">Занятие</span><span class="col-span-2">Учитель</span><span class="col-span-3">Когда</span><span class="col-span-2">Длительность</span><span class="col-span-1">В классе</span><span class="col-span-1"></span>
        </div>
        <div class="flex flex-col">
            @foreach ($recordingRows as $r)
                <div class="group flex items-center gap-4 border-t border-line py-4 last:pb-0 lg:grid lg:grid-cols-12" wire:key="rec-{{ $r['id'] }}">
                    <div class="flex min-w-0 flex-1 items-center gap-3 lg:col-span-3">
                        @if ($r['canWatch'])
                            <button type="button" wire:click="play({{ $r['id'] }})" class="flex size-10 shrink-0 items-center justify-center rounded bg-soft text-ink hover:shadow-outline-ink" aria-label="Смотреть запись: {{ $r['title'] }}"><x-ui.icon name="play" /></button>
                        @else
                            <span class="flex size-10 shrink-0 items-center justify-center rounded bg-soft text-muted" aria-hidden="true"><x-ui.icon name="clock" /></span>
                        @endif
                        <div class="flex min-w-0 flex-col gap-1">
                            @if ($r['canWatch'])
                                <button type="button" wire:click="play({{ $r['id'] }})" class="truncate text-left text-t1 font-medium hover:underline">{{ $r['title'] }}</button>
                            @else
                                <span class="truncate text-t1 font-medium">{{ $r['title'] }}</span>
                            @endif
                            @if ($r['status'] === 'processing')<span><x-ui.badge>Обрабатывается</x-ui.badge></span>
                            @elseif ($r['status'] === 'uploading')<span><x-ui.badge>Загружается</x-ui.badge></span>@endif
                            <span class="truncate text-t2 text-muted lg:hidden">{{ collect([$r['teacher'], $r['when'], $r['dur']])->filter()->implode(' · ') }}</span>
                        </div>
                    </div>
                    <span class="hidden truncate text-t1-s lg:col-span-2 lg:block">{{ $r['teacher'] }}</span>
                    <span class="hidden text-t1-s lg:col-span-3 lg:block">{{ $r['when'] }}</span>
                    <span class="hidden text-t1-s lg:col-span-2 lg:block">{{ $r['dur'] }}</span>
                    <span class="hidden whitespace-nowrap text-t2 lg:col-span-1 lg:block">{{ $r['people'] }}</span>
                    <div class="flex shrink-0 justify-end lg:col-span-1">
                        <x-ui.menu label="Действия с записью">
                            @if ($r['canWatch'])<x-ui.menu-item wire:click="play({{ $r['id'] }})">Смотреть</x-ui.menu-item>@endif
                            @if ($r['downloadUrl'])<x-ui.menu-item x-on:click="window.location = '{{ $r['downloadUrl'] }}'">Скачать</x-ui.menu-item>@endif
                            <x-ui.menu-item wire:click="askDeleteRecording({{ $r['id'] }})">Удалить</x-ui.menu-item>
                        </x-ui.menu>
                    </div>
                </div>
            @endforeach
        </div>
        @if ($hasMore)
            <x-ui.btn size="s" wire:click="showMore" class="mt-6 self-center">Показать ещё</x-ui.btn>
        @endif
    @endif
</x-ui.card>

{{-- Плеер --}}
@if ($player)
    <x-ui.modal :title="$player['title']" :sub="$player['meta']" width="l" close="closePlayer" wire:key="player-{{ $player['id'] }}">
        @if ($player['video'])
            <div class="overflow-hidden rounded-lg bg-ink">
                <video class="aspect-video w-full" src="{{ $player['video'] }}" controls preload="metadata" playsinline>Ваш браузер не поддерживает воспроизведение видео.</video>
            </div>
        @elseif ($player['externalUrl'])
            <div class="flex aspect-video w-full flex-col items-center justify-center gap-4 rounded-lg bg-ink p-6 text-center">
                <x-ui.btn variant="primary" icon="play" :href="$player['externalUrl']" target="_blank" rel="noopener">Смотреть запись</x-ui.btn>
            </div>
            <p class="text-t2 text-muted">Запись ещё копируется в хранилище Serdal: смотреть уже можно, скачать — через несколько минут.</p>
        @endif
        <x-slot:note><button type="button" wire:click="askDeleteRecording({{ $player['id'] }})" class="link">Удалить запись</button></x-slot:note>
        <x-slot:footer>
            @if ($player['downloadUrl'])
                <x-ui.btn icon="download" :href="$player['downloadUrl']">Скачать</x-ui.btn>
            @else
                <x-ui.btn icon="download" disabled>Скачать</x-ui.btn>
            @endif
        </x-slot:footer>
    </x-ui.modal>
@endif

{{-- Удалить запись --}}
@if ($recordingToDelete)
    <x-ui.modal title="Удалить запись?" :sub="$recordingToDelete['sub']" width="s" close="closeDeleteRecording">
        <p class="text-t1">Запись пропадёт у учителя и учеников и удалится с сервера видеосвязи. <x-ui.em>Вернуть её нельзя.</x-ui.em></p>
        <p class="text-t2 text-muted">Отчёт о занятии и статистика останутся.</p>
        <x-slot:footer>
            <x-ui.btn wire:click="closeDeleteRecording">Отмена</x-ui.btn>
            <x-ui.btn variant="dark" wire:click="deleteRecording" wire:loading.attr="disabled" wire:target="deleteRecording">Удалить запись</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>
@endif
