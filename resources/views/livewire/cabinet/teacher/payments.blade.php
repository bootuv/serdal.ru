<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Все платежи" :sub="$sub" :back="$backUrl" back-label="Тариф и платежи" />

    @if ($months->isEmpty())
        <x-ui.empty icon="wallet" title="Платежей пока нет" text="Когда вы оплатите тариф или дополнительные занятия, платежи и чеки появятся здесь.">
            <x-slot:action><x-ui.btn :href="$backUrl">Выбрать тариф</x-ui.btn></x-slot:action>
        </x-ui.empty>
    @else
        <x-ui.card aria-label="Платежи по месяцам">
            <x-ui.list>
                @foreach ($months as $month)
                    <x-ui.disclosure :title="$month['title']" :meta="$month['note']" :open="$loop->first" wire:key="pm-{{ $loop->index }}-{{ $month['title'] }}">
                        @foreach ($month['rows'] as $row)
                            <div class="flex flex-wrap items-center gap-4 border-t border-line py-3 pl-4 lg:flex-nowrap" wire:key="pay-{{ $row['id'] }}">
                                {{-- basis-1/2: не меньше половины строки под текст, иначе правый блок переносится вниз --}}
                                <div class="flex min-w-0 flex-1 basis-1/2 flex-col gap-1 lg:basis-0">
                                    <span class="text-t1-s font-medium">{{ $row['title'] }}</span>
                                    <span class="text-t2 text-muted">{{ $row['meta'] }}</span>
                                    @if ($row['refund'])
                                        <span class="text-t2 text-muted">{{ $row['refund'] }}@unless (str_starts_with($row['refund'], 'Проверочный')). Деньги вернутся на карту, с которой была оплата, в течение {{ plural_ru($refundDays, 'рабочего дня', 'рабочих дней', 'рабочих дней') }}@endunless</span>
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center gap-4">
                                    @if ($row['refunded'])
                                        <x-ui.badge>Вернули</x-ui.badge>
                                    @elseif ($row['pending'])
                                        @if ($row['payUrl'])
                                            <span class="text-t3 text-muted">ссылка до {{ $row['payUntil'] }}</span>
                                            <x-ui.btn size="s" :href="$row['payUrl']">Оплатить</x-ui.btn>
                                        @else
                                            <x-ui.badge>Ожидает оплаты</x-ui.badge>
                                        @endif
                                    @endif
                                    <span class="whitespace-nowrap text-t1 font-semibold">{{ $row['amount'] }}</span>
                                    @if ($row['receiptUrl'])
                                        <a href="{{ $row['receiptUrl'] }}" target="_blank" rel="noopener" class="link text-t2" aria-label="{{ $row['receiptLabel'] }}">Чек</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </x-ui.disclosure>
                @endforeach
            </x-ui.list>
        </x-ui.card>
    @endif
</div>
