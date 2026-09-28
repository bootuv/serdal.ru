{{-- Пустой раздел: иконка на плитке 64, заголовок, одна строка, одна кнопка (слот action). --}}
@props(['icon' => 'folder', 'title', 'text' => null])
<div data-tour="empty" {{ $attributes->class('flex flex-col items-center gap-4 px-8 py-12 text-center') }}>
    <span class="flex size-16 items-center justify-center rounded-lg bg-white shadow-outline"><x-ui.icon :name="$icon" /></span>
    <div class="flex flex-col gap-2">
        <p class="text-h2 font-medium">{{ $title }}</p>
        @if ($text)<p class="max-w-text text-t2 text-muted">{{ $text }}</p>@endif
    </div>
    {{ $action ?? '' }}
</div>
