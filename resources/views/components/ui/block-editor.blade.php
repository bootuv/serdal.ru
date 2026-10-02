{{-- Блочный редактор в духе Notion (статьи блога): «/» в пустой строке — меню блоков (заголовок, подзаголовок, списки, цитата,
     выделенный блок, разделитель, картинка), выделение текста — тёмная панель «жирный, курсив, ссылка, заголовки»,
     Markdown-сокращения, картинки вставкой и перетаскиванием. Привязка — wire:model (HTML), на сервере — RichText::clean().
     Картинка грузится во временное свойство (upload-model, WithFileUploads), метод upload-method кладёт её на CDN и возвращает адрес.
     video-model / video-method (новости) — еще и пункт «Видео»: метод возвращает адреса ролика и обложки, ролик сжимается в очереди
     (MediaService), редактор пишет «Видео обрабатывается…» и спрашивает готовность методом компонента mediaStatus. --}}
@props(['label', 'name', 'uploadModel', 'uploadMethod', 'videoModel' => null, 'videoMethod' => null, 'placeholder' => null, 'hint' => null])
@php $model = $attributes->wire('model')->value(); @endphp
<div class="flex flex-col gap-2">
    <span id="{{ $name }}-label" class="sr-only">{{ $label }}</span>
    <div wire:ignore class="relative"
         x-data="blockEditor($wire.entangle('{{ $model }}'), { placeholder: @js($placeholder), uploadModel: @js($uploadModel), uploadMethod: @js($uploadMethod), videoModel: @js($videoModel), videoMethod: @js($videoMethod) })"
         x-on:livewire:navigating.window="destroy()">
        <div x-ref="editor" aria-labelledby="{{ $name }}-label"></div>

        {{-- Меню «/»: пункты с иконками рисуем здесь, Alpine фильтрует по запросу и подсвечивает выбранный --}}
        <div x-show="menu.open && items().length" x-cloak x-bind:style="`left: ${menu.x}px; top: ${menu.y}px`"
             class="absolute z-10 flex max-h-tour w-sidebar flex-col overflow-y-auto rounded border border-line bg-white p-1 shadow-card" role="listbox" aria-label="Блоки">
            @foreach ([
                ['text', 'text', 'Текст', ''], ['h2', 'heading-2', 'Заголовок', '##'], ['h3', 'heading-3', 'Подзаголовок', '###'],
                ['ul', 'list', 'Список', '-'], ['ol', 'list-ordered', 'Нумерованный список', '1.'], ['quote', 'quote', 'Цитата', '>'],
                ['callout', 'callout', 'Выделенный блок', ''], ['hr', 'divider', 'Разделитель', '---'], ['image', 'image', 'Картинка', ''], ['video', 'video', 'Видео', ''],
            ] as [$id, $icon, $title, $hint])
                <button type="button" role="option" x-show="shows('{{ $id }}')" x-bind:aria-selected="current('{{ $id }}')"
                        x-on:mousedown.prevent="pick('{{ $id }}')" x-on:mouseenter="hover('{{ $id }}')" x-bind:class="current('{{ $id }}') ? 'bg-soft text-ink' : 'text-muted'"
                        class="flex h-10 w-full shrink-0 items-center gap-3 rounded-sm px-2 text-left">
                    <x-ui.icon :name="$icon" class="shrink-0" />
                    <span class="min-w-0 flex-1 truncate text-t2 font-medium text-ink">{{ $title }}</span>
                    @if ($hint)<span class="shrink-0 text-t3 text-muted">{{ $hint }}</span>@endif
                </button>
            @endforeach
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
        @if ($videoMethod)
            <input type="file" x-ref="video" accept="video/*" class="sr-only" tabindex="-1" aria-label="Видео в текст"
                   x-on:change="uploadVideo($event.target.files[0]); $event.target.value = ''">
        @endif
        <span x-show="uploading" x-cloak class="text-t3 text-muted" x-text="uploadingText"></span>
        <span x-show="! uploading && processing" x-cloak class="text-t3 text-muted">Видео обрабатывается — обычно минута-две</span>
    </div>
    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
    @if ($hint && ! $errors->has($name))<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</div>
