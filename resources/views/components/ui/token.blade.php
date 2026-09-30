{{-- Выбранное значение меткой с крестиком (ученики задания и занятия): высота 36, чёрная с белым текстом —
     как выбранный x-ui.chip, чтобы выбранных было видно сразу. remove — выражение Livewire для крестика; label — кого убираем (для подсказки). --}}
@props(['remove', 'label'])
<span {{ $attributes->class('inline-flex h-9 max-w-full items-center gap-1 rounded-full bg-ink pl-4 pr-1 text-t2 font-semibold text-white') }}>
    <span class="truncate">{{ $slot }}</span>
    <button type="button" wire:click="{{ $remove }}" class="flex size-8 shrink-0 items-center justify-center rounded-full text-white opacity-70 hover:opacity-100" aria-label="Убрать: {{ $label }}"><x-ui.icon name="x" size="s" /></button>
</span>
