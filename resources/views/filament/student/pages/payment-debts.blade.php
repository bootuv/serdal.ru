<x-filament-panels::page>
    @if($isBlocked)
        <div class="rounded-xl p-4 ring-1"
            style="background-color: rgba(239, 68, 68, 0.08); --tw-ring-color: rgba(239, 68, 68, 0.35);">
            <div class="flex items-start gap-3">
                <x-heroicon-o-lock-closed class="w-6 h-6 shrink-0" style="color: #ef4444;" />
                <div>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Личный кабинет ограничен</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        У вас есть занятия, которые не были оплачены в срок. Доступ к кабинету восстановится
                        автоматически, как только преподаватель отметит оплату. Пожалуйста, свяжитесь с преподавателем
                        и договоритесь об оплате.
                    </p>
                </div>
            </div>
        </div>
    @endif

    @forelse($recordsByTeacher as $teacherRecords)
        @php($teacher = $teacherRecords->first()->teacher)
        <x-filament::section>
            <x-slot name="heading">
                Преподаватель: {{ $teacher?->name ?? 'Неизвестен' }}
            </x-slot>

            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($teacherRecords as $record)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-950 dark:text-white truncate">
                                {{ $record->label }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                оплата до {{ $record->due_date->format('d.m.Y') }}
                            </p>
                        </div>
                        <div class="shrink-0">
                            @if($record->isOverdue())
                                <x-filament::badge color="danger">Просрочено</x-filament::badge>
                            @else
                                <x-filament::badge color="warning">Ожидает оплаты</x-filament::badge>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if($teacher && ($teacher->telegram || $teacher->whatsup || $teacher->phone))
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Связаться с преподавателем:</span>
                    @if($teacher->telegram)
                        <a href="https://t.me/{{ $teacher->telegram }}" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400 rounded-md ring-1 ring-inset ring-blue-600/20 dark:ring-blue-400/30">
                            Telegram
                        </a>
                    @endif
                    @if($teacher->whatsup)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $teacher->whatsup) }}" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400 rounded-md ring-1 ring-inset ring-green-600/20 dark:ring-green-400/30">
                            WhatsApp
                        </a>
                    @endif
                    @if($teacher->phone)
                        <a href="tel:{{ $teacher->phone }}"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200 rounded-md">
                            {{ $teacher->phone }}
                        </a>
                    @endif
                </div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <div class="flex items-center gap-3">
                <x-heroicon-o-check-circle class="w-6 h-6" style="color: #22c55e;" />
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Все занятия оплачены. Спасибо!
                </p>
            </div>
        </x-filament::section>
    @endforelse

    @if($paidRecords->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                История оплат
            </x-slot>

            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($paidRecords as $record)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-950 dark:text-white truncate">
                                {{ $record->label }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $record->teacher?->name ?? 'Преподаватель неизвестен' }}
                                @if($record->paid_at)
                                    · оплачено {{ $record->paid_at->format('d.m.Y') }}
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0">
                            <x-filament::badge color="success">Оплачено</x-filament::badge>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    <p class="text-xs text-gray-400 dark:text-gray-500">
        Оплата происходит напрямую преподавателю. Отметку об оплате ставит преподаватель после получения оплаты.
    </p>
</x-filament-panels::page>
