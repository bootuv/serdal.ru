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
        <x-ui.list>
            @if ($claim && $dues->isNotEmpty())
                <x-ui.row wire:key="claim-{{ $id }}-{{ $claim['id'] }}">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="text-t1 font-semibold">Ученик сообщил об оплате</span>
                        <span class="text-t2 text-muted">{{ \Illuminate\Support\Str::ucfirst($claim['when']) }}@if ($claim['facts']) · {{ $claim['facts'] }}@endif</span>
                    </div>
                    <x-ui.btn size="s" wire:click="openClaim({{ $claim['id'] }})">Проверить</x-ui.btn>
                </x-ui.row>
            @endif
            @foreach ($dues as $d)
                <x-ui.row wire:key="due-{{ $id }}-{{ $d['id'] }}">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="text-t1 font-medium">{{ $d['title'] }}</span>
                        @if ($d['hint'])<span class="text-t2 font-semibold text-ink">{{ $d['hint'] }}</span>@endif
                    </div>
                    @if ($d['overdue'])<x-ui.badge tone="danger">Просрочено</x-ui.badge>@endif
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
                    <x-ui.em>{{ $firstName }} не может войти в ваши занятия</x-ui.em>, пока вы не отметите оплату или не продлите срок.
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
