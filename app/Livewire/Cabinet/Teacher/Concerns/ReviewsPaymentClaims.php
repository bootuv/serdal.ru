<?php

namespace App\Livewire\Cabinet\Teacher\Concerns;

use App\Models\PaymentClaim;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Services\PaymentClaimService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;

/**
 * «Ученик сообщил об оплате» и «Напомнить» в кабинете учителя (Сегодня, Ученики, карточка ученика).
 * Окно проверки — partials/payment-claim-modal.blade.php, логика — PaymentClaimService.
 * Компонент определяет rememberPaid(int $studentId, array $ids) — для «Отменить» после подтверждения.
 */
trait ReviewsPaymentClaims
{
    /** Открытая на проверку заявка. */
    #[Locked]
    public ?int $claimId = null;

    /** Окно в режиме «Отклонить»: причина необязательна. */
    public bool $claimRejecting = false;

    public string $claimReason = '';

    private function claims(): PaymentClaimService
    {
        return app(PaymentClaimService::class);
    }

    public function openClaim(int $claimId): void
    {
        $this->claims()->findPending(auth()->user(), $claimId);

        $this->claimId = $claimId;
        $this->claimRejecting = false;
        $this->claimReason = '';
        $this->resetValidation('claimReason');
    }

    public function closeClaim(): void
    {
        $this->claimId = null;
        $this->claimRejecting = false;
        $this->claimReason = '';
    }

    public function confirmClaim(): void
    {
        if (! $this->claimId) {
            return;
        }

        $teacher = auth()->user();
        $claim = $this->claims()->findPending($teacher, $this->claimId);
        $marked = $this->claims()->confirm($teacher, $claim);
        $this->closeClaim();

        if ($marked->isNotEmpty()) {
            $this->rememberPaid((int) $claim->student_id, $marked->pluck('id')->all());
        }

        $sum = PaymentClaimService::knownSum($marked);
        $this->dispatch('toast', message: 'Оплата подтверждена' . ($sum ? ': ' . Money::format($sum) : ''));
    }

    public function rejectClaim(): void
    {
        if (! $this->claimId) {
            return;
        }

        $this->validate(['claimReason' => ['nullable', 'string', 'max:1000']], [], ['claimReason' => 'причина']);

        $teacher = auth()->user();
        $claim = $this->claims()->findPending($teacher, $this->claimId);
        $this->claims()->reject($teacher, $claim, $this->claimReason);
        $this->closeClaim();

        $this->dispatch('toast', message: 'Оплата не подтверждена — ученик получит уведомление');
    }

    /** «Напомнить» об оплате: не чаще раза в сутки. */
    public function remind(int $studentId): void
    {
        $teacher = auth()->user();
        $service = app(TeacherStudentsService::class);
        abort_unless($service->owns($teacher, $studentId), 404);

        $this->dispatch('toast', message: match ($service->remind($teacher, $studentId)) {
            'sent' => 'Напомнили',
            'too_soon' => 'Уже напоминали за последние сутки — можно будет завтра',
            default => 'Напоминать не о чем — долгов нет',
        });
    }

    /**
     * Заявки на проверке по ученикам: [id ученика => ['id', 'when', 'facts']] (самая ранняя заявка ученика).
     */
    protected function pendingClaims(User $teacher, ?int $studentId = null): Collection
    {
        return $this->claims()->pendingForTeacher($teacher, $studentId)
            ->groupBy('student_id')
            ->map(function (Collection $group) {
                /** @var PaymentClaim $claim */
                $claim = $group->first();
                $records = $group->flatMap->records->where('status', PaymentRecord::STATUS_UNPAID)->unique('id');
                $sum = PaymentClaimService::knownSum($records);

                return [
                    'id' => $claim->id,
                    'when' => HumanDate::at($claim->created_at),
                    'facts' => implode(' · ', array_filter([
                        $records->isNotEmpty() ? app(TeacherStudentsService::class)->countLabel($records) : null,
                        $sum ? Money::format($sum) : null,
                        $claim->files ? 'чек приложен' : null,
                    ])),
                ];
            });
    }

    /** Данные окна проверки заявки. */
    protected function claimView(User $teacher): array
    {
        $claim = $this->claimId
            ? PaymentClaim::pending()->where('teacher_id', $teacher->id)->with(['student:id,name,first_name', 'records.meetingSession.room'])->find($this->claimId)
            : null;

        if (! $claim) {
            $this->claimId = null;

            return ['claimView' => null];
        }

        $records = $claim->records->sortBy('due_date')->values();
        $sum = PaymentClaimService::knownSum($records);

        return ['claimView' => [
            'student' => $claim->student?->name ?? 'Ученик',
            'firstName' => $claim->student?->first_name ?: \Illuminate\Support\Str::before(trim((string) $claim->student?->name), ' '),
            'when' => 'сообщил(а) ' . HumanDate::at($claim->created_at),
            'rows' => $records->map(fn (PaymentRecord $r) => [
                'id' => $r->id,
                'title' => $r->human_label,
                'amount' => ($a = $r->amount()) ? Money::format($a) : null,
                'done' => $r->status !== PaymentRecord::STATUS_UNPAID,
            ])->all(),
            'sum' => $sum ? Money::format($sum) : null,
            'comment' => $claim->comment,
            'files' => $this->claims()->files($claim, $teacher),
        ]];
    }
}
