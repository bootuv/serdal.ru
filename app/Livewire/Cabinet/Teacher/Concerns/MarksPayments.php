<?php

namespace App\Livewire\Cabinet\Teacher\Concerns;

use App\Models\PaymentRecord;
use App\Models\User;
use App\Services\TeacherStudentsService;
use Livewire\Attributes\Locked;

/**
 * «Отметить оплату» в кабинете учителя (как PendingPaymentsWidget: PaymentRecord::markAs(paid)) с отменой в течение экрана.
 * Одно начисление отмечается сразу, несколько — через окно выбора (макет PmMarkPaid, partials/mark-paid-modal.blade.php).
 */
trait MarksPayments
{
    /** @var array<int, array<int>> только что отмеченные начисления: ученик → id начислений (для «Отменить») */
    public array $justPaid = [];

    /** Ученик в окне выбора начислений: меняется только сервером (markPaid проверяет, что это ученик учителя). */
    #[Locked]
    public ?int $markStudentId = null;

    /** @var array<int> выбранные в окне начисления */
    public array $markSelected = [];

    /** Отметить оплату ученика: $recordIds — конкретные начисления; без них — все неоплаченные (если одно) или окно выбора. */
    public function markPaid(int $studentId, ?array $recordIds = null): void
    {
        $teacher = auth()->user();
        abort_unless(app(TeacherStudentsService::class)->owns($teacher, $studentId), 404);
        $unpaid = app(TeacherStudentsService::class)->unpaidRecords($teacher, $studentId);

        if ($recordIds === null && $unpaid->count() > 1) {
            $this->markStudentId = $studentId;
            $this->markSelected = $unpaid->pluck('id')->all();
            $this->resetValidation();

            return;
        }

        $ids = $recordIds ?? $unpaid->pluck('id')->all();
        $this->applyPaid($teacher, $studentId, $ids);
    }

    public function confirmMarkPaid(): void
    {
        $this->validate(['markSelected' => ['required', 'array', 'min:1']], ['markSelected.required' => 'Отметьте хотя бы одно занятие']);

        $this->applyPaid(auth()->user(), (int) $this->markStudentId, array_map('intval', $this->markSelected));
        $this->closeMarkPaid();
    }

    public function closeMarkPaid(): void
    {
        $this->markStudentId = null;
        $this->markSelected = [];
    }

    public function undoPaid(int $studentId): void
    {
        $ids = $this->justPaid[$studentId] ?? [];

        if ($ids) {
            app(TeacherStudentsService::class)->undoPaid(auth()->user(), $studentId, $ids);
            $this->dispatch('toast', message: 'Отметка об оплате отменена');
        }

        unset($this->justPaid[$studentId]);
    }

    private function applyPaid(User $teacher, int $studentId, array $ids): void
    {
        $marked = app(TeacherStudentsService::class)->markRecords($teacher, $studentId, $ids, PaymentRecord::STATUS_PAID);

        if ($marked->isEmpty()) {
            return;
        }

        $this->rememberPaid($studentId, $marked->pluck('id')->all());
        $sum = $marked->sum(fn (PaymentRecord $r) => (int) $r->amount());
        $this->dispatch('toast', message: 'Оплата отмечена' . ($sum ? ': ' . \App\Support\Money::format($sum) : ''));
    }

    /** Только что отмеченные начисления ученика — строка остаётся с «Отменить». */
    protected function rememberPaid(int $studentId, array $ids): void
    {
        $this->justPaid[$studentId] = $ids;
    }

    /** Данные окна выбора начислений. */
    protected function markPaidView(User $teacher): array
    {
        if (! $this->markStudentId) {
            return [];
        }

        $records = app(TeacherStudentsService::class)->unpaidRecords($teacher, $this->markStudentId);
        $selected = array_map('intval', $this->markSelected);

        return [
            'markStudent' => User::find($this->markStudentId, ['id', 'name']),
            'markRows' => $records->map(fn (PaymentRecord $r) => [
                'id' => $r->id,
                'title' => $r->human_label,
                'hint' => $r->due_date ? ($r->isOverdue() ? 'срок был до ' : 'оплатить до ') . \App\Support\HumanDate::date($r->due_date) : null,
                'overdue' => $r->isOverdue(),
                'amount' => $r->amount(),
            ])->all(),
            'markSum' => $records->whereIn('id', $selected)->sum(fn (PaymentRecord $r) => (int) $r->amount()),
        ];
    }
}
