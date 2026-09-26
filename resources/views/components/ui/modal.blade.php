{{-- Модальное окно. Показывается, пока родитель рендерит его (@if). close — выражение Livewire для закрытия.
     width: s (480, подтверждение) | m (640, форма) | l (960, превью). Слоты: default (тело), footer (кнопки справа), note (слева в подвале). --}}
@props(['title', 'sub' => null, 'close', 'width' => 'm'])
@php $w = ['s' => 'max-w-modal-s', 'm' => 'max-w-modal-m', 'l' => 'max-w-modal-l'][$width]; @endphp
<div class="fixed inset-0 z-20 flex items-center justify-center bg-scrim p-4 lg:p-8" x-data x-on:keydown.escape.window="$wire.{{ $close }}">
    <div role="dialog" aria-modal="true" aria-labelledby="modal-title" {{ $attributes->class("flex max-h-full w-full flex-col overflow-hidden rounded-xl bg-white shadow-modal $w") }}>
        <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
            <div class="flex min-w-0 flex-col gap-1">
                <h2 id="modal-title" class="text-h2 font-medium">{{ $title }}</h2>
                @if ($sub)<p class="truncate text-t2 text-muted">{{ $sub }}</p>@endif
            </div>
            <x-ui.btn square icon="x" wire:click="{{ $close }}" aria-label="Закрыть" />
        </div>
        <div class="flex flex-col gap-4 overflow-y-auto p-6">{{ $slot }}</div>
        @isset($footer)
            <div class="flex items-center justify-between gap-4 border-t border-line px-6 py-4">
                <div class="min-w-0 text-t2 text-muted">{{ $note ?? '' }}</div>
                <div class="flex shrink-0 items-center gap-2">{{ $footer }}</div>
            </div>
        @endisset
    </div>
</div>
