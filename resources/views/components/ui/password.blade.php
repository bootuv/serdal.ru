{{-- Поле пароля с кнопкой «Показать пароль». Как x-ui.field: подпись, ошибка, hint; size="l" — 52 (экраны входа).
     aside — слот справа от подписи («Забыли пароль?»). Атрибуты (wire:model, autocomplete) — на input. --}}
@props(['label', 'name', 'hint' => null, 'size' => 'm'])
<div class="flex min-w-0 flex-col gap-2" x-data="{ shown: false }">
    <span class="flex items-center justify-between gap-2 text-t2 font-medium"><label for="{{ $name }}">{{ $label }}</label>{{ $aside ?? '' }}</span>
    <span class="relative flex items-center">
        <input id="{{ $name }}" name="{{ $name }}" type="password" x-bind:type="shown ? 'text' : 'password'"
            {{ $attributes->class(['field pr-12', 'h-13' => $size === 'l', 'shadow-outline-ink' => $errors->has($name)]) }}>
        <button type="button" x-on:click="shown = ! shown" x-bind:aria-label="shown ? 'Скрыть пароль' : 'Показать пароль'" aria-label="Показать пароль"
            @class(['absolute right-1 flex items-center justify-center rounded-sm text-muted hover:bg-soft hover:text-ink', 'size-11' => $size === 'l', 'size-9' => $size !== 'l'])>
            <x-ui.icon name="eye" x-show="! shown" /><x-ui.icon name="eye-off" x-show="shown" x-cloak />
        </button>
    </span>
    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
    @if ($hint && ! $errors->has($name))<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</div>
