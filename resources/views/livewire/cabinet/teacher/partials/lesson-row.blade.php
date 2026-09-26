{{-- Строка занятия учителя (Сегодня, Расписание): время, [аватар], название, статус; у ближайшего — белая подсветка и главная кнопка.
     row — см. Concerns\LessonRows::row(); afterFocus — строка сразу после подсвеченной (без линии сверху). --}}
@if ($row['focus'])
    <div class="-mx-4 flex flex-col gap-3 rounded-lg bg-white p-4 shadow-card lg:flex-row lg:items-center lg:gap-4" wire:key="row-{{ $row['key'] }}">
        <div class="flex min-w-0 flex-1 items-center gap-4">
            <div class="flex w-16 shrink-0 flex-col gap-1">
                <span class="text-t1 font-semibold">{{ $row['time'] }}</span>
                @if ($row['duration'])<span class="text-t3 text-muted">{{ $row['duration'] }}</span>@endif
            </div>
            @if ($row['avatar'])
                <x-ui.avatar :name="$row['avatar']['name'] ?? ''" :id="$row['avatar']['id'] ?? 0" :group="$row['avatar']['group'] ?? false" />
            @endif
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <a href="{{ $row['url'] }}" class="truncate text-t1 font-medium hover:underline">{{ $row['heading'] }}</a>
                <span class="text-t2 text-muted">@if ($row['status'])<x-ui.em>{{ $row['status'] }}</x-ui.em>@if ($row['facts']){{ $row['glue'] ?? ' · ' }}@endif @endif{{ $row['facts'] }}</span>
            </div>
        </div>
        @if ($row['action'])
            @if ($row['action']['kind'] === 'join')
                <x-ui.btn variant="primary" icon="video" :href="$row['action']['url']" target="_blank" rel="noopener">Вернуться в класс</x-ui.btn>
            @elseif ($row['action']['kind'] === 'start')
                <x-ui.btn variant="primary" icon="play" :href="$row['action']['url']" target="_blank" rel="noopener">Начать занятие</x-ui.btn>
            @elseif ($row['action']['kind'] === 'blocked')
                <x-ui.btn variant="primary" icon="play" wire:click="$set('startBlockedOpen', true)">Начать занятие</x-ui.btn>
            @endif
        @endif
    </div>
@else
    <a href="{{ $row['url'] }}" wire:key="row-{{ $row['key'] }}"
       @class(['group flex items-center gap-4 border-t py-4 text-ink last:pb-0', 'first:border-t-0 first:pt-0' => $bare ?? false,
               'border-line' => ! ($afterFocus ?? false), 'border-transparent' => $afterFocus ?? false])>
        <div class="flex w-16 shrink-0 flex-col gap-1">
            <span @class(['text-t1 font-medium', 'text-muted' => $row['dim']])>{{ $row['time'] }}</span>
            @if ($row['duration'])<span class="text-t3 text-muted">{{ $row['duration'] }}</span>@endif
        </div>
        @if ($row['avatar'])
            <x-ui.avatar :name="$row['avatar']['name'] ?? ''" :id="$row['avatar']['id'] ?? 0" :group="$row['avatar']['group'] ?? false" :class="$row['dim'] ? 'opacity-60' : ''" />
        @endif
        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <span @class(['truncate text-t1 font-medium', 'text-muted' => $row['dim']])>{{ $row['heading'] }}</span>
            @if ($row['status'] || $row['facts'])
                <span class="text-t2 text-muted">@if ($row['status'])<x-ui.em>{{ $row['status'] }}</x-ui.em>@if ($row['facts']){{ $row['glue'] ?? ' · ' }}@endif @endif{{ $row['facts'] }}</span>
            @endif
        </div>
        @if ($row['badge'])<x-ui.badge :tone="$row['badge']['tone']">{{ $row['badge']['text'] }}</x-ui.badge>@endif
        <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
    </a>
@endif
