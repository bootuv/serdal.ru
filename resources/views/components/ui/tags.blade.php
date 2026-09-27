{{-- Выбор нескольких значений тегами (предметы, направления): теги с крестиком + список «Добавить» в конце поля.
     selected — выбранные id; options — [id => название]; model — свойство Livewire для добавления; remove — метод удаления (получает id);
     what — чего (в родительном падеже для подсказок: «предмет» → «Убрать предмет …»). --}}
@props(['label', 'id', 'selected', 'options', 'model', 'remove', 'what', 'add' => 'Добавить'])
<div class="flex flex-col gap-2">
    <span id="{{ $id }}" class="text-t2 font-medium">{{ $label }}</span>
    <div class="flex min-h-11 flex-wrap items-center gap-2 rounded p-2 shadow-outline" role="group" aria-labelledby="{{ $id }}">
        @foreach ($selected as $value)
            @if (isset($options[$value]))
                <span class="inline-flex h-8 items-center gap-1 rounded-sm bg-soft pl-2 pr-1 text-t2 font-medium" wire:key="{{ $id }}-{{ $value }}">{{ $options[$value] }}
                    <button type="button" wire:click="{{ $remove }}({{ $value }})" class="flex size-5 items-center justify-center rounded-sm text-muted hover:text-ink" aria-label="Убрать {{ $what }} «{{ $options[$value] }}»"><x-ui.icon name="x" size="s" /></button>
                </span>
            @endif
        @endforeach
        @if (count($selected) < count($options))
            <select wire:model.live="{{ $model }}" class="h-8 min-w-0 flex-1 cursor-pointer appearance-none bg-transparent px-1 text-t1 text-muted outline-none hover:text-ink lg:text-t2" aria-label="Добавить {{ $what }}">
                <option value="">{{ $add }}</option>
                @foreach ($options as $value => $name)
                    @unless (in_array($value, $selected, true))<option value="{{ $value }}">{{ $name }}</option>@endunless
                @endforeach
            </select>
        @endif
    </div>
</div>
