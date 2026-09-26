{{-- Вариант выбора строкой: галочка (checkbox) или радиокнопка (radio) + подпись. Выбранный — тёмная обводка.
     title/sub — текст; либо свой слот (например, аватар и текст). Атрибуты (wire:model, value, name) — на input. --}}
@props(['type' => 'checkbox', 'title' => null, 'sub' => null])
<label class="option flex cursor-pointer items-center gap-3 rounded-lg bg-white px-4 py-3 shadow-line hover:shadow-outline" @if ($attributes->has('wire:key')) wire:key="{{ $attributes->get('wire:key') }}" @endif>
    <input type="{{ $type }}" {{ $attributes->except('wire:key')->class('peer sr-only') }}>
    @if ($type === 'radio')
        <span class="size-6 shrink-0 rounded-full shadow-outline peer-checked:shadow-radio peer-focus-visible:ring-2 peer-focus-visible:ring-ink peer-focus-visible:ring-offset-2" aria-hidden="true"></span>
    @else
        <span class="flex size-6 shrink-0 items-center justify-center rounded-sm text-transparent shadow-outline peer-checked:bg-ink peer-checked:text-white peer-checked:shadow-none peer-focus-visible:ring-2 peer-focus-visible:ring-ink peer-focus-visible:ring-offset-2" aria-hidden="true"><x-ui.icon name="check" size="s" /></span>
    @endif
    @if ($title !== null)
        <span class="flex min-w-0 flex-1 flex-col gap-1">
            <span class="truncate text-t1-s font-medium">{{ $title }}</span>
            @if ($sub)<span class="text-t2 text-muted">{{ $sub }}</span>@endif
        </span>
    @else
        {{ $slot }}
    @endif
</label>
