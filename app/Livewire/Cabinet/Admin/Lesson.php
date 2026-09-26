<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\MeetingSession;
use App\Models\Message;
use App\Models\Room;
use App\Models\RoomScheduleException;
use App\Services\AdminLessonsService;
use App\Services\LessonStopService;
use App\Services\TeacherScheduleService;
use App\Support\HumanDate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Админка · страница занятия (макет AdminLessons, страница занятия): кто занимается, проведённые, расписание, презентации.
 * Действия: подключиться, завершить занятие (учитель получает уведомление), в архив, вернуть из архива, удалить навсегда.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Занятие', 'active' => 'lessons'])]
class Lesson extends Component
{
    use AdminScreen;

    public int $roomId;

    /** Открытое подтверждение: stop | archive | delete. */
    public ?string $confirm = null;

    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(int|string $room): void
    {
        $this->authorizeAdmin();

        $model = ctype_digit((string) $room) ? Room::withTrashed()->find((int) $room) : null;
        abort_unless($model, 404);
        $this->roomId = $model->id;
    }

    private function room(): Room
    {
        $room = Room::withTrashed()
            ->with(['user', 'participants:id,name,first_name', 'schedules' => fn ($q) => $q->where('is_active', true)->with('exceptions')])
            ->find($this->roomId);
        abort_unless($room, 404);

        return $room;
    }

    public function ask(string $what): void
    {
        $room = $this->room();
        $allowed = match ($what) {
            'stop' => (bool) $room->is_running && ! $room->trashed(),
            'archive' => ! $room->trashed() && ! $room->is_running,
            'delete' => $room->trashed(),
            default => false,
        };
        $this->confirm = $allowed ? $what : null;
    }

    public function closeConfirm(): void
    {
        $this->confirm = null;
    }

    /** Завершить идущее занятие (как RoomController::stop), учитель получает уведомление. */
    public function stop(): void
    {
        $admin = $this->authorizeAdmin();
        $room = $this->room();
        $this->confirm = null;

        if (! $room->is_running) {
            $this->dispatch('toast', message: 'Занятие уже завершено');

            return;
        }

        try {
            app(LessonStopService::class)->stopByAdmin($room, $admin);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Не удалось завершить занятие. Попробуйте ещё раз.', tone: 'danger');

            return;
        }

        $this->dispatch('toast', message: 'Занятие завершено, ' . AdminLessonsService::firstName($room->user) . ' получит уведомление');
    }

    public function archive(): void
    {
        $this->authorizeAdmin();
        $room = $this->room();
        $this->confirm = null;

        if ($room->trashed() || $room->is_running) {
            return;
        }

        app(AdminLessonsService::class)->archive($room);
        $this->dispatch('toast', message: 'Занятие в архиве');
    }

    public function restore(): void
    {
        $this->authorizeAdmin();
        $room = $this->room();

        if (! $room->trashed()) {
            return;
        }

        app(AdminLessonsService::class)->restore($room);
        $this->dispatch('toast', message: 'Занятие вернулось из архива, время нужно назначить заново');
    }

    public function forceDelete(): void
    {
        $this->authorizeAdmin();
        $room = $this->room();
        abort_unless($room->trashed(), 422);

        app(AdminLessonsService::class)->forceDelete($room);

        session()->flash('toast', 'Занятие удалено навсегда');
        $this->redirect(route('cabinet.admin.lessons'));
    }

    public function render()
    {
        $service = app(AdminLessonsService::class);
        $room = $this->room();
        $teacher = $room->user;
        $state = $service->state($room);
        $running = $state === AdminLessonsService::LIVE ? app(LessonStopService::class)->runningSession($room) : null;
        $present = $state === AdminLessonsService::LIVE ? $service->present($room, $running) : [];
        $next = $state === AdminLessonsService::PLAN ? $service->nextOf($room) : null;
        $subscription = $teacher?->activeSubscription();

        $sessionsTotal = MeetingSession::where('room_id', $room->id)->where('status', 'completed')->count();
        $files = $service->presentations($room);
        $teacherUrl = AdminLessonsService::userUrl($teacher?->id);

        // Строка фактов: состояние · вид · учитель (ссылкой)
        $facts = collect([
            match ($state) {
                AdminLessonsService::LIVE => '<span class="font-semibold text-ink">Идёт сейчас' . ($running?->started_at ? ' · с ' . $running->started_at->format('H:i') : '') . '</span>',
                AdminLessonsService::PLAN => e('Ближайшее — ' . HumanDate::at($next['start'])),
                AdminLessonsService::NONE => 'Нет расписания',
                default => 'В архиве' . ($room->deleted_at ? ' с ' . e(HumanDate::date($room->deleted_at)) : ''),
            },
            e($service->kind($room)),
            $teacher ? ($teacherUrl ? '<a href="' . e($teacherUrl) . '" class="link">' . e($teacher->name) . '</a>' : e($teacher->name)) : null,
        ])->filter()->implode(' · ');

        $minutes = $running?->started_at ? max(1, (int) $running->started_at->diffInMinutes(now())) : null;
        $inClass = count($present);

        $data = [
            'room' => $room,
            'state' => $state,
            'facts' => new HtmlString($facts),
            'backUrl' => route('cabinet.admin.lessons'),
            'joinUrl' => $state === AdminLessonsService::LIVE ? route('rooms.connect', $room) : null,
            'liveLen' => $minutes ? 'идёт ' . plural_ru($minutes, 'минуту', 'минуты', 'минут') : null,
            'teacher' => $teacher,
            'teacherUrl' => $teacherUrl,
            'teacherNote' => 'Учитель · ' . match (true) {
                $state === AdminLessonsService::LIVE => 'ведёт занятие' . ($running?->started_at ? ' с ' . $running->started_at->format('H:i') : ''),
                (bool) $subscription?->tariff => $subscription->tariff->name . ($subscription->ends_at ? ' до ' . HumanDate::date($subscription->ends_at) : ''),
                default => 'без тарифа',
            },
            'people' => $room->participants->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'url' => AdminLessonsService::userUrl($u->id),
                'note' => $state === AdminLessonsService::LIVE ? (isset($present[$u->id]) ? 'В классе' . ($present[$u->id] ? ' с ' . $present[$u->id] : '') : 'Не в классе') : null,
                'away' => $state === AdminLessonsService::LIVE && ! isset($present[$u->id]),
            ]),
            'sessions' => $this->sessions($room),
            'sessionsTotal' => $sessionsTotal,
            'sessionsUrl' => route('cabinet.admin.lessons', ['tab' => 'sessions', 'lesson' => $room->id, 'period' => 'all']),
            'rules' => $state === AdminLessonsService::ARCHIVED ? collect() : $room->schedules->map(fn ($s) => ['rule' => $service->ruleLong($s), 'since' => $service->ruleSince($s)])->values(),
            'upcoming' => $state === AdminLessonsService::ARCHIVED ? collect() : $service->upcoming($room),
            'noRuleText' => $this->noRuleText($room, $state),
            'files' => $files,
        ];

        if ($this->confirm) {
            $teacherName = AdminLessonsService::firstName($teacher);
            $parts = collect([
                $sessionsTotal ? plural_ru($sessionsTotal, 'проведённое занятие', 'проведённых занятия', 'проведённых занятий') . ' со статистикой' : null,
                Message::where('room_id', $room->id)->exists() ? 'чат занятия' : null,
                $files->isNotEmpty() ? plural_ru($files->count(), 'презентация', 'презентации', 'презентаций') : null,
            ])->filter()->values()->all();

            $data['modal'] = [
                'teacher' => $teacherName,
                'inClass' => $inClass ? ' и у ' . plural_ru($inClass, 'ученика', 'учеников', 'учеников') . ' в классе' : '',
                'delWhat' => $parts ? 'Удалятся ' . TeacherScheduleService::joinAnd($parts) : 'Удалится само занятие',
            ];
        }

        return view('livewire.cabinet.admin.lesson', $data)->title($room->name);
    }

    /** Последние проведённые и отменённые занятия (до трёх). */
    private function sessions(Room $room)
    {
        $held = MeetingSession::where('room_id', $room->id)->where('status', 'completed')->whereNotNull('started_at')
            ->orderByDesc('started_at')->limit(3)->get()
            ->map(function (MeetingSession $s) {
                $att = TeacherScheduleService::attendance($s);

                return [
                    'key' => 's' . $s->id,
                    'at' => $s->started_at,
                    'when' => Str::ucfirst(HumanDate::at($s->started_at)),
                    'sub' => collect([
                        plural_ru(TeacherScheduleService::sessionMinutes($s), 'минута', 'минуты', 'минут'),
                        $att['total'] ? 'пришли ' . $att['attended'] . ' из ' . $att['total'] : null,
                    ])->filter()->implode(' · '),
                    'request' => (bool) $s->deletion_requested_at,
                    'url' => route('cabinet.admin.session', ['session' => $s->id]),
                ];
            });

        $cancelled = RoomScheduleException::where('room_id', $room->id)
            ->where('status', RoomScheduleException::STATUS_CANCELLED)
            ->where('original_starts_at', '<=', now())
            ->orderByDesc('original_starts_at')->limit(3)->get()
            ->map(fn (RoomScheduleException $e) => [
                'key' => 'c' . $e->id,
                'at' => $e->original_starts_at,
                'when' => Str::ucfirst(HumanDate::day($e->original_starts_at)),
                'sub' => 'Отменено учителем' . ($e->reason ? ' · «' . $e->reason . '»' : ''),
                'request' => false,
                'url' => null,
            ]);

        return $held->concat($cancelled)->sortByDesc(fn ($r) => $r['at']->timestamp)->take(3)->values();
    }

    private function noRuleText(Room $room, string $state): string
    {
        if ($state === AdminLessonsService::ARCHIVED) {
            return 'Расписание удалено при переносе в архив.';
        }

        $last = MeetingSession::where('room_id', $room->id)->where('status', 'completed')->max('started_at');

        return $last
            ? 'Расписания нет. Последнее занятие прошло ' . HumanDate::day(\Illuminate\Support\Carbon::parse($last)) . ' — время назначает учитель.'
            : 'Расписания нет — занятие создано ' . HumanDate::date($room->created_at ?? now()) . ', но время ещё не назначено.';
    }
}
