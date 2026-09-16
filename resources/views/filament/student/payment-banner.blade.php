@php
    // Красный баннер: есть преподаватели, к чьим занятиям ученик уже не допускается.
    // Жёлтый: просрочка есть, но лимит занятий с долгом ещё не исчерпан — предупреждение.
    $debtStatuses = \App\Services\PaymentRecordService::debtStatuses(auth()->id());
    $blocked = $debtStatuses->filter(fn($item) => $item['status']['blocked']);
    $warned = $debtStatuses->reject(fn($item) => $item['status']['blocked']);
    $isPaymentsPage = request()->routeIs('filament.student.pages.payment-debts');
    $lessonsWord = fn(int $n) => trans_choice('{1} :count занятие|[2,4] :count занятия|[5,*] :count занятий', $n);
@endphp

@if($blocked->isNotEmpty())
    <div class="rounded-xl p-4 ring-1 mt-6 mb-2"
        style="background-color: rgba(239, 68, 68, 0.08); --tw-ring-color: rgba(239, 68, 68, 0.35);">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="flex items-start gap-3 min-w-0">
                <x-heroicon-o-lock-closed class="w-6 h-6 shrink-0" style="color: #ef4444;" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        Доступ к занятиям ограничен
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        У вас есть занятия, не оплаченные в срок, у {{ $blocked->count() === 1 ? 'преподавателя' : 'преподавателей' }}:
                        {{ $blocked->map(fn($item) => $item['teacher']->name)->join(', ') }}.
                        Пока оплата не отмечена, присоединиться к занятиям {{ $blocked->count() === 1 ? 'этого преподавателя' : 'этих преподавателей' }} нельзя.
                        Остальные занятия и кабинет доступны как обычно. Пожалуйста, свяжитесь с преподавателем и договоритесь об оплате.
                    </p>
                </div>
            </div>
            @unless($isPaymentsPage)
                <a href="{{ \App\Filament\Student\Pages\PaymentDebts::getUrl() }}"
                    class="shrink-0 inline-flex items-center gap-1 px-3 py-2 text-sm font-medium rounded-lg text-white"
                    style="background-color: #ef4444;">
                    Подробнее
                </a>
            @endunless
        </div>
    </div>
@endif

@if($warned->isNotEmpty())
    <div class="rounded-xl p-4 ring-1 mt-6 mb-2"
        style="background-color: rgba(245, 158, 11, 0.1); --tw-ring-color: rgba(245, 158, 11, 0.4);">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="flex items-start gap-3 min-w-0">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 shrink-0" style="color: #f59e0b;" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        Предупреждение: занятия не оплачены в срок
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        @foreach($warned as $item)
                            @php($left = $item['status']['lessons_left'])
                            {{ $item['teacher']->name }}:
                            @if($left <= 1)
                                после следующего занятия доступ к занятиям этого преподавателя закроется.
                            @else
                                доступ к занятиям закроется через {{ $lessonsWord($left) }}.
                            @endif
                        @endforeach
                        Пожалуйста, договоритесь с преподавателем об оплате, чтобы этого не произошло.
                    </p>
                </div>
            </div>
            @unless($isPaymentsPage)
                <a href="{{ \App\Filament\Student\Pages\PaymentDebts::getUrl() }}"
                    class="shrink-0 inline-flex items-center gap-1 px-3 py-2 text-sm font-medium rounded-lg text-white"
                    style="background-color: #f59e0b;">
                    Подробнее
                </a>
            @endunless
        </div>
    </div>
@endif
