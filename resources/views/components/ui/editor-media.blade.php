{{-- Редактор текста с картинками (статьи базы знаний): как x-ui.editor + кнопка «Картинка». Привязка — wire:model (HTML).
     Картинка загружается во временное свойство компонента (upload-model, WithFileUploads), затем метод upload-method
     кладёт файл на CDN и возвращает адрес — картинка вставляется в текст. На сервере HTML чистится RichText::clean() (img сохраняется). --}}
@props(['label', 'name', 'uploadModel', 'uploadMethod', 'placeholder' => '', 'hint' => null])
@php $model = $attributes->wire('model')->value(); @endphp
<div class="flex flex-col gap-2">
    <span id="{{ $name }}-label" class="text-t2 font-medium">{{ $label }}</span>
    <div wire:ignore x-data="richEditor($wire.entangle('{{ $model }}'), @js($placeholder), { images: true })" x-on:livewire:navigating.window="destroy()"
         @class(['overflow-hidden rounded bg-white focus-within:shadow-outline-ink', 'shadow-outline-ink' => $errors->has($name), 'shadow-outline' => ! $errors->has($name)])>
        <div class="flex items-center gap-1 border-b border-line p-1" role="toolbar" aria-label="Оформление текста">
            @foreach ([['bold', 'bold', 'Жирный', 'bold'], ['italic', 'italic', 'Курсив', 'italic'], ['bulletList', 'list', 'Список', 'bullets'], ['orderedList', 'list-ordered', 'Нумерованный список', 'numbers'], ['link', 'link', 'Ссылка', 'link']] as [$mark, $icon, $title, $action])
                <button type="button" x-on:click="{{ $action }}()" title="{{ $title }}" aria-label="{{ $title }}"
                        x-bind:class="active('{{ $mark }}') ? 'bg-soft text-ink' : 'text-muted'"
                        class="flex size-9 items-center justify-center rounded-sm hover:bg-soft hover:text-ink"><x-ui.icon :name="$icon" /></button>
            @endforeach
            <button type="button" x-on:click="$refs.image.click()" x-bind:disabled="uploading" title="Картинка" aria-label="Картинка"
                    class="flex size-9 items-center justify-center rounded-sm text-muted hover:bg-soft hover:text-ink disabled:opacity-50"><x-ui.icon name="image" /></button>
            <input type="file" x-ref="image" accept="image/png,image/jpeg,image/gif,image/webp" class="sr-only" tabindex="-1" aria-label="Картинка в текст"
                   x-on:change="uploadImage($event.target.files[0], @js($uploadModel), @js($uploadMethod)); $event.target.value = ''">
            <span x-show="uploading" x-cloak class="px-2 text-t3 text-muted">Загружаем картинку…</span>
        </div>
        <div x-ref="editor" aria-labelledby="{{ $name }}-label"></div>
    </div>
    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
    @if ($hint && ! $errors->has($name))<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</div>
