<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\MarksPayments;
use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Services\PaymentRecordService;
use App\Services\TeacherScheduleService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Ученики учителя. Макеты: «Учитель · Ученики» (TeacherStudents), пустой список (PmTeacherEmpty),
 * окна «Пригласить ученика» (PmInvite), «Отметить оплату» (PmMarkPaid, общий трейт MarksPayments)
 * и «Продлить срок оплаты» (PmExtend). Логика — TeacherStudentsService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Ученики', 'active' => 'students'])]
class Students extends Component
{
    use MarksPayments, TeacherScreen;

    #[Url(except: 'students')]
    public string $tab = 'students';

    #[Url(except: 'all')]
    public string $filter = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Окно «Пригласить ученика»: link — по ссылке, find — уже на Serdal. */
    public bool $inviteOpen = false;

    public string $inviteTab = 'link';

    public string $inviteEmail = '';

    public string $findQuery = '';

    public ?int $pickId = null;

    /** Окно «Продлить срок оплаты». */
    public ?int $extendStudentId = null;

    public int $extendDays = 3;

    public function mount(): void
    {
        $this->authorizeTeacher();
    }

    private function service(): TeacherStudentsService
    {
        return app(TeacherStudentsService::class);
    }

    private function teacher(): User
    {
        return auth()->user();
    }

    /** Ученик из списка текущего учителя, иначе 404. */
    private function ownStudent(int $studentId): User
    {
        abort_unless($this->service()->owns($this->teacher(), $studentId), 404);

        return User::findOrFail($studentId);
    }

    /*
     | Оплата
     */

    public function openExtend(int $studentId): void
    {
        $this->ownStudent($studentId);
        $this->extendStudentId = $studentId;
        $this->extendDays = 3;
    }

    public function closeExtend(): void
    {
        $this->extendStudentId = null;
    }

    public function extend(): void
    {
        if (! $this->extendStudentId) {
            return;
        }

        $student = $this->ownStudent($this->extendStudentId);
        $ids = $this->service()->unpaidRecords($this->teacher(), $student->id)->pluck('id')->all();
        $due = $this->service()->extendRecords($this->teacher(), $student->id, $ids, $this->extendDays);

        $this->extendStudentId = null;
        $this->dispatch('toast', message: $due ? 'Срок продлён до ' . HumanDate::date($due) : 'Продлевать нечего — долгов нет');
    }

    /*
     | Приглашение
     */

    public function openInvite(string $tab = 'link'): void
    {
        $this->resetErrorBag();
        $this->inviteTab = in_array($tab, ['link', 'find'], true) ? $tab : 'link';
        $this->inviteEmail = '';
        $this->findQuery = '';
        $this->pickId = null;
        $this->inviteOpen = true;
    }

    public function closeInvite(): void
    {
        $this->inviteOpen = false;
    }

    public function updatedFindQuery(): void
    {
        $this->pickId = null;
    }

    public function sendInvite(): void
    {
        $this->validate(
            ['inviteEmail' => 'required|email'],
            ['inviteEmail.required' => 'Укажите email ученика', 'inviteEmail.email' => 'Проверьте email: похоже, в нём опечатка']
        );

        $this->service()->sendInvitation($this->teacher(), $this->inviteEmail);
        $this->inviteOpen = false;
        $this->dispatch('toast', message: 'Приглашение отправлено на ' . $this->inviteEmail);
        $this->inviteEmail = '';
    }

    public function addStudent(): void
    {
        $student = $this->pickId
            ? $this->service()->availableStudentsQuery($this->teacher())->whereKey($this->pickId)->first()
            : null;

        if (! $student) {
            $this->addError('pickId', 'Выберите ученика из списка');

            return;
        }

        $added = $this->service()->attachExisting($this->teacher(), $student);
        $this->inviteOpen = false;
        $this->filter = 'all';
        $this->tab = 'students';
        $this->dispatch('toast', message: $added ? $student->name . ' теперь в вашем списке' : 'Ученик уже в вашем списке');
    }

    /*
     | Данные экрана
     */

    public function render()
    {
        $teacher = $this->teacher();
        $all = $this->studentRows($teacher);
        $groups = $this->groupRows($teacher, $all);

        $needle = mb_strtolower(trim($this->search));
        $matches = fn (array $row) => $needle === '' || collect($row['haystack'])->contains(fn ($v) => $v && str_contains(mb_strtolower($v), $needle));

        $rows = $all->filter($matches)->filter(fn (array $r) => match ($this->filter) {
            'debt' => $r['owes'],
            'none' => $r['none'],
            default => true,
        })->values();

        $debtCount = $all->where('owes', true)->count();
        $noneCount = $all->where('none', true)->count();

        return view('livewire.cabinet.teacher.students', [
            'isEmpty' => $all->isEmpty(),
            'sub' => $all->isEmpty() ? null : implode(' · ', array_filter([
                plural_ru($all->count(), 'ученик', 'ученика', 'учеников'),
                $groups->isNotEmpty() ? plural_ru($groups->count(), 'группа', 'группы', 'групп') : null,
            ])),
            'rows' => $rows,
            'groups' => $groups->filter($matches)->values(),
            'filters' => [
                'all' => 'Все',
                'debt' => 'Ждут оплаты · ' . $debtCount,
                'none' => 'Без занятий · ' . $noneCount,
            ],
            'debts' => $this->debts($teacher, $all),
            'debtTotalLabel' => $this->totalLabel($all->flatMap(fn ($r) => $r['owes'] ? $r['unpaid'] : [])),
            'invite' => $this->inviteOpen ? $this->inviteData($teacher) : null,
            'extend' => $this->extendStudentId ? $this->extendData($teacher, $all) : null,
            'flash' => session('cabinet_toast'),
        ] + $this->markPaidView($teacher));
    }

    /** Ученики учителя с занятиями, ближайшим занятием и состоянием оплаты. */
    private function studentRows(User $teacher): Collection
    {
        $students = $this->service()->query($teacher)
            ->with(['assignedRooms' => fn ($q) => $q->where('rooms.user_id', $teacher->id)])
            ->orderBy('name')
            ->get();

        $pivots = DB::table('teacher_student')->where('teacher_id', $teacher->id)->get()->keyBy('student_id');
        $records = PaymentRecord::where('teacher_id', $teacher->id)->with('meetingSession.room')->get()->groupBy('student_id');
        $blocked = PaymentRecordService::blockedStudentIds($teacher->id);

        return $students->map(function (User $s) use ($teacher, $pivots, $records, $blocked) {
            $rooms = $s->assignedRooms->sortBy('name')->values();
            $pivot = $pivots[$s->id] ?? null;
            $payment = $this->service()->paymentState($teacher, $s->id, (bool) $pivot?->is_free,
                $records[$s->id] ?? collect(), in_array($s->id, $blocked, true));
            $next = $this->nextRoom($rooms);

            $labels = $rooms->map(fn (Room $r) => $this->isGroup($r) ? 'группа «' . $r->name . '»' : $r->name);
            $since = $pivot?->created_at ? HumanDate::date(\Illuminate\Support\Carbon::parse($pivot->created_at)) : null;

            return [
                'id' => $s->id,
                'user' => $s,
                'name' => $s->name,
                'firstName' => $this->firstName($s),
                'href' => $s->username ? route('cabinet.teacher.student', $s) : null,
                'sub' => $rooms->isNotEmpty()
                    ? $labels->take(2)->implode(' · ') . ($labels->count() > 2 ? ' и ещё ' . ($labels->count() - 2) : '')
                    : ($since ? 'В списке с ' . $since : 'Занятий пока нет'),
                'none' => $rooms->isEmpty(),
                'next' => $next ? $this->nextView($next) : null,
                'state' => $payment['state'],
                'unpaid' => $payment['unpaid'],
                'owes' => in_array($payment['state'], ['blocked', 'overdue', 'unpaid'], true),
                'payNote' => match ($payment['state']) {
                    'free' => 'бесплатно',
                    'blocked', 'overdue', 'unpaid' => $this->countLabel($payment['unpaid']),
                    default => null,
                },
                'haystack' => [$s->name, $s->email, $s->phone],
            ];
        });
    }

    /** Группы учителя: занятия с несколькими учениками. */
    private function groupRows(User $teacher, Collection $students): Collection
    {
        $byId = $students->keyBy('id');

        return Room::query()
            ->where('user_id', $teacher->id)
            ->with(['participants:users.id,users.name,users.first_name', 'schedules'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Room $r) => $this->isGroup($r) || $r->participants->count() > 1)
            ->map(function (Room $room) use ($byId) {
                $people = $room->participants;
                $overdue = $people->filter(fn (User $u) => in_array($byId[$u->id]['state'] ?? null, ['blocked', 'overdue'], true))->count();
                $unpaid = $people->filter(fn (User $u) => ($byId[$u->id]['state'] ?? null) === 'unpaid')->count();

                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'people' => $people->map(fn (User $u) => $this->firstName($u))->take(5)->implode(', ')
                        . ($people->count() > 5 ? ' и ещё ' . ($people->count() - 5) : ''),
                    'next' => $this->nextView($room, false),
                    'schedule' => $this->scheduleSummary($room),
                    'overdue' => $overdue,
                    'unpaid' => $unpaid,
                    'href' => Route::has('cabinet.teacher.lesson') ? route('cabinet.teacher.lesson', $room) : url('/tutor/rooms/' . $room->id),
                    'haystack' => [$room->name, ...$people->pluck('name')->all()],
                ];
            })
            ->values();
    }

    /** Фокус «Ждут оплаты»: сначала закрытый вход и просрочка, затем по сроку. Только что оплаченные остаются до ухода со страницы. */
    private function debts(User $teacher, Collection $all): Collection
    {
        return $all
            ->filter(fn ($r) => $r['owes'] || isset($this->justPaid[$r['id']]))
            ->sortBy(fn ($r) => [
                isset($this->justPaid[$r['id']]) ? 1 : 0,
                match ($r['state']) { 'blocked' => 0, 'overdue' => 1, default => 2 },
                $r['unpaid']->first()?->due_date?->timestamp ?? PHP_INT_MAX,
            ])
            ->map(function ($r) use ($teacher) {
                if (! $r['owes']) {
                    return ['row' => $r, 'paid' => true, 'undo' => true, 'meta' => 'Оплата отмечена сейчас', 'note' => null, 'overdue' => false];
                }

                $first = $r['unpaid']->first();
                $overdue = in_array($r['state'], ['blocked', 'overdue'], true);
                $note = null;

                if ($r['state'] === 'blocked') {
                    $note = $r['firstName'] . ' не может войти в ваши занятия, пока вы не отметите оплату или не продлите срок.';
                } elseif ($overdue) {
                    $left = PaymentRecordService::debtStatus($r['id'], $teacher->id)['lessons_left'];
                    $note = 'Ещё ' . plural_ru($left, 'занятие', 'занятия', 'занятий') . ' с долгом — и ' . $r['firstName'] . ' не сможет войти в ваши занятия.';
                }

                return [
                    'row' => $r,
                    'paid' => false,
                    'undo' => isset($this->justPaid[$r['id']]),
                    'overdue' => $overdue,
                    'meta' => $this->countLabel($r['unpaid']) . ($first?->due_date
                        ? ($overdue ? ' · срок был ' . HumanDate::date($first->due_date) : ' · до ' . HumanDate::date($first->due_date))
                        : ''),
                    'note' => $note,
                ];
            })
            ->values();
    }

    private function inviteData(User $teacher): array
    {
        $q = trim($this->findQuery);

        return [
            'link' => $this->service()->invitationLink($teacher),
            'results' => $q === '' ? collect() : $this->service()->searchAvailable($teacher, $q, 10),
            'query' => $q,
        ];
    }

    private function extendData(User $teacher, Collection $all): ?array
    {
        $row = $all->firstWhere('id', $this->extendStudentId);
        $records = $row ? $row['unpaid'] : collect();
        $first = $records->first();

        if (! $first) {
            return null;
        }

        $new = $first->extendedDue($this->extendDays);
        $overdue = $first->isOverdue();

        return [
            'sub' => $row['name'] . ' · ' . $this->countLabel($records),
            'newDate' => 'до ' . HumanDate::day($new),
            'explain' => $overdue
                ? 'Срок прошёл ' . HumanDate::date($first->due_date) . ' — считаем от сегодня. До новой даты вход в занятия не закроется.'
                : 'Считаем от текущего срока — ' . HumanDate::day($first->due_date) . '. Напоминание сдвинется.',
        ];
    }

    /*
     | Помощники
     */

    private function isGroup(Room $room): bool
    {
        return $room->type === 'group';
    }

    private function firstName(User $u): string
    {
        return $u->first_name ?: Str::before(trim($u->name), ' ');
    }

    /** Ближайшее занятие из набора: идущее сейчас, иначе с ближайшим началом. */
    private function nextRoom(Collection $rooms): ?Room
    {
        return $rooms
            ->filter(fn (Room $r) => $r->is_running
                || ($r->next_start && $r->next_start->copy()->addMinutes($r->duration ?: 45)->isFuture()))
            ->sortBy(fn (Room $r) => [$r->is_running ? 0 : 1, $r->next_start?->timestamp ?? PHP_INT_MAX])
            ->first();
    }

    /** «Сегодня в 16:00» + пояснение (через сколько — жирным, иначе название занятия). */
    private function nextView(Room $room, bool $withRoom = true): ?array
    {
        if ($room->is_running) {
            return ['when' => 'Идёт сейчас', 'note' => $withRoom ? $this->roomLabel($room) : null, 'urgent' => true];
        }

        $start = $room->next_start;
        if (! $start || $start->copy()->addMinutes($room->duration ?: 45)->isPast()) {
            return null;
        }

        $soon = $start->isFuture() && $start->lte(now()->addHours(3));

        return [
            'when' => Str::ucfirst(HumanDate::at($start)),
            'note' => $soon ? HumanDate::until($start) : ($withRoom ? $this->roomLabel($room) : null),
            'urgent' => $soon,
        ];
    }

    private function roomLabel(Room $room): string
    {
        return $this->isGroup($room) ? 'группа «' . $room->name . '»' : $room->name;
    }

    /** «по вт и чт · 90 минут» по активному расписанию занятия. */
    private function scheduleSummary(Room $room): ?string
    {
        $schedule = $room->schedules->firstWhere('is_active', true);

        return $schedule ? implode(' · ', array_filter([
            TeacherScheduleService::repeatLabel($schedule),
            $schedule->duration_minutes ? plural_ru($schedule->duration_minutes, 'минута', 'минуты', 'минут') : null,
        ])) : null;
    }

    /** Итог в шапке фокус-блока: сумма, если известна, иначе количество. */
    private function totalLabel(Collection $records): string
    {
        return $this->service()->amountLabel($records) ?? $this->service()->countLabel($records);
    }

    /** «2 занятия · 3 000 ₽» (сумма — если известна у всех начислений). */
    private function countLabel(Collection $records): string
    {
        return implode(' · ', array_filter([
            $this->service()->countLabel($records),
            $this->service()->amountLabel($records),
        ]));
    }
}
