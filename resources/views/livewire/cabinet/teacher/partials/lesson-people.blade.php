{{-- Экран занятия: ученики, цена и неоплаченное (правая колонка). Данные — Lesson::people(). --}}
@php $person = ! $group ? $people->first() : null; @endphp
<x-ui.card aria-labelledby="l-st">
    <x-ui.card-head id="l-st" :title="$group ? 'Ученики' : 'Ученик'">
        <x-slot:action>
            @if ($person)
                <a href="{{ $person['url'] }}" class="link text-t2">Карточка</a>
            @elseif ($group)
                <a href="{{ $chatUrl }}" class="link text-t2">Чат группы</a>
            @endif
        </x-slot:action>
    </x-ui.card-head>
    @if ($people->isEmpty())
        <p class="text-t2 text-muted">Пока пусто — добавьте учеников в <a href="{{ $editUrl }}" class="link">настройках занятия</a>.</p>
    @elseif ($person)
        <x-ui.list>
            <x-ui.row>
                <x-ui.avatar :name="$person['name']" :id="$person['id']" />
                <x-ui.text :title="$person['name']" :sub="$person['since']" />
                <x-ui.btn size="s" :href="$chatUrl">Написать</x-ui.btn>
            </x-ui.row>
            <div class="flex flex-col gap-3 border-t border-line py-4 last:pb-0">
                <div class="flex items-start gap-4">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="text-t1 font-semibold">{{ $person['price'] ? \App\Support\Money::format($person['price']) : 'Цена не указана' }}</span>
                        <span class="text-t2 text-muted">{{ $monthly ? 'Оплата в месяц' : 'Оплата за занятие' }}@if ($person['unpaid']) · не оплачено {{ plural_ru($person['unpaid'], 'начисление', 'начисления', 'начислений') }}@endif</span>
                    </div>
                    @if ($person['justPaid'] && ! $person['unpaid'])<x-ui.badge tone="ok">Оплачено</x-ui.badge>
                    @elseif ($person['overdue'])<x-ui.badge tone="danger">Просрочено</x-ui.badge>@endif
                </div>
                @if ($person['unpaid'])
                    <x-ui.btn size="s" class="self-start" wire:click="markPaid({{ $person['id'] }})">Отметить оплату</x-ui.btn>
                @elseif ($person['justPaid'])
                    <x-ui.btn size="s" class="self-start" wire:click="undoPaid({{ $person['id'] }})">Отменить</x-ui.btn>
                @endif
            </div>
        </x-ui.list>
    @else
        <x-ui.list>
            @foreach ($people as $p)
                <x-ui.row :href="$p['url']" wire:key="pp-{{ $p['id'] }}">
                    <x-ui.avatar :name="$p['name']" :id="$p['id']" />
                    <x-ui.text :title="$p['name']" :sub="$p['since']" />
                    @if ($p['overdue'])<x-ui.badge tone="danger">Не оплачено</x-ui.badge>@endif
                </x-ui.row>
            @endforeach
        </x-ui.list>
        @php $price = $people->pluck('price')->filter()->unique(); @endphp
        @if ($price->count() === 1)
            <p class="text-t2 text-muted">{{ \App\Support\Money::format($price->first()) }} {{ $priceUnit }} с каждого ученика</p>
        @endif
    @endif
</x-ui.card>
