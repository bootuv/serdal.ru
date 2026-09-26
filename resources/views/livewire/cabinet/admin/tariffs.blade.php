{{-- Тарифы по порядку на сайте (макет AdminTariffs). Перетаскивание за ручку — порядок сохраняется сразу, в тосте «Отменить». --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Тарифы" :sub="$factLine">
        <x-slot:actions><x-ui.btn variant="dark" icon="plus" :href="$newUrl">Добавить тариф</x-ui.btn></x-slot:actions>
    </x-ui.page-head>

    <x-ui.card aria-label="Тарифы по порядку на сайте">
        <div>
            <div class="hidden items-center gap-4 pb-3 text-t3 font-medium text-muted lg:flex">
                <span class="w-5"></span>
                <span class="flex-1">Тариф</span>
                <span class="w-44">Цена</span>
                <span class="w-40">Активных подписок</span>
                <span class="size-5"></span>
            </div>
            <div role="list" class="flex flex-col" x-data="{ drag: null }">
                @foreach ($rows as $t)
                    @php $lifted = $moving === $t['id']; @endphp
                    <div role="listitem" wire:key="tariff-{{ $t['id'] }}"
                         draggable="true"
                         x-data="{ side: null }"
                         x-on:dragstart="drag = {{ $t['id'] }}"
                         x-on:dragend="drag = null"
                         x-on:dragover="if (! drag || drag === {{ $t['id'] }}) { side = null; return } $event.preventDefault(); const r = $el.getBoundingClientRect(); side = ($event.clientY - r.top) / r.height < 0.5 ? 'before' : 'after'"
                         x-on:dragleave="side = null"
                         x-on:drop="if (drag && side) { $event.preventDefault(); $wire.move(drag, {{ $t['id'] }}, side === 'before') } side = null; drag = null"
                         @class(['relative flex items-center gap-4 py-4 last:pb-0',
                                 '-mx-4 rounded-lg bg-white px-4 shadow-card' => $lifted,
                                 'border-t border-line' => ! $lifted])>
                        <span x-show="side === 'before'" x-cloak class="pointer-events-none absolute inset-x-0 top-0 border-t-2 border-ink"></span>
                        <span x-show="side === 'after'" x-cloak class="pointer-events-none absolute inset-x-0 bottom-0 border-b-2 border-ink"></span>
                        <button type="button" wire:click="pick({{ $t['id'] }})" aria-pressed="{{ $lifted ? 'true' : 'false' }}" aria-label="Переставить тариф «{{ $t['name'] }}»"
                                @class(['flex h-9 w-5 shrink-0 cursor-grab items-center justify-center rounded-sm hover:text-ink', 'text-ink' => $lifted, 'text-faint' => ! $lifted])><x-ui.icon name="grip" size="s" /></button>
                        <a href="{{ $t['url'] }}" draggable="false" class="group flex min-w-0 flex-1 items-center gap-4 text-ink">
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="flex min-w-0 flex-wrap items-center gap-2">
                                    <span class="text-t1 font-medium group-hover:underline">{{ $t['name'] }}</span>
                                    @if ($t['popular'])<x-ui.badge>Популярный</x-ui.badge>@endif
                                    @if ($t['hidden'])<x-ui.badge>Скрыт</x-ui.badge>@endif
                                </span>
                                <span class="text-t2 text-muted">{{ $t['limits'] }}</span>
                                <span class="text-t2 text-muted lg:hidden">{{ $t['price'] }} · {{ plural_ru($t['subs'], 'активная подписка', 'активные подписки', 'активных подписок') }}</span>
                            </span>
                            <span class="hidden w-44 shrink-0 flex-col gap-1 lg:flex">
                                <span class="text-t1-s font-medium">{{ $t['price'] }}</span>
                                <span class="text-t2 text-muted">{{ $t['priceSub'] }}</span>
                            </span>
                            <span class="hidden w-40 shrink-0 text-t1-s font-medium lg:block">{{ $t['subs'] }}</span>
                            <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                        </a>
                        {{-- Режим перестановки нажатием: строка целиком — «поставить сюда» --}}
                        @if ($moving && ! $lifted)
                            <button type="button" wire:click="dropBefore({{ $t['id'] }})" class="absolute inset-0 hover:border-t-2 hover:border-ink" aria-label="Поставить «{{ $movingName }}» перед «{{ $t['name'] }}»"></button>
                        @endif
                    </div>
                @endforeach
                @if ($moving)
                    <button type="button" wire:click="dropBefore" class="h-4 w-full hover:border-t-2 hover:border-ink" aria-label="Поставить в конец списка"></button>
                @endif
            </div>
        </div>
        @if ($moving)
            <div class="flex items-center justify-between gap-4 border-t border-line pt-4 text-t2 text-muted">
                <span>Нажмите на тариф, перед которым должен стоять «{{ $movingName }}»</span>
                <button type="button" wire:click="cancelMove" class="link shrink-0">Отмена</button>
            </div>
        @else
            <p class="border-t border-line pt-4 text-t2 text-muted">В этом порядке тарифы показываются на сайте и в кабинете учителя. Чтобы поменять порядок, перетащите тариф за значок слева.</p>
        @endif
    </x-ui.card>

    @if ($undoOrder)
        <x-ui.toast-action message="Порядок сохранён — на сайте он уже новый" action="undo" close="dismissUndo" wire:key="undo-{{ md5(json_encode($undoOrder)) }}" />
    @endif
</div>
