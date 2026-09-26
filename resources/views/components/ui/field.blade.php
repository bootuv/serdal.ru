{{-- Поле формы: подпись над полем, ошибка под ним. Для textarea — rows > 0. Атрибуты (wire:model, type, placeholder) передаются в поле.
     size="l" — поле 52 (экраны входа и регистрации); optional — пометка «необязательно» у подписи; aside — слот справа от подписи («Забыли пароль?»). --}}
@props(['label', 'name', 'rows' => 0, 'hint' => null, 'size' => 'm', 'optional' => false])
<label class="flex min-w-0 flex-col gap-2">
    <span class="flex items-center justify-between gap-2 text-t2 font-medium"><span>{{ $label }}@if ($optional) <span class="font-normal text-muted">необязательно</span>@endif</span>{{ $aside ?? '' }}</span>
    @if ($rows > 0)
        <textarea name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->class(['field h-auto min-h-12 py-3', 'shadow-outline-ink' => $errors->has($name)]) }}></textarea>
    @else
        <input name="{{ $name }}" {{ $attributes->merge(['type' => 'text'])->class(['field', 'h-13' => $size === 'l', 'shadow-outline-ink' => $errors->has($name)]) }}>
    @endif
    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
    @if ($hint && ! $errors->has($name))<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</label>
