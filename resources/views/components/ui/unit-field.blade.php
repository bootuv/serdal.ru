{{-- Числовое поле формы с единицей справа внутри поля («₽», «дней», «мин», «занятий»): подпись, ошибка, подсказка.
     unit — единица (пусто — без неё); after — слот справа от поля (чип «Без лимита»). Атрибуты (wire:model, disabled, placeholder) — в поле.
     Недоступное поле — серое (BRAND.md §5). --}}
@props(['label', 'name', 'unit' => null, 'hint' => null])
<div class="flex min-w-0 flex-col gap-2">
    <label for="uf-{{ $name }}" class="text-t2 font-medium">{{ $label }}</label>
    <div class="flex items-center gap-2">
        <span class="relative flex min-w-0 flex-1">
            <input id="uf-{{ $name }}" name="{{ $name }}" type="text" inputmode="numeric" {{ $attributes->class(['field disabled:bg-soft disabled:text-muted disabled:shadow-none', 'pr-12' => $unit, 'shadow-outline-ink' => $errors->has($name)]) }}>
            @if ($unit)<span class="pointer-events-none absolute right-3 top-3 text-t1-s text-muted">{{ $unit }}</span>@endif
        </span>
        {{ $after ?? '' }}
    </div>
    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
    @if ($hint && ! $errors->has($name))<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</div>
