{{-- Список строк тегами с крестиком + поле «Добавить…» в конце (пункты тарифа «Что входит»). Enter или уход с поля — добавить.
     items — строки; model — свойство Livewire для нового пункта; add — метод добавления; remove — метод удаления (получает номер);
     what — что убираем («пункт» → «Убрать пункт …»); hint — строка под полем. --}}
@props(['label', 'id', 'items', 'model', 'add', 'remove', 'what', 'placeholder' => 'Добавить', 'hint' => null])
<div class="flex flex-col gap-2">
    <span id="{{ $id }}" class="text-t2 font-medium">{{ $label }}</span>
    <div class="flex min-h-11 flex-wrap items-center gap-2 rounded p-2 shadow-outline" role="group" aria-labelledby="{{ $id }}">
        @foreach ($items as $i => $text)
            <span class="inline-flex h-8 items-center gap-1 rounded-sm bg-soft pl-2 pr-1 text-t2 font-medium" wire:key="{{ $id }}-{{ $i }}-{{ md5($text) }}">{{ $text }}
                <button type="button" wire:click="{{ $remove }}({{ $i }})" class="flex size-5 items-center justify-center rounded-sm text-muted hover:text-ink" aria-label="Убрать {{ $what }} «{{ $text }}»"><x-ui.icon name="x" size="s" /></button>
            </span>
        @endforeach
        <input type="text" wire:model="{{ $model }}" wire:keydown.enter.prevent="{{ $add }}" wire:blur="{{ $add }}" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}"
               class="h-8 min-w-0 flex-1 bg-transparent px-1 text-t1 text-ink outline-none placeholder:text-muted lg:text-t2">
    </div>
    @if ($hint)<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</div>
