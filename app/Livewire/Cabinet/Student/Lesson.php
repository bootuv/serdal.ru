<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\Homework;
use App\Models\Recording;
use App\Models\Room;
use App\Models\TeacherMaterial;
use App\Models\User;
use App\Services\HomeworkSubmissionService as Hw;
use App\Services\MessengerService;
use App\Services\PaymentRecordService;
use App\Services\StudentLessonService;
use App\Services\StudentScheduleService;
use App\Support\HumanDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Занятие ученика: ближайшее время (с отменой или переносом), «Войти в класс», задания, материалы, записи,
 * правила расписания и прошедшие занятия. Отдельного макета нет — по духу Lesson, StudentSchedule, StudentTask.
 * Открыть может только участник занятия, иначе 404.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Занятие', 'active' => 'schedule'])]
class Lesson extends Component
{
    private const PAST_STEP = 10;

    private const TASKS_LIMIT = 5;

    private const RECORDINGS_LIMIT = 5;

    public int $roomId;

    public int $pastLimit = self::PAST_STEP;

    /** Обновляем, когда учитель начинает или завершает занятие. */
    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(int|string $room): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_STUDENT, 403);

        $this->roomId = $this->room((int) $room)->id;
    }

    /** Занятие ученика (в том числе архивное). Участие проверяется на каждом запросе. */
    private function room(?int $id = null): Room
    {
        $room = app(StudentLessonService::class)->room(auth()->user(), $id ?? $this->roomId);
        abort_unless($room, 404);

        return $room;
    }

    public function showEarlier(): void
    {
        $this->pastLimit += self::PAST_STEP;
    }

    public function render()
    {
        $student = auth()->user();
        $room = $this->room();
        $service = app(StudentLessonService::class);

        $archived = $room->trashed();
        $running = ! $archived && (bool) $room->is_running;
        $nearest = $service->nearest($room);
        $blocked = PaymentRecordService::isBlockedForTeacher($student->id, $room->user_id);

        // Вход — к ближайшему неотменённому занятию
        $target = $nearest && $nearest['cancelled'] ? $nearest['following'] : $nearest;
        $canJoin = ! $archived && StudentScheduleService::canJoin($running, $target['start'] ?? null, $target['end'] ?? null);
        $past = $service->past($student, $room, $this->pastLimit);

        return view('livewire.cabinet.student.lesson', [
            'room' => $room,
            'sub' => $this->facts($room, $nearest, $running),
            'archived' => $archived,
            'running' => $running,
            'blocked' => $blocked,
            'canJoin' => $canJoin,
            'joinHint' => $archived || ! $target ? null : StudentScheduleService::joinOpensLabel($target['start']),
            // Пока вход не открыт, а занятие сегодня, — проверяем раз в минуту, чтобы кнопка появилась сама
            'poll' => ! $canJoin && ! $archived && $target && $target['start']->isToday(),
            'joinUrl' => route('rooms.connect', $room),
            'chatUrl' => MessengerService::url($student, $room->id),
            'backUrl' => route('cabinet.student.schedule'),
            'paymentsUrl' => Route::has('cabinet.student.payments')
                ? route('cabinet.student.payments', ['report' => $room->user_id])
                : url('/student/payment-debts'),
            'tasks' => $this->tasks($student, $room),
            'tasksUrl' => route('cabinet.student.tasks'),
            'rules' => $service->rules($room),
            'materials' => $this->materials($student, $room),
            'materialsUrl' => Route::has('cabinet.student.materials') ? route('cabinet.student.materials') : url('/student/materials'),
            'recordings' => $this->recordings($student, $room),
            'past' => $past['items']->map(fn (array $p) => $p + ['recordingUrl' => $p['recordingId'] ? $this->recordingUrl($p['recordingId']) : null]),
            'hasEarlier' => $past['hasMore'],
        ])->title($room->name);
    }

    /**
     * Строка фактов под названием: учитель · «сегодня в 16:00 · 60 минут» и пометки
     * (идёт сейчас, начнётся через…, перенесено, отменено с причиной). Срочное — жирным.
     */
    private function facts(Room $room, ?array $nearest, bool $running): HtmlString
    {
        $parts = [$room->user?->name, $room->type === 'group' ? 'Групповое' : null];
        $when = fn (array $o) => HumanDate::at($o['start']) . ' · ' . StudentLessonService::minutes((int) $o['start']->diffInMinutes($o['end']));

        $parts = array_merge($parts, match (true) {
            $room->trashed() => [['занятие в архиве']],
            $running => [['идёт сейчас']],
            ! $nearest => ['время пока не назначено'],
            $nearest['cancelled'] => [
                HumanDate::at($nearest['start']),
                ['отменено' . ($nearest['reason'] ? ': ' . $nearest['reason'] : '')],
                $nearest['following'] ? 'следующее — ' . HumanDate::at($nearest['following']['start']) : null,
            ],
            (bool) $nearest['moved_from'] => [$when($nearest), ['перенесено ' . \App\Services\TeacherScheduleService::movedFromLabel($nearest['moved_from'])]],
            $nearest['start']->isPast() => [$when($nearest), ['идёт по расписанию']],
            default => [$when($nearest), $nearest['start']->isToday() ? ['начнётся ' . HumanDate::until($nearest['start'])] : null],
        });

        return new HtmlString(collect($parts)->filter()->map(fn ($p) => is_array($p)
            ? '<span class="font-semibold text-ink">' . e($p[0]) . '</span>'
            : e($p))->implode(' · '));
    }

    /** Задания этого занятия: сначала те, что нужно сдать (ближайший срок первым), затем сданные. */
    private function tasks(User $student, Room $room): Collection
    {
        $order = [Hw::STATE_REVISION => 0, Hw::STATE_OVERDUE => 1, Hw::STATE_TODO => 2, Hw::STATE_REVIEW => 3, Hw::STATE_GRADED => 4];

        return Hw::visibleTo($student->id)
            ->where('room_id', $room->id)
            ->with([
                'submissions' => fn ($q) => $q->where('student_id', $student->id),
                'teacher:id,name',
                'room' => fn ($q) => $q->withTrashed()->select('id', 'name'),
            ])
            ->get()
            ->map(fn (Homework $h) => Tasks::item($h, withRoom: false))
            ->sortBy(fn (array $t) => [$order[$t['state']] ?? 9, $t['deadline'] === null ? 1 : 0])
            ->take(self::TASKS_LIMIT)
            ->values();
    }

    /** Материалы, которые учитель открыл этому занятию. */
    private function materials(User $student, Room $room): Collection
    {
        return TeacherMaterial::query()
            ->visibleToStudent($student)
            ->where('visibility', TeacherMaterial::VISIBILITY_ROOMS)
            ->whereHas('rooms', fn ($q) => $q->where('rooms.id', $room->id))
            ->orderBy('title')
            ->get()
            ->map(fn (TeacherMaterial $m) => [
                'folder' => false,
                'title' => $m->title ?: $m->original_name,
                'file' => $m->original_name ?: $m->file_path,
                'thumb' => $m->thumbnail_url,
                'meta' => implode(' · ', array_filter([$m->created_at ? 'Добавлено ' . HumanDate::date($m->created_at) : null, $m->file_size ? $m->formatted_size : null])),
                'href' => $m->file_url,
            ]);
    }

    /** Записи этого занятия, как на странице «Записи»: только занятия, где ученик — участник. */
    private function recordings(User $student, Room $room): Collection
    {
        if (! $room->meeting_id) {
            return collect();
        }

        return Recording::forStudent($student)
            ->listed()
            ->where('meeting_id', $room->meeting_id)
            ->orderByDesc('start_time')
            ->limit(self::RECORDINGS_LIMIT)
            ->get()
            ->map(function (Recording $r) {
                $start = $r->start_time ?? $r->created_at;
                $minutes = $r->start_time && $r->end_time ? (int) round($r->start_time->diffInMinutes($r->end_time)) : null;

                return [
                    'id' => $r->id,
                    'title' => $start ? Str::ucfirst(HumanDate::at($start)) : 'Запись занятия',
                    'sub' => $minutes ? StudentLessonService::minutes($minutes) : null,
                    'status' => $r->status(),
                    // Видео в хранилище — смотрим в «Записях»; пока не перенесено — на сервере занятий
                    'url' => $r->s3_url ? $this->recordingUrl($r->id) : ($r->url ?: null),
                    'external' => ! $r->s3_url && $r->url,
                ];
            });
    }

    private function recordingUrl(int $id): string
    {
        return Route::has('cabinet.student.recordings')
            ? route('cabinet.student.recordings', ['open' => $id])
            : url('/student/recordings/' . $id);
    }
}
