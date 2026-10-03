<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\PaymentClaim;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Services\PaymentClaimService;
use App\Services\PaymentRecordService;
use App\Services\StudentTeachersService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Оплата ученика. Макет: «Ученик · Оплата» (StudentPayments, docs/design/BRAND.md).
 * Онлайн-оплаты у ученика нет (решение владельца): он платит учителю напрямую и «Сообщает об оплате» —
 * выбирает занятия, прикладывает чек; учитель подтверждает (PaymentClaimService). ?report=<id учителя> — сразу открыть окно.
 * Суммы — PaymentRecord::amount() (поурочные — из снимка цен занятия, помесячные — цена за месяц в начислении).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Оплата', 'active' => 'payments'])]
class Payments extends Component
{
    use WithFileUploads;

    /** Окно «Сообщить об оплате»: учитель, которому сообщаем. */
    #[Locked]
    public ?int $reportTeacherId = null;

    /** @var array<int> выбранные начисления */
    public array $reportSelected = [];

    /** Только что выбранные в зоне загрузки файлы (проверяются и переносятся в $receipts). */
    public array $picked = [];

    /** @var array<int, TemporaryUploadedFile> чеки к отправке */
    public array $receipts = [];

    public string $reportComment = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_STUDENT, 403);

        // ?report=<id учителя> — «Сообщить об оплате» с главной
        if ($teacherId = request()->integer('report')) {
            $this->openReport($teacherId, quiet: true);
        }
    }

    private function claims(): PaymentClaimService
    {
        return app(PaymentClaimService::class);
    }

    /*
     | «Сообщить об оплате»
     */

    public function openReport(int $teacherId, bool $quiet = false): void
    {
        $records = $this->claims()->claimableRecords(auth()->id(), $teacherId);

        if ($records->isEmpty()) {
            if (! $quiet) {
                $this->dispatch('toast', message: 'Об этих занятиях вы уже сообщили — учитель ещё не ответил');
            }

            return;
        }

        $this->reportTeacherId = $teacherId;
        $this->reportSelected = $records->pluck('id')->all();
        $this->picked = [];
        $this->receipts = [];
        $this->reportComment = '';
        $this->resetValidation();
    }

    public function closeReport(): void
    {
        $this->reportTeacherId = null;
        $this->reportSelected = [];
        $this->picked = [];
        $this->receipts = [];
        $this->reportComment = '';
    }

    protected function messages(): array
    {
        $kb = PaymentClaimService::MAX_FILE_KB / 1024;

        return [
            'picked.*.max' => "Файл больше {$kb} МБ — сфотографируйте чек ещё раз или сохраните в PDF.",
            'picked.*.extensions' => 'Можно приложить фото (JPG, PNG, HEIC) или PDF.',
            'picked.*.mimes' => 'Можно приложить фото (JPG, PNG, HEIC) или PDF.',
            'receipts.*.max' => "Файл больше {$kb} МБ — сфотографируйте чек ещё раз или сохраните в PDF.",
            'receipts.*.extensions' => 'Можно приложить фото (JPG, PNG, HEIC) или PDF.',
            'receipts.*.mimes' => 'Можно приложить фото (JPG, PNG, HEIC) или PDF.',
            'reportSelected.required' => 'Отметьте хотя бы одно занятие',
            'reportComment.max' => 'Комментарий слишком длинный',
        ];
    }

    /** Выбрали файлы — проверяем и добавляем к уже выбранным (не больше MAX_FILES). */
    public function updatedPicked(): void
    {
        $this->validate(['picked.*' => PaymentClaimService::fileRules()]);

        foreach ($this->picked as $file) {
            if ($file instanceof TemporaryUploadedFile && count($this->receipts) < PaymentClaimService::MAX_FILES) {
                $this->receipts[] = $file;
            }
        }

        $this->picked = [];
    }

    public function removeReceipt(int $index): void
    {
        unset($this->receipts[$index]);
        $this->receipts = array_values($this->receipts);
    }

    public function sendReport(): void
    {
        if (! $this->reportTeacherId) {
            return;
        }

        $this->validate([
            'reportSelected' => ['required', 'array', 'min:1'],
            'receipts' => ['array', 'max:' . PaymentClaimService::MAX_FILES],
            'receipts.*' => PaymentClaimService::fileRules(),
            'reportComment' => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($this->receipts) && trim($this->reportComment) === '') {
            $this->addError('receipts', 'Приложите чек или напишите учителю, как оплатили');

            return;
        }

        try {
            $this->claims()->submit(auth()->user(), $this->reportTeacherId, $this->reportSelected, $this->receipts, $this->reportComment);
        } catch (\DomainException $e) {
            $this->addError('reportSelected', $e->getMessage());

            return;
        }

        $this->closeReport();
        $this->dispatch('toast', message: 'Отправили учителю — он проверит и подтвердит оплату');
    }

    /*
     | Данные экрана
     */

    public function render()
    {
        $studentId = auth()->id();

        $records = PaymentRecord::query()
            ->where('student_id', $studentId)
            ->whereIn('status', [PaymentRecord::STATUS_UNPAID, PaymentRecord::STATUS_PAID, PaymentRecord::STATUS_CANCELLED])
            ->with(['teacher:id,name,first_name,avatar,telegram,whatsup,phone', 'meetingSession.room:id,name'])
            ->get();

        $unpaid = $records->where('status', PaymentRecord::STATUS_UNPAID)->sortBy('due_date');
        $debtStatuses = PaymentRecordService::debtStatuses($studentId);
        $pending = $this->claims()->pendingRecordIds($studentId);

        $debts = $unpaid->groupBy('teacher_id')
            ->map(fn (Collection $group, $teacherId) => $this->debtView($group, (int) $teacherId, $debtStatuses[(int) $teacherId]['status'] ?? null, $pending))
            ->sortByDesc(fn ($d) => [(int) ($d['status']['blocked'] ?? false), (int) (bool) $d['status']])
            ->values();

        return view('livewire.cabinet.student.payments', [
            'unpaidCount' => $unpaid->count(),
            'debts' => $debts,
            'months' => $this->history($records),
            'teachers' => $this->teachers($records, $unpaid, $studentId),
            'hasRecords' => $records->isNotEmpty(),
            'report' => $this->reportTeacherId ? $this->reportView($studentId) : null,
        ]);
    }

    /** Долг перед одним учителем: строки с суммами, итог, отправленные заявки с чеками или причина отказа. */
    private function debtView(Collection $group, int $teacherId, ?array $status, array $pending): array
    {
        $claimable = $group->reject(fn (PaymentRecord $r) => in_array($r->id, $pending, true));
        $latest = $this->claims()->latestForStudent(auth()->id(), $teacherId);
        $sum = PaymentClaimService::knownSum($group);
        $first = $group->first();

        return [
            'teacher' => $first->teacher,
            'teacherId' => $teacherId,
            'status' => $status,
            'total' => $sum ? Money::format($sum) : null,
            'count' => app(TeacherStudentsService::class)->countLabel($group),
            'due' => $first->due_date && ! $first->isOverdue() ? HumanDate::day($first->due_date) : null,
            'canReport' => $claimable->isNotEmpty(),
            // Отправленные учителю и ещё не проверенные: когда, комментарий и чеки
            'sent' => PaymentClaim::pending()
                ->where('student_id', auth()->id())
                ->where('teacher_id', $teacherId)
                ->oldest('id')
                ->get()
                ->map(fn (PaymentClaim $c) => [
                    'id' => $c->id,
                    'when' => HumanDate::at($c->created_at),
                    'comment' => trim((string) $c->comment),
                    'files' => $this->claims()->files($c, auth()->user()),
                ])
                ->values(),
            // Отказ показываем, пока по этим занятиям не сообщили снова
            'rejected' => $latest?->status === PaymentClaim::STATUS_REJECTED && $claimable->isNotEmpty()
                ? ['reason' => $latest->reject_reason]
                : null,
            'rows' => $group->map(fn (PaymentRecord $r) => [
                'id' => $r->id,
                'title' => $r->human_label,
                'overdue' => $r->isOverdue(),
                'amount' => ($a = $r->amount()) ? Money::format($a) : null,
                'claimed' => in_array($r->id, $pending, true),
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
        ];
    }

    /** Окно «Сообщить об оплате»: неоплаченные занятия учителя (без уже отправленных), итог по выбранным. */
    private function reportView(int $studentId): ?array
    {
        $records = $this->claims()->claimableRecords($studentId, $this->reportTeacherId);

        if ($records->isEmpty()) {
            $this->reportTeacherId = null;

            return null;
        }

        $selected = $records->whereIn('id', array_map('intval', $this->reportSelected));
        $sum = PaymentClaimService::knownSum($selected);

        return [
            'teacher' => User::find($this->reportTeacherId, ['id', 'name']),
            'rows' => $records->map(fn (PaymentRecord $r) => [
                'id' => $r->id,
                'title' => $r->human_label,
                'hint' => $r->due_date ? ($r->isOverdue() ? 'срок был ' : 'оплатить до ') . HumanDate::date($r->due_date) : null,
                'overdue' => $r->isOverdue(),
                'amount' => ($a = $r->amount()) ? Money::format($a) : null,
            ])->all(),
            'sum' => $sum ? Money::format($sum) : null,
            'files' => collect($this->receipts)->map(fn ($f) => [
                'name' => $f instanceof TemporaryUploadedFile ? $f->getClientOriginalName() : 'Файл',
                'meta' => $f instanceof TemporaryUploadedFile ? $this->fileSize($f->getSize()) : null,
            ])->all(),
            'maxFiles' => count($this->receipts) >= PaymentClaimService::MAX_FILES,
        ];
    }

    private function fileSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1, ',', '') . ' МБ'
            : max(1, (int) round($bytes / 1024)) . ' КБ';
    }

    /**
     * История по месяцам (новые сверху). В строках — оплаченное и «без оплаты» с суммами; неоплаченное — в фокус-блоке.
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
                $paid = $group->where('status', PaymentRecord::STATUS_PAID);
                $paidSum = $paid->sum(fn (PaymentRecord $r) => (int) $r->amount());

                return [
                    'title' => Str::ucfirst(HumanDate::month($group->first()->billingMonth())),
                    'note' => implode(' · ', array_filter([
                        $waiting
                            ? plural_ru($waiting, 'счёт ждёт оплаты', 'счёта ждут оплаты', 'счетов ждут оплаты')
                            : ($paid->isNotEmpty() ? 'Всё оплачено · ' . plural_ru($paid->count(), 'оплата', 'оплаты', 'оплат') : 'Без оплаты'),
                        $paidSum ? 'оплачено ' . Money::format($paidSum) : null,
                    ])),
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
                        'amount' => $r->status === PaymentRecord::STATUS_PAID && ($a = $r->amount()) ? Money::format($a) : null,
                    ])->values(),
                ];
            })
            ->filter(fn ($m) => $m['rows']->isNotEmpty())
            ->values();
    }

    /** «Как вы платите»: учителя (сначала те, кому должны) — цена, срок, контакты и чат. */
    private function teachers(Collection $records, Collection $unpaid, int $studentId): Collection
    {
        $owed = $unpaid->pluck('teacher_id')->unique();
        $student = auth()->user();
        $service = app(TeacherStudentsService::class);

        return $records->pluck('teacher')->filter()->unique('id')
            ->sortBy(fn (User $t) => [$owed->contains($t->id) ? 0 : 1, $t->name])
            ->map(fn (User $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'photo' => $t->photoThumb(),
                'terms' => $service->studentTerms($t, $student),
                'contacts' => $t->contactLinks(),
                'chat' => app(StudentTeachersService::class)->chatUrl($studentId, $t->id),
            ])
            ->values();
    }
}
