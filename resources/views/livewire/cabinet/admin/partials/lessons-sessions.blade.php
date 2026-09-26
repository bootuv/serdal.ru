{{-- Админка · Занятия · Проведённые (макет AdminSessions, «Все проведённые»). --}}
@include('livewire.cabinet.admin.partials.lessons-filters')

@if ($lessonName)
    <p class="text-t2 text-muted">Только занятие «{{ $lessonName }}» · <button type="button" wire:click="$set('lesson', '')" class="link">Показать все</button></p>
@endif

<x-ui.card class="gap-0" aria-label="Проведённые занятия">
    @if ($sessionRows->isEmpty())
        <p class="text-t2 text-muted">Пока пусто — за этот период занятий не было. <button type="button" wire:click="resetFilters" class="link">Сбросить фильтры</button></p>
    @else
        <div class="hidden gap-4 pb-3 text-t3 font-medium text-muted lg:grid lg:grid-cols-12">
            <span class="col-span-3">Занятие</span><span class="col-span-2">Учитель</span><span class="col-span-3">Когда</span><span class="col-span-2">Длительность</span><span class="col-span-1">Пришли</span><span class="col-span-1"></span>
        </div>
        <div class="flex flex-col">
            @foreach ($sessionRows as $r)
                <a href="{{ $r['url'] }}" class="group flex flex-col gap-1 border-t border-line py-4 last:pb-0 lg:grid lg:grid-cols-12 lg:items-center lg:gap-4" wire:key="ses-{{ $r['id'] }}">
                    <span class="flex min-w-0 flex-col gap-1 lg:col-span-3">
                        <span class="truncate text-t1 font-medium group-hover:underline">{{ $r['title'] }}</span>
                        @if ($r['request'])<span><x-ui.badge>Запрос на удаление</x-ui.badge></span>@endif
                    </span>
                    <span class="truncate text-t2 text-muted lg:col-span-2 lg:text-t1-s lg:text-ink">{{ $r['teacher'] }}</span>
                    <span class="text-t2 text-muted lg:col-span-3 lg:text-t1-s lg:text-ink">{{ $r['when'] }}</span>
                    <span @class(['text-t2 lg:col-span-2 lg:text-t1-s', 'font-semibold text-ink' => $r['live'] || $r['short'], 'text-muted lg:text-ink' => ! $r['live'] && ! $r['short']])>{{ $r['live'] ? 'идёт сейчас' : $r['dur'] }}</span>
                    <span @class(['text-t2 lg:col-span-1 lg:text-t1-s', 'font-semibold text-ink' => $r['nobody'], 'text-muted lg:text-ink' => ! $r['nobody']])>{{ $r['nobody'] ? 'никто' : ($r['came'] ?? '—') }}</span>
                    <span class="hidden justify-end lg:col-span-1 lg:flex"><x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" /></span>
                </a>
            @endforeach
        </div>
        @if ($hasMore)
            <x-ui.btn size="s" wire:click="showMore" class="mt-6 self-center">Показать ещё</x-ui.btn>
        @endif
    @endif
</x-ui.card>
