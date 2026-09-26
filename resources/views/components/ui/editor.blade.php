{{-- Редактор форматированного текста: жирный, курсив, списки, ссылка. Привязка — wire:model (HTML).
     На сервере значение чистится App\Support\RichText::clean(). Показ — в блоке с классом .rich. --}}
@props(['label', 'name', 'placeholder' => '', 'hint' => null])
@php $model = $attributes->wire('model')->value(); @endphp
<div class="flex flex-col gap-2">
    <span id="{{ $name }}-label" class="text-t2 font-medium">{{ $label }}</span>
    <div wire:ignore x-data="richEditor($wire.entangle('{{ $model }}'), @js($placeholder))" x-on:livewire:navigating.window="destroy()"
         @class(['overflow-hidden rounded bg-white focus-within:shadow-outline-ink', 'shadow-outline-ink' => $errors->has($name), 'shadow-outline' => ! $errors->has($name)])>
        <div class="flex gap-1 border-b border-line p-1" role="toolbar" aria-label="Оформление текста">
            @foreach ([['bold', 'bold', 'Жирный'], ['italic', 'italic', 'Курсив'], ['bulletList', 'list', 'Список'], ['orderedList', 'list-ordered', 'Нумерованный список'], ['link', 'link', 'Ссылка']] as [$mark, $icon, $title])
                @php $action = ['bold' => 'bold', 'italic' => 'italic', 'bulletList' => 'bullets', 'orderedList' => 'numbers', 'link' => 'link'][$mark]; @endphp
                <button type="button" x-on:click="{{ $action }}()" title="{{ $title }}" aria-label="{{ $title }}"
                        x-bind:class="active('{{ $mark }}') ? 'bg-soft text-ink' : 'text-muted'"
                        class="flex size-9 items-center justify-center rounded-sm hover:bg-soft hover:text-ink"><x-ui.icon :name="$icon" /></button>
            @endforeach
        </div>
        <div x-ref="editor" aria-labelledby="{{ $name }}-label"></div>
    </div>
    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
    @if ($hint && ! $errors->has($name))<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</div>
