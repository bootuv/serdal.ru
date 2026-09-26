{{-- Выбор способа оплаты (ЮKassa): кнопки-варианты с логотипами, под ними подпись выбранного.
     methods — SubscriptionCheckoutService::paymentMethods(), model — свойство Livewire, active — выбранный ключ. --}}
<div class="flex flex-col gap-2">
    <span id="pm-{{ $model }}" class="text-t2 font-medium">Способ оплаты</span>
    <div class="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="pm-{{ $model }}">
        @foreach ($methods as $key => $m)
            <button type="button" role="radio" aria-checked="{{ $active === $key ? 'true' : 'false' }}" wire:click="$set('{{ $model }}', '{{ $key }}')"
                    @class(['inline-flex h-11 items-center gap-2 whitespace-nowrap rounded bg-white px-4 text-t2 text-ink',
                            'font-semibold shadow-outline-ink' => $active === $key,
                            'font-medium shadow-outline hover:shadow-outline-ink' => $active !== $key])><x-ui.pay-logo :type="$key" />{{ $m['title'] }}</button>
        @endforeach
    </div>
    @if (isset($methods[$active]))<span class="text-t3 text-muted">{{ $methods[$active]['subtitle'] }}</span>@endif
</div>
