{{-- Поле формы: подпись над полем, ошибка под ним. Для textarea — rows > 0. Атрибуты (wire:model, type, placeholder) передаются в поле. --}}
@props(['label', 'name', 'rows' => 0, 'hint' => null])
<label class="flex flex-col gap-2">
    <span class="text-t2 font-medium">{{ $label }}</span>
    @if ($rows > 0)
        <textarea name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->class(['field h-auto min-h-12 py-3', 'shadow-outline-ink' => $errors->has($name)]) }}></textarea>
    @else
        <input name="{{ $name }}" {{ $attributes->merge(['type' => 'text'])->class(['field', 'shadow-outline-ink' => $errors->has($name)]) }}>
    @endif
    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
    @if ($hint && ! $errors->has($name))<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</label>
