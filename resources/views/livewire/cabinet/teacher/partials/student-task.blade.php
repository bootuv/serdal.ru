{{-- Карточка ученика: строка задания. Норму не подсвечиваем, срочное — жирным. --}}
<x-ui.row :href="$t['url']" wire:key="hw-{{ $t['id'] }}">
    <div class="flex min-w-0 flex-1 flex-col gap-1">
        <span class="truncate text-t1 font-medium">{{ $t['title'] }}</span>
        @if ($t['state'] === 'review')
            <span class="text-t2 text-muted">{{ $t['sub'] }}</span>
        @elseif ($t['sub'] || $t['urgent'])
            <span class="text-t2 text-muted">
                {{ $t['sub'] }}@if ($t['sub'] && $t['urgent']) · @endif
                @if ($t['urgent'])<x-ui.em>{{ $t['urgent'] }}</x-ui.em>@endif
            </span>
        @endif
    </div>
    @if ($t['state'] === 'review')
        <span class="shrink-0 text-t2 font-semibold text-ink">{{ $t['urgent'] }}</span>
    @elseif ($t['state'] === 'revision')
        <x-ui.badge tone="danger">На доработке</x-ui.badge>
    @elseif ($t['state'] === 'overdue')
        <x-ui.badge tone="danger">Просрочено</x-ui.badge>
    @elseif ($t['state'] === 'graded' && $t['grade'])
        <x-ui.badge tone="ok">Оценка {{ $t['grade'] }}</x-ui.badge>
    @elseif ($t['state'] === 'todo')
        <x-ui.badge>Ещё не сдано</x-ui.badge>
    @endif
</x-ui.row>
