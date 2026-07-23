@php
    // При блокировке баннер не нужен: ученик и так видит только страницу
    // «Оплата» с объяснением ограничения
    $isPaymentBlocked = auth()->user()?->payment_blocked_at !== null;

    $overdueRecords = $isPaymentBlocked
        ? collect()
        : \App\Models\PaymentRecord::overdue()
            ->where('student_id', auth()->id())
            ->with('teacher:id,name')
            ->get();
    $teacherNames = $overdueRecords->pluck('teacher.name')->filter()->unique()->join(', ');
@endphp

@if($overdueRecords->isNotEmpty())
    <div class="rounded-xl p-4 ring-1 mt-6 mb-2"
        style="background-color: rgba(245, 158, 11, 0.1); --tw-ring-color: rgba(245, 158, 11, 0.4);">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="flex items-start gap-3 min-w-0">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 shrink-0" style="color: #f59e0b;" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        Напоминание об оплате занятий
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        У вас есть неоплаченные занятия у преподавателя: {{ $teacherNames }}.
                        Пожалуйста, не забудьте про оплату, чтобы доступ к кабинету не был ограничен.
                    </p>
                </div>
            </div>
            @unless(request()->routeIs('filament.student.pages.payment-debts'))
                <a href="{{ \App\Filament\Student\Pages\PaymentDebts::getUrl() }}"
                    class="shrink-0 inline-flex items-center gap-1 px-3 py-2 text-sm font-medium rounded-lg text-white"
                    style="background-color: #f59e0b;">
                    Подробнее
                </a>
            @endunless
        </div>
    </div>
@endif
