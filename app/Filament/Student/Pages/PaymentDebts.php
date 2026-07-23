<?php

namespace App\Filament\Student\Pages;

use App\Models\PaymentRecord;
use Filament\Pages\Page;

class PaymentDebts extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Оплата';

    protected static ?string $title = 'Оплата занятий';

    public function getTitle(): string
    {
        return auth()->user()?->payment_blocked_at ? 'Доступ ограничен' : 'Оплата занятий';
    }

    protected static ?string $slug = 'payment-debts';

    protected static string $view = 'filament.student.pages.payment-debts';

    // В меню показываем, как только у ученика появилось хоть одно начисление
    // (неоплаченное или оплаченное) — страница также служит историей оплат
    public static function shouldRegisterNavigation(): bool
    {
        return PaymentRecord::where('student_id', auth()->id())
            ->whereIn('status', [PaymentRecord::STATUS_UNPAID, PaymentRecord::STATUS_PAID])
            ->exists();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PaymentRecord::unpaid()
            ->where('student_id', auth()->id())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function getViewData(): array
    {
        $records = PaymentRecord::unpaid()
            ->where('student_id', auth()->id())
            ->with(['teacher', 'meetingSession.room'])
            ->orderBy('due_date')
            ->get()
            ->groupBy('teacher_id');

        $paidRecords = PaymentRecord::where('student_id', auth()->id())
            ->where('status', PaymentRecord::STATUS_PAID)
            ->with(['teacher', 'meetingSession.room'])
            ->orderByDesc('paid_at')
            ->get();

        return [
            'recordsByTeacher' => $records,
            'paidRecords' => $paidRecords,
            'isBlocked' => auth()->user()->payment_blocked_at !== null,
        ];
    }
}
