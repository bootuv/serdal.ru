{{-- Модальное окно. Показывается, пока родитель рендерит его (@if). close — метод Livewire или выражение ($set(…)) для закрытия.
     width: s (480, подтверждение) | m (640, форма) | l (960, превью). Слоты: default (тело), footer (кнопки справа), note (слева в подвале).
     fill — окно на всю высоту экрана, тело не прокручивается (рабочая область: холст пометок сам двигает фото). --}}
@props(['title', 'sub' => null, 'close', 'width' => 'm', 'fill' => false])
@php
    $w = ['s' => 'max-w-modal-s', 'm' => 'max-w-modal-m', 'l' => 'max-w-modal-l'][$width];
    $call = '$wire.' . (str_contains($close, '(') ? $close : $close . '()');
@endphp
{{-- Закрывается крестиком, Esc и кликом по затемнению. Клик считается, только если и нажатие, и отпускание
     пришлись на затемнение: выделение текста внутри окна, отпущенное снаружи, окно не закрывает. --}}
<div class="fixed inset-0 z-20 flex items-center justify-center bg-scrim p-4 lg:p-8" x-data="{ down: false }"
     x-on:keydown.escape.window="if (! document.body.dataset.lightbox) {{ $call }}"
     x-on:mousedown="down = $event.target === $el"
     x-on:click="if (down && $event.target === $el) {{ $call }}; down = false">
    <div role="dialog" aria-modal="true" aria-labelledby="modal-title" {{ $attributes->class(["flex max-h-full w-full flex-col overflow-hidden rounded-xl bg-white shadow-modal $w", 'h-full' => $fill]) }}>
        <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
            <div class="flex min-w-0 flex-col gap-1">
                <h2 id="modal-title" class="text-h2 font-medium">{{ $title }}</h2>
                @if ($sub)<p class="truncate text-t2 text-muted">{{ $sub }}</p>@endif
            </div>
            <x-ui.btn square icon="x" wire:click="{{ $close }}" aria-label="Закрыть" />
        </div>
        <div @class(['flex flex-col gap-4 p-6', 'min-h-0 flex-1' => $fill, 'overflow-y-auto' => ! $fill])>{{ $slot }}</div>
        @isset($footer)
            {{-- Телефон: пояснение сверху, кнопки под ним на всю ширину; с sm — пояснение слева, кнопки справа --}}
            <div class="flex flex-col gap-3 border-t border-line px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <div class="min-w-0 text-t2 text-muted empty:hidden sm:empty:block">{{ $note ?? '' }}</div>
                <div class="flex flex-wrap items-center gap-2 sm:shrink-0 sm:flex-nowrap">{{ $footer }}</div>
            </div>
        @endisset
    </div>
</div>
