{{-- Личная страница основателя: взнос за текущий сбор, куда переводить, расходы, прошлые взносы. После входа под привязанным профилем. --}}
<div class="flex flex-col gap-6">
    <x-ui.page-head title="Сбор на расходы" :sub="$name . ' · доля ' . $share" :back="$homeUrl" back-label="В кабинет" />

    <x-ui.card focus aria-labelledby="fp-now">
        <div class="flex flex-col gap-1">
            <h2 id="fp-now" class="text-t2 text-muted">{{ $periodTitle }}</h2>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="text-num font-medium">{{ $amount }}</span>
                @if ($state === 'paid')<x-ui.badge tone="ok">Внесено</x-ui.badge>@endif
            </div>
            @if ($dueLine)
                <span @class(['text-t2', 'font-semibold text-danger-fg' => $overdue, 'text-muted' => ! $overdue])>{{ $dueLine }}</span>
            @endif
        </div>

        @if ($lines->isNotEmpty())
            <x-ui.list>
                @foreach ($lines as $l)
                    <div wire:key="fpl-{{ $l['id'] }}" class="flex items-baseline justify-between gap-4 border-t border-line py-3 last:pb-0">
                        <span class="min-w-0 text-t1-s">{{ $l['label'] }}@if ($l['paid'])<span class="text-muted"> · внесено</span>@endif</span>
                        <span @class(['shrink-0 text-t1-s font-medium', 'text-muted' => $l['paid']])>{{ $l['amount'] }}</span>
                    </div>
                @endforeach
            </x-ui.list>
        @endif

        @if ($state === 'due')
            <x-ui.btn variant="primary" class="self-start" wire:click="claim" wire:loading.attr="disabled">Я перевёл</x-ui.btn>
        @elseif ($state === 'claimed')
            <p class="text-t2">{{ $claimedLine }}. <button type="button" wire:click="unclaim" class="link">Отменить</button></p>
        @elseif ($state === 'none')
            <p class="text-t2 text-muted">В этом месяце вносить ничего не нужно.</p>
        @endif
    </x-ui.card>

    @if ($state === 'due' || $state === 'claimed')
        <x-ui.card aria-labelledby="fp-pay">
            <x-ui.card-head id="fp-pay" title="Куда переводить">
                @if ($payNumber)
                    <x-slot:action><x-ui.copy :value="$payNumber" message="Номер скопирован" size="s">Скопировать номер</x-ui.copy></x-slot:action>
                @endif
            </x-ui.card-head>
            @if (empty($payment))
                <p class="text-t2 text-muted">Реквизиты пока не указаны — уточните у того, кто оплачивает расходы.</p>
            @else
                <div class="flex flex-col">
                    @foreach ($payment as $row)
                        <div class="flex items-baseline justify-between gap-4 border-t border-line py-3 first:border-t-0 first:pt-0 last:pb-0">
                            <span class="shrink-0 text-t2 text-muted">{{ $row['label'] }}</span>
                            <span class="min-w-0 text-right text-t1-s font-medium">{{ $row['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @endif

    @if ($expenses->isNotEmpty())
        <x-ui.card aria-labelledby="fp-exp">
            <x-ui.card-head id="fp-exp" title="Расходы в месяц" />
            <div class="flex flex-col">
                @foreach ($expenses as $e)
                    <div wire:key="fpe-{{ $e['id'] }}" class="flex items-start justify-between gap-4 border-t border-line py-3 first:border-t-0 first:pt-0">
                        <span class="flex min-w-0 flex-col gap-1">
                            <span class="text-t1-s">{{ $e['name'] }}</span>
                            @if ($e['sub'])<span class="text-t3 text-muted">{{ $e['sub'] }}</span>@endif
                        </span>
                        <span class="shrink-0 text-t1-s font-medium">{{ $e['monthly'] }}</span>
                    </div>
                @endforeach
                <div class="flex items-baseline justify-between gap-4 border-t border-line pt-3">
                    <span class="text-t2 text-muted">Всего на всех</span>
                    <span class="text-t1-s font-medium">{{ $total }}</span>
                </div>
            </div>
        </x-ui.card>
    @endif

    @if ($past->isNotEmpty())
        <x-ui.card aria-labelledby="fp-past">
            <x-ui.card-head id="fp-past" title="Прошлые взносы" />
            <div class="flex flex-col">
                @foreach ($past as $p)
                    <div wire:key="fpp-{{ $p['id'] }}" class="flex items-start justify-between gap-4 border-t border-line py-3 first:border-t-0 first:pt-0 last:pb-0">
                        <span class="flex min-w-0 flex-col gap-1">
                            <span class="text-t1-s">{{ $p['title'] }}</span>
                            <span @class(['text-t3', 'font-semibold text-danger-fg' => $p['debt'], 'text-muted' => ! $p['debt']])>{{ $p['sub'] }}</span>
                        </span>
                        <span class="shrink-0 text-t1-s font-medium">{{ $p['amount'] }}</span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif
</div>
