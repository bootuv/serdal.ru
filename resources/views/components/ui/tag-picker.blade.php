{{-- Выбор тегов из существующих с созданием нового (теги статьи блога): выбранные — метками с крестиком, поле «Добавить» в конце.
     Фокус в поле открывает список существующих тегов (до 8), ввод фильтрует его; если такого тега нет — строка «Создать тег «…»».
     ↓/↑ — по списку, Enter — выбрать подсвеченное (или создать), Esc — закрыть.
     model — свойство Livewire с выбранными названиями (items — они же для отрисовки); options — все существующие названия;
     add — метод Livewire, получает название; remove — получает номер выбранного;
     what — что убираем («тег» → «Убрать тег …»); max — сколько можно выбрать. --}}
@props(['label', 'id', 'model', 'items', 'options', 'add', 'remove', 'what', 'placeholder' => 'Добавить', 'hint' => null, 'max' => 8])
<div class="flex flex-col gap-2"
     x-data="{
        q: '', open: false, index: 0,
        options: @js(array_values($options)),
        get chosen() { return ($wire.get(@js($model)) || []).map((t) => t.toLowerCase()); },
        get full() { return this.chosen.length >= {{ (int) $max }}; },
        list() {
            const q = this.q.trim().toLowerCase();
            return this.options.filter((o) => ! this.chosen.includes(o.toLowerCase()) && (q === '' || o.toLowerCase().includes(q))).slice(0, 8);
        },
        canCreate() {
            const q = this.q.trim().toLowerCase();
            return q !== '' && ! this.options.some((o) => o.toLowerCase() === q) && ! this.chosen.includes(q);
        },
        rows() { return [...(this.canCreate() ? [{ create: true, name: this.q.trim() }] : []), ...this.list().map((name) => ({ create: false, name }))]; },
        move(step) { const n = this.rows().length; if (n) this.index = (this.index + step + n) % n; },
        pick(name) { if (! name || this.full) return; $wire.call(@js($add), name); this.q = ''; this.index = 0; },
        enter() { const row = this.rows()[this.index]; if (row) this.pick(row.name); },
     }"
     x-on:click.outside="open = false">
    <span id="{{ $id }}" class="text-t2 font-medium">{{ $label }}</span>
    <div class="relative">
        <div class="flex min-h-11 flex-wrap items-center gap-2 rounded p-2 shadow-outline focus-within:shadow-outline-ink" role="group" aria-labelledby="{{ $id }}">
            @foreach ($items as $i => $text)
                <span class="inline-flex h-8 items-center gap-1 rounded-sm bg-soft pl-2 pr-1 text-t2 font-medium" wire:key="{{ $id }}-{{ $i }}-{{ md5($text) }}">{{ $text }}
                    <button type="button" wire:click="{{ $remove }}({{ $i }})" class="flex size-5 items-center justify-center rounded-sm text-muted hover:text-ink" aria-label="Убрать {{ $what }} «{{ $text }}»"><x-ui.icon name="x" size="s" /></button>
                </span>
            @endforeach
            @if (count($items) < $max)
                <input type="text" x-model="q" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" autocomplete="off"
                       x-on:focus="open = true" x-on:input="open = true; index = 0"
                       x-on:keydown.arrow-down.prevent="open = true; move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
                       x-on:keydown.enter.prevent="enter()" x-on:keydown.escape="open = false"
                       class="h-8 min-w-0 flex-1 bg-transparent px-1 text-t1 text-ink outline-none placeholder:text-muted lg:text-t2">
            @endif
        </div>

        <div x-show="open && rows().length" x-cloak role="listbox" aria-label="{{ $label }}"
             class="absolute inset-x-0 top-full z-10 mt-1 flex max-h-sidebar flex-col overflow-y-auto rounded border border-line bg-white p-1 shadow-card">
            <template x-for="(row, i) in rows()" x-bind:key="(row.create ? '+' : '') + row.name">
                <button type="button" role="option" x-bind:aria-selected="i === index" x-on:mousedown.prevent="pick(row.name)" x-on:mouseenter="index = i"
                        x-bind:class="i === index ? 'bg-soft' : ''" class="flex h-9 w-full shrink-0 items-center gap-2 rounded-sm px-3 text-left text-t2">
                    <x-ui.icon name="plus" size="s" class="shrink-0 text-muted" x-show="row.create" />
                    <span class="truncate" x-bind:class="row.create ? 'font-medium text-ink' : 'text-ink'" x-text="row.create ? 'Создать тег «' + row.name + '»' : row.name"></span>
                </button>
            </template>
        </div>
    </div>
    @if ($hint)<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</div>
