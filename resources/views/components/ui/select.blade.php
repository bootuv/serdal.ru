{{-- Выпадающий список формы: подпись над полем, ошибка под ним. options: ['value' => 'Подпись']. Атрибуты (wire:model) — в select. --}}
@props(['label' => null, 'name', 'options', 'placeholder' => null])
<label class="flex flex-col gap-2">
    @if ($label)<span class="text-t2 font-medium">{{ $label }}</span>@endif
    <span class="relative flex">
        <select name="{{ $name }}" {{ $attributes->class(['field appearance-none pr-12', 'shadow-outline-ink' => $errors->has($name)]) }}>
            @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
            @foreach ($options as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
        </select>
        <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-4 top-3 text-muted" />
    </span>
    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
</label>
