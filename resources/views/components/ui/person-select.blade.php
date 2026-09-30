{{-- Выбор человека из длинного списка: поле открывает список с поиском по имени и почте (вместо системного списка).
     people: [['id' => …, 'name' => …, 'email' => …], …]. Два режима:
     model — свойство Livewire с id выбранного (один человек; selected — его id, показывается в поле);
     action — метод Livewire, получает id (добавление в группу: список не закрывается, можно добавить нескольких).
     Клавиши: ↓/↑ — по списку, Enter — выбрать, Esc — закрыть список (окно под ним остаётся открытым). --}}
@props(['people', 'name', 'label' => null, 'model' => null, 'action' => null, 'selected' => null, 'placeholder' => 'Выберите человека', 'search' => 'Имя или почта'])
@php
    $people = collect($people)->values();
    $current = $selected !== null && $selected !== '' ? $people->firstWhere('id', (int) $selected) : null;
    $norm = fn (string $s) => str_replace('ё', 'е', mb_strtolower($s));
    $pick = $model ? '$wire.set(' . \Illuminate\Support\Js::from($model) . ', String(id))' : '$wire.' . $action . '(id)';
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
        pick(id) { {{ $pick }}; {{ $model ? 'this.close(); this.$refs.trigger.focus()' : 'this.q = \'\'; this.$refs.search.focus()' }} },
     }"
     x-on:click.outside="close()" x-on:keydown.escape="if (open) { $event.stopPropagation(); close(); $refs.trigger.focus() }">
    @if ($label)<span id="ps-{{ $name }}-label" class="text-t2 font-medium">{{ $label }}</span>@endif

    <button type="button" x-ref="trigger" x-on:click="toggle()" x-bind:aria-expanded="open" aria-haspopup="listbox"
            {!! $label ? 'aria-labelledby="ps-' . e($name) . '-label"' : 'aria-label="' . e($placeholder) . '"' !!}
            @class(['field flex items-center gap-2 pr-3 text-left', 'shadow-outline-ink' => $errors->has($name)])>
        @if ($current)
            <span class="min-w-0 flex-1 truncate">{{ $current['name'] }}@if (! empty($current['email']))<span class="text-muted"> · {{ $current['email'] }}</span>@endif</span>
        @else
            <span class="min-w-0 flex-1 truncate text-faint">{{ $placeholder }}</span>
        @endif
        <x-ui.icon name="chevron-down" class="shrink-0 text-muted" />
    </button>

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
            @foreach ($people as $person)
                @php $on = $current && $current['id'] === $person['id']; @endphp
                <button type="button" role="option" aria-selected="{{ $on ? 'true' : 'false' }}" wire:key="ps-{{ $name }}-{{ $person['id'] }}"
                        data-q="{{ $norm($person['name'] . ' ' . ($person['email'] ?? '')) }}"
                        x-show="$el.dataset.q.includes(norm(q))"
                        x-on:click="pick({{ (int) $person['id'] }})"
                        x-on:keydown.down.prevent="move($el, 1)" x-on:keydown.up.prevent="move($el, -1)"
                        @class(['flex shrink-0 items-center gap-3 rounded-sm p-2 text-left outline-none hover:bg-soft-hover focus:bg-soft-hover', 'bg-mint' => $on])>
                    <x-ui.avatar :name="$person['name']" :id="$person['id']" />
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="truncate text-t1-s font-medium text-ink">{{ $person['name'] }}</span>
                        @if (! empty($person['email']))<span class="truncate text-t3 text-muted">{{ $person['email'] }}</span>@endif
                    </span>
                    @if ($on)<x-ui.icon name="check" class="shrink-0 text-ink" />@endif
                </button>
            @endforeach
            <p x-show="! shown().length" x-cloak class="px-3 py-4 text-t2 text-muted">{{ $people->isEmpty() ? 'Список пуст.' : 'Никого не нашли — проверьте имя или почту.' }}</p>
        </div>
    </div>

    @error($name)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
</div>
