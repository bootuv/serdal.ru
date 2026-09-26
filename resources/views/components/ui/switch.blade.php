{{-- Переключатель 44×24: вкл — ink, выкл — line-strong. checked — состояние; атрибуты (wire:click) — на кнопку. --}}
@props(['checked' => false, 'label'])
<button type="button" role="switch" aria-checked="{{ $checked ? 'true' : 'false' }}" aria-label="{{ $label }}"
        {{ $attributes->class(['relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors', 'bg-ink' => $checked, 'bg-line-strong' => ! $checked]) }}>
    <span @class(['absolute size-5 rounded-full bg-white shadow-seg transition-all', 'left-6' => $checked, 'left-1' => ! $checked])></span>
</button>
