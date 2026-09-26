{{-- Строка-переключатель на вкладке настроек: $group, $key, $title, $sub (необязательно), $on. --}}
<div class="flex items-center justify-between gap-4 border-t border-line py-4 first:border-t-0 first:pt-0 last:pb-0">
    <div class="flex min-w-0 flex-col gap-1">
        <span class="text-t1-s font-medium">{{ $title }}</span>
        @if (! empty($sub))<span class="text-t2 text-muted">{{ $sub }}</span>@endif
    </div>
    <x-ui.switch :checked="$on" :label="$title" wire:click="flip('{{ $group }}', '{{ $key }}')" />
</div>
