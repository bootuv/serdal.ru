{{-- Выбор из длинного списка с поиском (вместо системного списка): поле 44 открывает список под собой — поиск сверху, строки с названием и подписью.
     options: [['value' => …, 'title' => …, 'sub' => …, 'photo' => …], …]; avatars — в строках аватар (люди: x-ui.person-select), photo — адрес фото.
     model — свойство Livewire со значением выбранного (selected — оно же, показывается в поле); clear — подпись строки «ничего не выбрано»
     («Не привязывать к занятию»), она же стоит в пустом поле; у выбранного значения в поле появляется крестик, который снимает выбор.
     action — метод Livewire, получает значение (выбор нескольких: список не закрывается — keep; checked — уже выбранные значения,
     они отмечены галочкой, повторное нажатие снимает отметку, если так делает метод). В поле при этом — подпись действия («Выбрать учеников»).
     Клавиши: ↓/↑ — по списку, Enter — выбрать, Esc — закрыть список (окно под ним остаётся открытым). --}}
@props(['options', 'name', 'label' => null, 'model' => null, 'action' => null, 'selected' => null, 'checked' => null, 'keep' => null, 'placeholder' => 'Выберите', 'search' => 'Поиск', 'clear' => null, 'avatars' => false])
@php
    $options = collect($options)->values();
    $current = $selected !== null && $selected !== '' ? $options->first(fn ($o) => (string) $o['value'] === (string) $selected) : null;
    $checked = $checked === null ? null : array_map('strval', $checked);
    $keep = $keep ?? ! $model;
    $norm = fn (string $s) => str_replace('ё', 'е', mb_strtolower($s));
    $pick = $model ? '$wire.set(' . \Illuminate\Support\Js::from($model) . ', value)' : '$wire.' . $action . '(value !== \'\' && ! isNaN(value) ? Number(value) : value)';
    $row = 'flex shrink-0 items-center gap-3 rounded-sm text-left outline-none hover:bg-soft-hover focus:bg-soft-hover ' . ($avatars ? 'p-2' : 'min-h-11 px-3 py-2');
@endphp
<div class="relative flex flex-col gap-2"
     x-data="{
        open: false, q: '',
        norm(s) { return s.toLowerCase().replaceAll('ё', 'е').trim() },
        items() { return [...this.$refs.list.querySelectorAll('[data-q]')] },
        shown() { return this.items().filter(el => el.dataset.q.includes(this.norm(this.q))) },
        toggle() { this.open = ! this.open; if (this.open) { this.q = ''; this.$nextTick(() => this.$refs.search.focus()) } },
        close() { this.open = false },
        move(el, step) { const list = this.shown(); const next = list[list.indexOf(el) + step]; (next ?? (step < 0 ? this.$refs.search : el)).focus() },
        pick(value) { {{ $pick }}; {{ $keep ? 'this.$refs.search.focus()' : 'this.close(); this.$refs.trigger.focus()' }} },
     }"
     x-on:click.outside="close()" x-on:keydown.escape="if (open) { $event.stopPropagation(); close(); $refs.trigger.focus() }">
    @if ($label)<span id="ss-{{ $name }}-label" class="text-t2 font-medium">{{ $label }}</span>@endif

    {{-- Поле: текст — кнопка, открывающая список; крестик снимает выбор (только с clear); стрелка — тоже открывает --}}
    <div @class(['field flex items-center gap-1 p-0 pr-3 focus-within:shadow-outline-ink', 'shadow-outline-ink' => $errors->has($name)])>
        <button type="button" x-ref="trigger" x-on:click="toggle()" x-bind:aria-expanded="open" aria-haspopup="listbox"
                {!! $label ? 'aria-labelledby="ss-' . e($name) . '-label"' : 'aria-label="' . e($clear ?? $placeholder) . '"' !!}
                class="flex h-full min-w-0 flex-1 items-center rounded pl-4 text-left outline-none">
            @if ($current)
                <span class="min-w-0 flex-1 truncate">{{ $current['title'] }}@if (! empty($current['sub']))<span class="text-muted"> · {{ $current['sub'] }}</span>@endif</span>
            @elseif ($clear)
                <span class="min-w-0 flex-1 truncate">{{ $clear }}</span>
            @else
                <span @class(['min-w-0 flex-1 truncate', 'text-faint' => ! $action])>{{ $placeholder }}</span>
            @endif
        </button>
        @if ($current && $clear && $model)
            <button type="button" x-on:click="pick('')" aria-label="{{ $clear }}" title="{{ $clear }}"
                    class="flex size-8 shrink-0 items-center justify-center rounded-sm text-muted hover:bg-soft-hover hover:text-ink"><x-ui.icon name="x" size="s" /></button>
        @endif
        <span x-on:click="toggle()" class="flex shrink-0 cursor-pointer text-muted" aria-hidden="true"><x-ui.icon name="chevron-down" /></span>
    </div>

    <div x-show="open" x-cloak class="absolute inset-x-0 top-full z-10 mt-1 flex flex-col gap-1 rounded-lg border border-line bg-white p-1 shadow-card">
        <label class="relative flex">
            <span class="sr-only">{{ $search }}</span>
            <x-ui.icon name="search" size="s" class="pointer-events-none absolute left-3 top-3 text-muted" />
            <input type="search" x-ref="search" x-model="q" placeholder="{{ $search }}" autocomplete="off"
                   x-on:keydown.down.prevent="shown()[0]?.focus()"
                   x-on:keydown.enter.prevent="shown()[0]?.click()"
                   class="h-10 w-full rounded-sm bg-soft pl-8 pr-3 text-t1 text-ink outline-none placeholder:text-faint lg:text-t2">
        </label>
        <div x-ref="list" role="listbox" class="flex max-h-sidebar flex-col overflow-y-auto">
            @if ($clear && $model)
                <button type="button" role="option" aria-selected="{{ $current ? 'false' : 'true' }}" wire:key="ss-{{ $name }}-none"
                        data-q="{{ $norm($clear) }}" x-show="$el.dataset.q.includes(norm(q))" x-on:click="pick('')"
                        x-on:keydown.down.prevent="move($el, 1)" x-on:keydown.up.prevent="move($el, -1)"
                        @class([$row, 'bg-mint' => ! $current])>
                    <span class="min-w-0 flex-1 truncate text-t1-s font-medium text-muted">{{ $clear }}</span>
                    @if (! $current)<x-ui.icon name="check" class="shrink-0 text-ink" />@endif
                </button>
            @endif
            @foreach ($options as $o)
                @php $on = $checked !== null ? in_array((string) $o['value'], $checked, true) : ($current && (string) $current['value'] === (string) $o['value']); @endphp
                <button type="button" role="option" aria-selected="{{ $on ? 'true' : 'false' }}" wire:key="ss-{{ $name }}-{{ $o['value'] }}"
                        data-q="{{ $norm($o['title'] . ' ' . ($o['sub'] ?? '')) }}" data-value="{{ $o['value'] }}"
                        x-show="$el.dataset.q.includes(norm(q))"
                        x-on:click="pick($el.dataset.value)"
                        x-on:keydown.down.prevent="move($el, 1)" x-on:keydown.up.prevent="move($el, -1)"
                        @class([$row, 'bg-mint' => $on])>
                    @if ($avatars)<x-ui.avatar :name="$o['title']" :id="(int) $o['value']" :photo="$o['photo'] ?? null" />@endif
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="truncate text-t1-s font-medium text-ink">{{ $o['title'] }}</span>
                        @if (! empty($o['sub']))<span class="truncate text-t3 text-muted">{{ $o['sub'] }}</span>@endif
                    </span>
                    @if ($on)<x-ui.icon name="check" class="shrink-0 text-ink" />@endif
                </button>
            @endforeach
            <p x-show="! shown().length" x-cloak class="px-3 py-4 text-t2 text-muted">{{ $options->isEmpty() ? 'Список пуст.' : 'Ничего не нашли — попробуйте другое слово.' }}</p>
        </div>
    </div>

    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
</div>
