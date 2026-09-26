{{-- Строка диалога в списке «Сообщений». --}}
<button type="button" wire:click="open('{{ $d['key'] }}')" wire:key="dialog-{{ $d['key'] }}" @class($classes) @if ($current && $current['key'] === $d['key']) aria-current="true" @endif>
    @include('livewire.cabinet.messages.avatar', ['d' => $d])
    <span class="flex min-w-0 flex-1 flex-col">
        <span class="flex min-w-0 items-center gap-2">
            <span @class(['truncate text-t1-s', 'font-semibold' => $d['unread'], 'font-medium' => ! $d['unread']])>{{ $d['name'] }}</span>
            @if ($d['archived'])<x-ui.badge>Архив</x-ui.badge>@endif
            <span class="ml-auto shrink-0 text-t3 text-muted">{{ $d['time'] }}</span>
        </span>
        <span class="flex min-w-0 items-center gap-2">
            <span @class(['flex-1 truncate text-t2', 'text-ink' => $d['unread'], 'text-muted' => ! $d['unread']])>{{ $d['preview'] }}</span>
            <x-ui.count :value="$d['unread']" />
        </span>
    </span>
</button>
