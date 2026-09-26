<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Recording;
use App\Models\RoomSchedule;
use App\Models\User;
use App\Services\PaymentRecordService;
use App\Services\StudentScheduleService;
use App\Support\HumanDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Расписание ученика. Макет: «Ученик · Расписание» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Расписание', 'active' => 'schedule'])]
class Schedule extends Component
{
    /** На сколько дней вперёд показываем предстоящие занятия. */
    private const DAYS_AHEAD = 14;

    /** Прошедшие занятия показываем порциями по три недели. */
    private const PAST_WEEKS_STEP = 3;

    #[Url(except: 'upcoming')]
    public string $tab = 'upcoming';

    /** Учитель, чьи занятия показаны ('all' — все). */
    #[Url(as: 'teacher', except: 'all')]
    public string $teacher = 'all';

    public int $pastWeeks = self::PAST_WEEKS_STEP;

    public bool $confirmGoogleDisconnect = false;

    /** Обновляем, когда учитель начинает или завершает занятие. */
    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_STUDENT, 403);
    }

    public function updatedTab(): void
    {
        if (! in_array($this->tab, ['upcoming', 'past'], true)) {
            $this->tab = 'upcoming';
        }
    }

    public function showEarlier(): void
    {
        $this->pastWeeks += self::PAST_WEEKS_STEP;
    }

    public function render()
    {
        $student = auth()->user();
        $teachers = $student->teachers()->orderBy('name')->get(['users.id', 'users.name']);
        $teacherId = $teachers->count() > 1 && $teachers->contains('id', (int) $this->teacher) ? (int) $this->teacher : null;

        $data = $this->tab === 'past'
            ? $this->past($student, $teacherId)
            : $this->upcoming($student, $teacherId);

        return view('livewire.cabinet.student.schedule', $data + [
            'teacherFilter' => $teachers->count() > 1
                ? ['all' => 'Все учителя'] + $teachers->pluck('name', 'id')->all()
                : [],
            'googleConnected' => ! empty($student->google_access_token),
            'paymentsUrl' => Route::has('cabinet.student.payments') ? route('cabinet.student.payments') : url('/student/payment-debts'),
        ]);
    }

    /** Предстоящие занятия на две недели: первое (идущее или ближайшее) — в фокусе. */
    private function upcoming(User $student, ?int $teacherId): array
    {
        $service = app(StudentScheduleService::class);
        $schedules = $service->schedules($student->id);
        $blockedTeacherIds = PaymentRecordService::blockedTeacherIds($student->id);
        $now = now();

        // Занятия, у которых есть повторяющееся расписание: разовое в них — дополнительное
        $recurringRoomIds = $schedules->where('type', '!=', 'once')->pluck('room_id')->unique()->all();
        $recurrence = $schedules->keyBy('id');

        $events = $service->events($student->id, today(), today()->addDays(self::DAYS_AHEAD)->endOfDay(), $schedules)
            ->when($teacherId, fn ($c) => $c->where('teacher_id', $teacherId))
            // Идёт сейчас: комната запущена и это её сегодняшнее занятие
            ->map(fn (array $e) => $e + ['running' => $e['is_running'] && ($e['type'] === 'running' || $e['start']->isToday())])
            ->filter(fn (array $e) => $e['running'] || $e['end']->gt($now))
            ->values();

        // У запущенной комнаты «идёт» только одно занятие — ближайшее к текущему времени
        $events = $events->groupBy('room_id')->flatMap(function (Collection $roomEvents) use ($now) {
            $live = $roomEvents->where('running', true)->sortBy(fn ($e) => abs($e['start']->diffInMinutes($now)))->keys()->first();

            return $roomEvents->map(fn ($e, $k) => ['running' => $k === $live] + $e);
        })->sortBy(fn ($e) => [$e['running'] ? 0 : 1, $e['start']->timestamp])->values();

        $lessons = $events->map(fn (array $e) => $this->lessonView($e, $blockedTeacherIds, $recurringRoomIds, $recurrence));

        return [
            'sub' => 'Ближайшие две недели',
            'focus' => $lessons->first(),
            'next' => $lessons->skip(1)->values(),
        ];
    }

    private function lessonView(array $e, array $blockedTeacherIds, array $recurringRoomIds, Collection $schedules): array
    {
        $start = $e['start'];
        $facts = [];

        if ($e['room_type'] === 'group') {
            $facts[] = 'Групповое';
        }
        if ($e['owner']) {
            $facts[] = $e['owner'];
        }
        $repeat = $this->repeatLabel($schedules->get($e['id']), $start);
        if ($repeat) {
            $facts[] = $repeat;
        }

        $until = $e['running'] ? null : HumanDate::until($start);

        return [
            'title' => $start->format('H:i') . ' · ' . $e['title'],
            'facts' => implode(' · ', $facts),
            // Разовое занятие там, где есть регулярное, — исключение, выделяем
            'extra' => $e['type'] === 'once' && in_array($e['room_id'], $recurringRoomIds, true),
            'status' => $e['running'] ? 'Идёт сейчас' : ($until ? 'Начнётся ' . $until : null),
            'start' => $start,
            'isToday' => $start->isToday(),
            'running' => $e['running'],
            'blocked' => in_array($e['teacher_id'], $blockedTeacherIds, true),
            'joinUrl' => route('rooms.connect', $e['room_id']),
            'url' => url('/student/rooms/' . $e['room_id']),
        ];
    }

    /** «каждый четверг», «каждый день», «раз в месяц»; для разовых — null. */
    private function repeatLabel(?RoomSchedule $schedule, $start): ?string
    {
        return match ($schedule?->recurrence_type) {
            'daily' => 'каждый день',
            'weekly' => ['каждое воскресенье', 'каждый понедельник', 'каждый вторник', 'каждую среду',
                'каждый четверг', 'каждую пятницу', 'каждую субботу'][$start->dayOfWeek],
            'monthly' => 'раз в месяц',
            default => null,
        };
    }

    /** Прошедшие занятия: завершённые, где ученик — участник; с отметкой о пропуске, долге и записью. */
    private function past(User $student, ?int $teacherId): array
    {
        $since = today()->subWeeks($this->pastWeeks);

        $base = MeetingSession::query()
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereHas('room', fn ($q) => $q
                ->whereHas('participants', fn ($p) => $p->where('users.id', $student->id))
                ->when($teacherId, fn ($r) => $r->where('user_id', $teacherId)));

        $sessions = (clone $base)
            ->where('started_at', '>=', $since)
            ->with('room.user:id,name')
            ->orderByDesc('started_at')
            ->get();

        // Долги по конкретным занятиям: просроченная поурочная оплата
        $overdueSessionIds = PaymentRecord::overdue()
            ->where('student_id', $student->id)
            ->whereIn('meeting_session_id', $sessions->pluck('id'))
            ->pluck('meeting_session_id')
            ->all();

        // Записи с видео, которые ученик может открыть
        $recordings = Recording::forStudent($student)
            ->whereNotNull('s3_url')
            ->whereIn('meeting_id', $sessions->pluck('meeting_id')->filter()->unique())
            ->where('start_time', '>=', $since->copy()->subDay())
            ->get();

        $lessons = $sessions->map(function (MeetingSession $s) use ($student, $overdueSessionIds, $recordings) {
            $room = $s->room;
            $recording = $recordings->first(fn (Recording $r) => $r->belongsToSession($s));
            $attended = $s->attendedBy($student->id);

            return [
                'title' => $s->started_at->format('H:i') . ' · ' . ($room?->name ?? 'Занятие'),
                'facts' => $attended
                    ? implode(' · ', array_filter([$room?->type === 'group' ? 'Групповое' : null, $room?->user?->name]))
                    : 'Вы не были на занятии',
                'attended' => $attended,
                'start' => $s->started_at,
                'unpaid' => in_array($s->id, $overdueSessionIds, true),
                'recordingUrl' => match (true) {
                    ! $recording => null,
                    Route::has('cabinet.student.recordings') => route('cabinet.student.recordings', ['open' => $recording->id]),
                    default => url('/student/recordings/' . $recording->id),
                },
            ];
        });

        return [
            'sub' => 'Последние ' . plural_ru($this->pastWeeks, 'неделя', 'недели', 'недель'),
            'past' => $lessons,
            'hasEarlier' => (clone $base)->where('started_at', '<', $since)->exists(),
        ];
    }
}
