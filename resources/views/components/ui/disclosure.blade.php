{{-- Раскрывающаяся секция (история по месяцам и т.п.): строка-заголовок со стрелкой, содержимое под ней. --}}
@props(['title', 'meta' => null, 'open' => false])
<details {{ $attributes->class('group border-t border-line') }} @if($open) open @endif>
    <summary class="flex cursor-pointer list-none items-center gap-4 py-4">
        <span class="flex-1 text-t1 font-medium">{{ $title }}</span>
        @if ($meta)<span class="text-t2 text-muted">{{ $meta }}</span>@endif
        <x-ui.icon name="chevron-down" class="text-faint transition-transform group-open:rotate-180" />
    </summary>
    <div class="flex flex-col pb-4">{{ $slot }}</div>
</details>
