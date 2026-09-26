{{-- Шапка страницы: [← назад] → h1 → одна строка фактов; справа 1–3 действия. --}}
@props(['title', 'sub' => null, 'back' => null, 'backLabel' => null])
<div class="flex flex-col gap-4">
    @if ($back)
        <a href="{{ $back }}" class="inline-flex items-center gap-2 self-start text-t1-s font-medium text-muted hover:text-ink"><x-ui.icon name="arrow-left" size="s" />{{ $backLabel }}</a>
    @endif
    <header {{ $attributes->class('flex items-start justify-between gap-6') }}>
        <div class="flex min-w-0 flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">{{ $title }}</h1>
            @if ($sub)<p class="text-t1 text-muted">{{ $sub }}</p>@endif
        </div>
        @isset($actions)<div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>@endisset
    </header>
</div>
