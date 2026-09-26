<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\LessonRows;
use App\Livewire\Cabinet\Teacher\Concerns\MarksPayments;
use App\Livewire\Cabinet\Teacher\Concerns\PlansLessons;
use App\Livewire\Cabinet\Teacher\Concerns\StartsLessons;
use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\HomeworkSubmission;
use App\Models\LessonType;
use App\Models\Message;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Services\PaymentRecordService;
use App\Services\SubscriptionService;
use App\Services\TeacherScheduleService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/** «Сегодня» учителя. Макеты: TeacherToday, SyLimit, LsStartBlocked, SyEmptyTeacher (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Сегодня', 'active' => 'today'])]
class Today extends Component
{
    use LessonRows, MarksPayments, PlansLessons, StartsLessons, TeacherScreen;

    /** Обновляем, когда занятие начинается или завершается. */
    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(): void
    {
        $this->authorizeTeacher();
    }

    public function render()
    {
        $teacher = auth()->user();
        $startBlock = $this->startBlock($teacher);

        $common = [
            'firstName' => $teacher->first_name ?: $teacher->name,
            'today' => HumanDate::todayLong(),
            'startBlock' => $startBlock,
        ] + $this->planView($teacher) + $this->markPaidView($teacher);

        // Новый учитель без занятий — первые шаги
        if (! Room::where('user_id', $teacher->id)->exists()) {
            return view('livewire.cabinet.teacher.today', $common + [
                'empty' => true,
                'steps' => $this->firstSteps($teacher),
                'tariff' => $this->tariffCard($teacher),
            ]);
        }

        return view('livewire.cabinet.teacher.today', $common + [
            'empty' => false,
            'limitBanner' => $startBlock ? $this->limitBanner($teacher, $startBlock) : null,
            'inviteUrl' => Route::has('cabinet.teacher.students') ? route('cabinet.teacher.students', ['invite' => 1]) : url('/tutor/students'),
            'scheduleUrl' => Route::has('cabinet.teacher.schedule') ? route('cabinet.teacher.schedule') : url('/tutor/schedule-calendar'),
        ] + $this->lessonsToday($teacher, (bool) $startBlock) + $this->review($teacher) + $this->payments($teacher) + $this->messages($teacher));
    }

    /** Занятия сегодня: прошедшие, идущее или ближайшее (в фокусе, с главной кнопкой), остальные. */
    private function lessonsToday(User $teacher, bool $blocked): array
    {
        $service = app(TeacherScheduleService::class);
        $lessons = $service->lessons($teacher->id, today(), today()->endOfDay());

        $matched = $this->matchSessions($lessons->where('past', true), $service->sessions($teacher->id, today()));
        $recordings = $this->readyRecordings(collect(array_values($matched)));
        $runningId = $this->otherRunningRoomId($teacher);

        $focus = $lessons->firstWhere('running', true) ?? $lessons->first(fn ($l) => ! $l['past']);

        $rows = $lessons->map(function (array $l) use ($focus, $matched, $recordings, $runningId, $blocked) {
            if ($focus && $l['key'] === $focus['key']) {
                return $this->lessonRow($l, [
                    'focus' => true,
                    'status' => $l['running'] ? 'Идёт сейчас' : Str::ucfirst('начнётся ' . (HumanDate::until($l['start']) ?? 'сейчас')),
                    'facts' => mb_strtolower($this->kindFacts($l)),
                    'action' => $this->startAction($l, $runningId, $blocked),
                ]);
            }

            if ($l['past']) {
                $session = $matched[$l['key']] ?? null;

                return $this->lessonRow($l, [
                    'facts' => $session ? 'Завершено' . (isset($recordings[$session->id]) ? ' · запись готова' : '') : 'Не состоялось',
                    'sessionId' => $session?->id,
                ]);
            }

            return $this->lessonRow($l, ['facts' => $this->kindFacts($l)]);
        });

        $next = null;
        if ($rows->isEmpty()) {
            $upcoming = $service->lessons($teacher->id, today()->addDay(), today()->addDays(14)->endOfDay())->first();
            $next = $upcoming ? [
                'when' => HumanDate::at($upcoming['start']),
                'title' => $upcoming['heading'],
                'url' => $this->lessonUrl($upcoming['roomId']),
            ] : null;
        }

        return ['rows' => $rows->values(), 'focusKey' => $focus['key'] ?? null, 'nextLesson' => $next];
    }

    /** Работы, которые ждут проверки: сданные и ещё не оценённые (как счётчик «Проверка работ» в старом кабинете). */
    private function review(User $teacher): array
    {
        $query = HomeworkSubmission::query()
            ->whereNotNull('submitted_at')
            ->whereNull('grade')
            ->where('status', HomeworkSubmission::STATUS_SUBMITTED)
            ->whereHas('homework', fn ($q) => $q->where('teacher_id', $teacher->id));

        $items = (clone $query)
            ->with(['homework:id,title', 'student:id,name'])
            ->orderBy('submitted_at')
            ->limit(3)
            ->get()
            ->map(function (HomeworkSubmission $s) {
                $days = (int) $s->submitted_at->copy()->startOfDay()->diffInDays(today());

                return [
                    'key' => $s->id,
                    'title' => $s->homework?->title,
                    'student' => $s->student?->name,
                    'studentId' => $s->student_id,
                    'submitted' => 'сдано ' . (in_array(HumanDate::day($s->submitted_at), ['сегодня', 'вчера'], true)
                        ? HumanDate::day($s->submitted_at)
                        : HumanDate::date($s->submitted_at)),
                    'waits' => $days >= 2 ? 'ждёт ' . plural_ru($days, 'день', 'дня', 'дней') : null,
                    'url' => Route::has('cabinet.teacher.review') ? route('cabinet.teacher.review', $s) : url('/tutor/homework-submissions/' . $s->id),
                ];
            });

        return [
            'reviewCount' => $items->isEmpty() ? 0 : (clone $query)->count(),
            'review' => $items,
            'reviewAllUrl' => Route::has('cabinet.teacher.tasks') ? route('cabinet.teacher.tasks') : url('/tutor/homework-submissions'),
        ];
    }

    /** Ученики с неоплаченными занятиями (как PendingPaymentsWidget), плюс только что отмеченные — для «Отменить». */
    private function payments(User $teacher): array
    {
        $records = PaymentRecord::unpaid()
            ->where('teacher_id', $teacher->id)
            ->with(['student:id,name', 'meetingSession:id,room_id,ended_at,pricing_snapshot'])
            ->orderBy('due_date')
            ->get();

        $blocked = $records->isEmpty() ? [] : PaymentRecordService::blockedStudentIds($teacher->id);
        $studentIds = $records->pluck('student_id')->unique()->take(4)->values();

        $rows = $studentIds->map(function (int $studentId) use ($records, $blocked) {
            $own = $records->where('student_id', $studentId);
            $perLesson = $own->where('type', PaymentRecord::TYPE_PER_LESSON)->count();
            $monthly = $own->where('type', PaymentRecord::TYPE_MONTHLY)->map(fn (PaymentRecord $r) => mb_strtolower($r->human_label));
            $sum = $own->sum(fn (PaymentRecord $r) => (int) $r->amount());

            return [
                'id' => $studentId,
                'name' => $own->first()->student?->name,
                'facts' => collect([$perLesson ? plural_ru($perLesson, 'занятие', 'занятия', 'занятий') : null, ...$monthly, $sum ? \App\Support\Money::format($sum) : null])->filter()->implode(' · '),
                'badge' => match (true) {
                    in_array($studentId, $blocked, true) => 'Доступ закрыт',
                    $own->contains(fn (PaymentRecord $r) => $r->isOverdue()) => 'Просрочено',
                    default => null,
                },
                'paid' => false,
            ];
        });

        // Только что отмеченные: строка остаётся с «Оплачено» и кнопкой «Отменить»
        foreach ($this->justPaid as $studentId => $ids) {
            if (! $rows->contains('id', $studentId)) {
                $paid = PaymentRecord::whereIn('id', $ids)->where('status', PaymentRecord::STATUS_PAID)->with('meetingSession:id,pricing_snapshot')->get();
                if ($paid->isNotEmpty()) {
                    $sum = $paid->sum(fn (PaymentRecord $r) => (int) $r->amount());
                    $rows->push([
                        'id' => (int) $studentId,
                        'name' => User::whereKey($studentId)->value('name'),
                        'facts' => plural_ru($paid->count(), 'начисление', 'начисления', 'начислений') . ($sum ? ' · ' . \App\Support\Money::format($sum) : ''),
                        'badge' => null,
                        'paid' => true,
                    ]);
                }
            }
        }

        return [
            'payments' => $rows->values(),
            'paymentsUrl' => Route::has('cabinet.teacher.students') ? route('cabinet.teacher.students') : url('/tutor/students'),
        ];
    }

    /** Последние сообщения учеников в чатах занятий (как список чатов в «Сообщениях» старого кабинета). */
    private function messages(User $teacher): array
    {
        $roomIds = Room::withTrashed()->where('user_id', $teacher->id)->pluck('id');

        $latest = Message::whereIn('room_id', $roomIds)
            ->where('user_id', '!=', $teacher->id)
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->unique('room_id')
            ->take(3);

        $unread = Message::whereIn('room_id', $latest->pluck('room_id'))
            ->where('user_id', '!=', $teacher->id)
            ->whereNull('read_at')
            ->pluck('room_id')
            ->countBy();

        return [
            'messages' => $latest->map(fn (Message $m) => [
                'key' => $m->id,
                'name' => $m->user?->name,
                'userId' => $m->user_id,
                'time' => $m->created_at->isToday() ? $m->created_at->format('H:i') : HumanDate::day($m->created_at),
                'text' => $m->content ? Str::limit(trim(strip_tags($m->content)), 80) : 'Файл',
                'unread' => ($unread[$m->room_id] ?? 0) > 0,
                'url' => url('/tutor/messenger?room=' . $m->room_id),
            ])->values(),
            'messagesUrl' => url('/tutor/messenger'),
        ];
    }

    /** Плашка «Занятия по тарифу закончились» (макет SyLimit). */
    private function limitBanner(User $teacher, array $block): array
    {
        $text = $block['text'];
        $em = null;

        if ($block['limit']) {
            $tariff = $teacher->activeSubscription()?->tariff;
            $resets = SubscriptionService::periodResetsAt($teacher);
            $text = 'Проведено ' . SubscriptionService::lessonsUsedThisPeriod($teacher) . ' из ' . $tariff?->lessons_per_month . '.'
                . ($resets ? ' Новые можно начать' : '');
            $em = $resets ? 'с ' . HumanDate::date($resets) : null;
        }

        return ['title' => $block['title'], 'text' => $text, 'em' => $em, 'action' => $block['primary']];
    }

    /** Чек-лист нового учителя (макет SyEmptyTeacher). */
    private function firstSteps(User $teacher): array
    {
        $lessonType = $teacher->lessonTypes()->where('price', '>', 0)->orderByRaw("type = 'individual' desc")->first();
        $subscription = $teacher->activeSubscription();
        $hasStudents = $teacher->students()->exists();
        $profileUrl = Route::has('cabinet.teacher.profile') ? route('cabinet.teacher.profile') : url('/tutor/edit-profile');
        $subscriptionUrl = Route::has('cabinet.teacher.subscription') ? route('cabinet.teacher.subscription') : url('/tutor/subscription');

        $steps = [
            [
                'key' => 'profile',
                'done' => (bool) $teacher->is_profile_completed,
                'title' => $teacher->is_profile_completed ? 'Профиль заполнен' : 'Заполните профиль',
                'facts' => null,
                'text' => 'Ученики увидят его на вашей странице',
                'url' => $profileUrl,
                'action' => 'Заполнить профиль',
            ],
            [
                'key' => 'prices',
                'done' => (bool) $lessonType,
                'title' => $lessonType ? 'Цены указаны' : 'Укажите цены',
                'facts' => $lessonType ? \App\Support\Money::format((int) $lessonType->price) . ($lessonType->isMonthly() ? ' в месяц' : ($lessonType->duration ? ' за ' . $lessonType->duration . ' минут' : ' за занятие')) : null,
                'text' => 'По ним начисляется оплата занятий',
                'url' => Route::has('cabinet.teacher.profile') ? route('cabinet.teacher.profile') : url('/tutor/lesson-types'),
                'action' => 'Указать цены',
            ],
            [
                'key' => 'tariff',
                'done' => (bool) $subscription,
                'title' => $subscription ? 'Тариф подключён' : 'Подключите тариф',
                'facts' => $subscription ? '«' . $subscription->tariff->name . '»' . ($subscription->tariff->isFree() ? ', бесплатно' : '') : null,
                'text' => 'Без тарифа занятия не начать',
                'url' => $subscriptionUrl,
                'action' => 'Выбрать тариф',
            ],
            [
                'key' => 'invite',
                'done' => $hasStudents,
                'title' => $hasStudents ? 'Ученики приглашены' : 'Пригласите первого ученика',
                'facts' => null,
                'text' => 'Отправьте ему ссылку — после регистрации он появится в «Учениках»',
                'url' => Route::has('cabinet.teacher.students') ? route('cabinet.teacher.students') : url('/tutor/students'),
                'action' => null,
            ],
            [
                'key' => 'plan',
                'done' => false,
                'title' => 'Запланируйте первое занятие',
                'facts' => $hasStudents ? null : 'после приглашения',
                'text' => 'Выберите ученика, дни и время — он получит уведомление',
                'url' => null,
                'action' => null,
            ],
        ];

        $current = collect($steps)->search(fn ($s) => ! $s['done']);

        return [
            'items' => collect($steps)->map(fn ($s, $i) => $s + ['n' => $i + 1, 'current' => $i === $current])->all(),
            'done' => collect($steps)->where('done', true)->count(),
            'total' => count($steps),
            'invitation' => $current !== false && $steps[$current]['key'] === 'invite'
                ? app(TeacherStudentsService::class)->invitationLink($teacher)
                : null,
            'emailUrl' => Route::has('cabinet.teacher.students') ? route('cabinet.teacher.students', ['invite' => 1]) : url('/tutor/students'),
        ];
    }

    /** Карточка тарифа нового учителя: сколько занятий осталось и ограничения. */
    private function tariffCard(User $teacher): ?array
    {
        $subscription = $teacher->activeSubscription();
        $url = Route::has('cabinet.teacher.subscription') ? route('cabinet.teacher.subscription') : url('/tutor/subscription');

        if (! $subscription) {
            return ['name' => null, 'url' => $url];
        }

        $tariff = $subscription->tariff;
        $limit = $tariff->lessons_per_month;
        $resets = SubscriptionService::periodResetsAt($teacher);

        return [
            'name' => $tariff->name,
            'url' => $url,
            'left' => $limit !== null ? max(0, $limit - SubscriptionService::lessonsUsedThisPeriod($teacher)) . ' из ' . $limit : null,
            'leftSub' => $limit !== null ? 'занятий осталось' . ($resets ? ' · обновится ' . HumanDate::date($resets) : '') : null,
            'limits' => collect([
                $tariff->max_participants ? 'до ' . plural_ru($tariff->max_participants, 'участника', 'участников', 'участников') : null,
                $tariff->max_duration_minutes ? 'до ' . plural_ru($tariff->max_duration_minutes, 'минуты', 'минут', 'минут') . ' в занятии' : null,
            ])->filter()->implode(' и '),
        ];
    }
}
