{{-- Карточка ученика: «Ждёт оплаты». focus — фокус-блок (вкладка «Оплата»), waive — кнопка «Не требовать оплату».
     claim — заявка «Ученик сообщил об оплате» на проверке (трейт ReviewsPaymentClaims), canRemind — есть просрочка. --}}
<x-ui.card :focus="$focus" aria-labelledby="{{ $id }}">
    <x-ui.card-head :id="$id" title="Ждёт оплаты">
        <x-slot:action>
            @if ($dues->isNotEmpty())
                <span class="text-t1 font-medium">{{ $dueSum ?? $dueCount }}</span>
            @elseif ($paidNow->isNotEmpty())
                <x-ui.badge tone="ok">Оплачено</x-ui.badge>
            @endif
        </x-slot:action>
    </x-ui.card-head>

    @if ($dues->isEmpty() && $paidNow->isEmpty())
        <p class="text-t2 text-muted">
            {{ $isFree ? $firstName . ' занимается бесплатно — оплата не отслеживается.' : 'Пока пусто — все занятия оплачены. Новая запись появится после следующего занятия.' }}
        </p>
    @else
        {{-- Ученик сообщил об оплате — главное на карточке: жирный заголовок, чек, жёлтая кнопка --}}
        @if ($claim && $dues->isNotEmpty())
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-4" wire:key="claim-{{ $id }}-{{ $claim['id'] }}" data-lightbox-group>
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    @foreach (array_slice($claim['receipts'] ?? [], 0, 2) as $r)
                        <a href="{{ $r['url'] }}" data-lightbox data-name="{{ $r['name'] }}" class="shrink-0" aria-label="Чек: {{ $r['name'] }}"><x-ui.file-tile :name="$r['name']" :thumb="$r['url']" :on-mint="$focus" /></a>
                    @endforeach
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="text-t1 font-semibold">Ученик сообщил об оплате</span>
                        <span class="text-t2 text-muted">{{ \Illuminate\Support\Str::ucfirst($claim['when']) }}@if ($claim['facts']) · {{ $claim['facts'] }}@endif</span>
                    </div>
                </div>
                {{-- Жёлтая — на вкладке «Оплата»; в «Обзоре» жёлтая уже у «Начать занятие» --}}
                <x-ui.btn :variant="$focus ? 'primary' : 'outline'" wire:click="openClaim({{ $claim['id'] }})" class="self-start lg:self-center">Проверить оплату</x-ui.btn>
            </div>
        @endif

        <x-ui.list>
            @foreach ($dues as $d)
                <x-ui.row wire:key="due-{{ $id }}-{{ $d['id'] }}">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="text-t1 font-medium">{{ $d['title'] }}</span>
                        @if ($d['hint'])<span class="text-t2 font-semibold text-ink">{{ $d['hint'] }}</span>@endif
                        @if ($d['overdue'])<span class="flex sm:hidden"><x-ui.badge tone="danger">Просрочено</x-ui.badge></span>@endif
                    </div>
                    @if ($d['overdue'])<x-ui.badge tone="danger" class="hidden sm:inline-flex">Просрочено</x-ui.badge>@endif
                    @if ($d['amount'])<span class="shrink-0 text-t1 font-medium">{{ $d['amount'] }}</span>@endif
                </x-ui.row>
            @endforeach
            @foreach ($paidNow as $p)
                <x-ui.row wire:key="paid-{{ $id }}-{{ $loop->index }}">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="text-t1 font-medium">{{ $p['title'] }}</span>
                        <span class="text-t2 text-muted">Оплачено сегодня</span>
                    </div>
                    @if ($p['amount'])<span class="shrink-0 text-t1 font-medium">{{ $p['amount'] }}</span>@endif
                </x-ui.row>
            @endforeach
        </x-ui.list>

        @if ($debtStatus && $dues->isNotEmpty())
            <p class="text-t2 text-muted">
                @if ($debtStatus['blocked'])
                    <x-ui.em>{{ $firstName }} не может войти в ваши занятия</x-ui.em>, пока вы не отметите оплату, не продлите срок или не нажмёте «Не требовать оплату».
                @else
                    Ещё {{ plural_ru($debtStatus['lessons_left'], 'занятие', 'занятия', 'занятий') }} с долгом — и {{ $firstName }} не сможет войти в ваши занятия.
                @endif
            </p>
        @endif

        <div class="flex flex-wrap gap-2">
            @if ($dues->isNotEmpty())
                <x-ui.btn size="s" wire:click="openModal('mark')">Отметить оплату</x-ui.btn>
                <x-ui.btn size="s" wire:click="openModal('extend')">Продлить срок</x-ui.btn>
                @if ($canRemind && ! $claim)<x-ui.btn size="s" wire:click="remind({{ $student->id }})" wire:loading.attr="disabled" wire:target="remind">Напомнить</x-ui.btn>@endif
                @if ($waive)<x-ui.btn size="s" wire:click="openModal('waive')">Не требовать оплату</x-ui.btn>@endif
            @endif
            @if ($paidNow->isNotEmpty())
                <x-ui.btn size="s" wire:click="undoPaid">Отменить</x-ui.btn>
            @endif
        </div>
    @endif
</x-ui.card>
