<div class="fi-in-repeatable-item-ctn divide-y divide-gray-200 dark:divide-gray-700">
    @forelse($records as $record)
        <div class="flex items-center justify-between gap-4 p-3">
            <div class="flex items-center gap-4 min-w-0">
                <span class="fi-in-text-item-label text-sm font-medium text-gray-950 dark:text-white truncate">
                    {{ $record->label }}
                </span>
                <span class="fi-in-text-item-label text-sm text-gray-500 dark:text-gray-400">
                    @if($record->status === \App\Models\PaymentRecord::STATUS_PAID && $record->paid_at)
                        оплачено {{ $record->paid_at->format('d.m.Y') }}
                    @else
                        оплата до {{ $record->due_date->format('d.m.Y') }}
                    @endif
                </span>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @if($record->status === \App\Models\PaymentRecord::STATUS_PAID)
                    <x-filament::badge color="success">Оплачено</x-filament::badge>
                @elseif($record->status === \App\Models\PaymentRecord::STATUS_CANCELLED)
                    <x-filament::badge color="gray">Оплата не требуется</x-filament::badge>
                @elseif($record->isOverdue())
                    <x-filament::badge color="danger">Срок прошёл</x-filament::badge>
                @else
                    <x-filament::badge color="warning">Ожидает оплаты</x-filament::badge>
                @endif
            </div>
        </div>
    @empty
        <div class="fi-in-placeholder p-4 text-sm text-gray-400 dark:text-gray-500">
            Записей об оплате пока нет. Они появляются сами: после каждого проведённого занятия — при поурочной оплате, в начале каждого месяца — при помесячной.
        </div>
    @endforelse
</div>
