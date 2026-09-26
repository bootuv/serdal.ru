{{-- Логотип способа оплаты (public/images/payment): type — тип ЮKassa (sbp, sberbank, tinkoff_bank, bank_card, yoo_money).
     По умолчанию — знак 20 в строку (кнопки выбора способа); tile — белая плитка 40 с обводкой (сохранённый способ).
     Неизвестный тип: знак не рисуется, плитка показывает иконку кошелька. --}}
@props(['type' => null, 'tile' => false])
@php $src = \App\Services\SubscriptionCheckoutService::paymentLogo($type); @endphp
@if ($tile)
    <span {{ $attributes->class('flex size-10 shrink-0 items-center justify-center rounded bg-white shadow-outline') }}>
        @if ($src)<img src="{{ $src }}" alt="" class="size-6 object-contain">@else<x-ui.icon name="wallet" size="s" class="text-ink" />@endif
    </span>
@elseif ($src)
    <img src="{{ $src }}" alt="" {{ $attributes->class('size-5 shrink-0 object-contain') }}>
@endif
