<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\PaymentRecord;
use App\Models\User;
use App\Services\PaymentRecordService;
use App\Services\StudentTeachersService;
use App\Support\HumanDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Оплата ученика. Макет: «Ученик · Оплата» (docs/design/BRAND.md).
 * Онлайн-оплаты у ученика нет: он платит учителю напрямую, учитель отмечает оплату. Сумм в начислениях нет.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Оплата', 'active' => 'payments'])]
class Payments extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_STUDENT, 403);
    }

    public function render()
    {
        $studentId = auth()->id();

        $records = PaymentRecord::query()
            ->where('student_id', $studentId)
            ->whereIn('status', [PaymentRecord::STATUS_UNPAID, PaymentRecord::STATUS_PAID, PaymentRecord::STATUS_CANCELLED])
            ->with(['teacher:id,name,telegram,whatsup,phone', 'meetingSession.room:id,name'])
            ->get();

        $unpaid = $records->where('status', PaymentRecord::STATUS_UNPAID)->sortBy('due_date');
        $debtStatuses = PaymentRecordService::debtStatuses($studentId);

        return view('livewire.cabinet.student.payments', [
            'unpaidCount' => $unpaid->count(),
            'debts' => $unpaid->groupBy('teacher_id')->map(fn (Collection $group, $teacherId) => [
                'teacher' => $group->first()->teacher,
                'status' => $debtStatuses[(int) $teacherId]['status'] ?? null,
                'rows' => $group->map(fn (PaymentRecord $r) => [
                    'title' => $r->human_label,
                    'overdue' => $r->isOverdue(),
                    // Срок: сегодня/завтра — срочно (жирным), просрочено — бейдж
                    'due' => match (true) {
                        ! $r->due_date => null,
                        $r->isOverdue() => 'срок был ' . HumanDate::date($r->due_date),
                        $r->due_date->isToday() => 'оплатить сегодня',
                        $r->due_date->isTomorrow() => 'оплатить до завтра',
                        default => 'оплатить до ' . HumanDate::day($r->due_date),
                    },
                    'urgent' => $r->due_date && ! $r->isOverdue() && $r->due_date->lte(today()->addDay()),
                ])->values(),
            ])->sortByDesc(fn ($d) => [(int) ($d['status']['blocked'] ?? false), (int) (bool) $d['status']])->values(),
            'months' => $this->history($records),
            'teachers' => $this->teachers($records, $unpaid, $studentId),
            'hasRecords' => $records->isNotEmpty(),
        ]);
    }

    /**
     * История по месяцам (новые сверху). В строках — оплаченное и «без оплаты»; неоплаченное — в фокус-блоке.
     */
    private function history(Collection $records): Collection
    {
        return $records
            ->groupBy(fn (PaymentRecord $r) => $r->billingMonth()->format('Y-m'))
            ->sortKeysDesc()
            ->map(function (Collection $group) {
                $settled = $group->where('status', '!=', PaymentRecord::STATUS_UNPAID)
                    ->sortByDesc(fn (PaymentRecord $r) => $r->paid_at?->timestamp ?? $r->updated_at?->timestamp ?? 0);
                $waiting = $group->where('status', PaymentRecord::STATUS_UNPAID)->count();
                $paid = $group->where('status', PaymentRecord::STATUS_PAID)->count();

                return [
                    'title' => Str::ucfirst(HumanDate::month($group->first()->billingMonth())),
                    'note' => $waiting
                        ? plural_ru($waiting, 'счёт ждёт оплаты', 'счёта ждут оплаты', 'счетов ждут оплаты')
                        : ($paid ? 'Всё оплачено · ' . plural_ru($paid, 'оплата', 'оплаты', 'оплат') : 'Без оплаты'),
                    'waiting' => $waiting > 0,
                    'rows' => $settled->map(fn (PaymentRecord $r) => [
                        'title' => $r->human_label,
                        'sub' => implode(' · ', array_filter([
                            $r->teacher?->name,
                            match (true) {
                                $r->status === PaymentRecord::STATUS_CANCELLED => 'оплата не требуется',
                                (bool) $r->paid_at => 'оплачено ' . HumanDate::date($r->paid_at) . ($r->isPaidLate() ? ', позже срока' : ''),
                                default => null,
                            },
                        ])),
                        'waived' => $r->status === PaymentRecord::STATUS_CANCELLED,
                    ])->values(),
                ];
            })
            ->filter(fn ($m) => $m['rows']->isNotEmpty())
            ->values();
    }

    /** Кому платить: учителя с начислениями (сначала те, кому должны), их контакты и чат. */
    private function teachers(Collection $records, Collection $unpaid, int $studentId): Collection
    {
        $owed = $unpaid->pluck('teacher_id')->unique();

        return $records->pluck('teacher')->filter()->unique('id')
            ->sortBy(fn (User $t) => [$owed->contains($t->id) ? 0 : 1, $t->name])
            ->map(fn (User $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'contacts' => $t->contactLinks(),
                'chat' => app(StudentTeachersService::class)->chatUrl($studentId, $t->id),
            ])
            ->values();
    }
}
