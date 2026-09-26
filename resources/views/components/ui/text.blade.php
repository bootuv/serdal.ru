{{-- Двухстрочный текст строки: название (t1) и подпись (t2). --}}
@props(['title', 'sub' => null])
<div {{ $attributes->class('flex min-w-0 flex-1 flex-col gap-1') }}>
    <span class="truncate text-t1 font-medium">{{ $title }}</span>
    @if ($sub || isset($subSlot))<span class="text-t2 text-muted">{{ $subSlot ?? $sub }}</span>@endif
</div>
