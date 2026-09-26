{{-- Статус платежа — только исключения: не прошёл (проблема), возврат, ожидает оплаты. «Оплачен» не показываем. --}}
@if ($status === \App\Models\SubscriptionPayment::STATUS_FAILED)
    <x-ui.badge tone="danger">Не прошёл</x-ui.badge>
@elseif ($status === \App\Models\SubscriptionPayment::STATUS_REFUNDED)
    <x-ui.badge>Возврат</x-ui.badge>
@elseif ($status === \App\Models\SubscriptionPayment::STATUS_PENDING)
    <x-ui.badge>Ожидает оплаты</x-ui.badge>
@endif
