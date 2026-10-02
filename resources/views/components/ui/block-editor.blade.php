{{-- Блочный редактор в духе Notion (статьи блога): «/» в пустой строке — меню блоков (заголовок, подзаголовок, списки, цитата,
     выделенный блок, разделитель, картинка), выделение текста — тёмная панель «жирный, курсив, ссылка, заголовки»,
     Markdown-сокращения, картинки вставкой и перетаскиванием. Привязка — wire:model (HTML), на сервере — RichText::clean().
     Картинка грузится во временное свойство (upload-model, WithFileUploads), метод upload-method кладёт её на CDN и возвращает адрес. --}}
@props(['label', 'name', 'uploadModel', 'uploadMethod', 'placeholder' => null, 'hint' => null])
@php $model = $attributes->wire('model')->value(); @endphp
<div class="flex flex-col gap-2">
    <span id="{{ $name }}-label" class="sr-only">{{ $label }}</span>
    <div wire:ignore class="relative"
         x-data="blockEditor($wire.entangle('{{ $model }}'), { placeholder: @js($placeholder), uploadModel: @js($uploadModel), uploadMethod: @js($uploadMethod) })"
         x-on:livewire:navigating.window="destroy()">
        <div x-ref="editor" aria-labelledby="{{ $name }}-label"></div>

        {{-- Меню «/» --}}
        <div x-show="menu.open && items().length" x-cloak x-bind:style="`left: ${menu.x}px; top: ${menu.y}px`"
             class="absolute z-10 flex max-h-sidebar w-sidebar flex-col overflow-y-auto rounded border border-line bg-white p-1 shadow-card" role="listbox" aria-label="Блоки">
            <template x-for="(item, i) in items()" x-bind:key="item.id">
                <button type="button" role="option" x-bind:aria-selected="i === menu.index" x-on:mousedown.prevent="pick(item.id)" x-on:mouseenter="menu.index = i"
                        x-bind:class="i === menu.index ? 'bg-soft' : ''"
                        class="flex h-11 w-full shrink-0 items-center justify-between gap-3 rounded-sm px-3 text-left">
                    <span class="truncate text-t2 font-medium text-ink" x-text="item.title"></span>
                    <span class="shrink-0 text-t3 text-muted" x-text="item.hint"></span>
                </button>
            </template>
        </div>

        {{-- Панель выделенного текста --}}
        <div x-show="bubble.open" x-cloak x-bind:style="`left: ${bubble.x}px; top: ${bubble.y}px`"
             class="absolute z-10 flex items-center gap-1 rounded bg-ink p-1 shadow-card" role="toolbar" aria-label="Оформление текста">
            @foreach ([['bold()', "active('bold')", 'Жирный', 'bold'], ['italic()', "active('italic')", 'Курсив', 'italic'], ['link()', "active('link')", 'Ссылка', 'link']] as [$call, $isActive, $title, $icon])
                <button type="button" x-on:mousedown.prevent="{{ $call }}" title="{{ $title }}" aria-label="{{ $title }}"
                        x-bind:class="{{ $isActive }} ? 'text-white bg-ink-hover' : 'text-faint'"
                        class="flex size-9 items-center justify-center rounded-sm hover:bg-ink-hover hover:text-white"><x-ui.icon :name="$icon" /></button>
            @endforeach
            @foreach ([2 => 'Заголовок', 3 => 'Подзаголовок'] as $level => $title)
                <button type="button" x-on:mousedown.prevent="heading({{ $level }})" title="{{ $title }}"
                        x-bind:class="active('heading', { level: {{ $level }} }) ? 'text-white bg-ink-hover' : 'text-faint'"
                        class="flex h-9 items-center justify-center rounded-sm px-2 text-t2 font-semibold hover:bg-ink-hover hover:text-white">{{ $title }}</button>
            @endforeach
        </div>

        <input type="file" x-ref="image" accept="image/png,image/jpeg,image/gif,image/webp" class="sr-only" tabindex="-1" aria-label="Картинка в текст"
               x-on:change="uploadImage($event.target.files[0]); $event.target.value = ''">
        <span x-show="uploading" x-cloak class="text-t3 text-muted">Загружаем картинку…</span>
    </div>
    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
    @if ($hint && ! $errors->has($name))<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</div>
