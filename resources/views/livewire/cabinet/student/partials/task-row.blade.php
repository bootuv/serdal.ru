{{-- Строка задания в списке ученика. $item — из Tasks::item(). --}}
<x-ui.row :href="$item['url']" :align="$item['feedback'] ? 'start' : 'center'" wire:key="task-{{ $item['url'] }}">
    <div class="flex min-w-0 flex-1 flex-col gap-1">
        <span class="truncate text-t1 font-medium">{{ $item['title'] }}</span>
        @if ($item['meta'] || $item['em'])
            <span class="text-t2 text-muted">{{ $item['meta'] }}@if ($item['em'])@if ($item['meta']) · @endif<x-ui.em>{{ $item['em'] }}</x-ui.em>@endif</span>
        @endif
        @if ($item['feedback'])
            <span class="truncate text-t2 text-ink">«{{ $item['feedback'] }}»</span>
        @endif
    </div>
    @if ($item['badge'])
        <x-ui.badge :tone="$item['badge'][0]">{{ $item['badge'][1] }}</x-ui.badge>
    @endif
</x-ui.row>
