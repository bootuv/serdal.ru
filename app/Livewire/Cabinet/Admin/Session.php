<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Livewire\Cabinet\Admin\Concerns\DecidesDeletions;
use App\Models\MeetingSession;
use App\Models\Message;
use App\Models\Recording;
use App\Services\AdminLessonsService;
use App\Services\LessonActivityService;
use App\Services\TeacherScheduleService;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Админка · отчёт о проведённом занятии (макет AdminSessions, отчёт): итог, ученики и их активность, ход занятия.
 * При запросе учителя на удаление — карточка запроса и решение; без запроса — «Удалить занятие» (учитель получает уведомление).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Отчёт о занятии', 'active' => 'lessons'])]
class Session extends Component
{
    use AdminScreen, DecidesDeletions;

    public int $sessionId;

    /** Был ли у занятия запрос на удаление при открытии: куда вернуться после решения. */
    public bool $fromRequest = false;

    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(int|string $session): void
    {
        $this->authorizeAdmin();

        $model = ctype_digit((string) $session) ? MeetingSession::find((int) $session) : null;
        abort_unless($model, 404);
        $this->sessionId = $model->id;
        $this->fromRequest = (bool) $model->deletion_requested_at;
    }

    private function session(): MeetingSession
    {
        $session = MeetingSession::with(['room' => fn ($q) => $q->withTrashed()->with(['user', 'participants:id,name'])])->find($this->sessionId);
        abort_unless($session, 404);

        return $session;
    }

    protected function afterDecision(string $message): void
    {
        session()->flash('toast', $message);
        $this->redirect(route('cabinet.admin.lessons', ['tab' => $this->fromRequest ? 'deletions' : 'sessions']));
    }

    public function render()
    {
        $s = $this->session();

        // Занятие удалили в другой вкладке после решения по запросу
        if (! $s->room) {
            abort(404);
        }

        $room = $s->room;
        $teacher = $room->user;
        $live = $s->status === 'running';
        $end = $live ? now() : ($s->ended_at ?? $s->started_at);
        $minutes = $live ? max(1, (int) $s->started_at->diffInMinutes(now())) : TeacherScheduleService::sessionMinutes($s);
        $att = TeacherScheduleService::attendance($s);
        $raw = collect($s->analytics_data['participants'] ?? [])->filter(fn ($p) => is_array($p) && isset($p['user_id']))->keyBy(fn ($p) => (string) $p['user_id']);
        $activity = LessonActivityService::participants($s);
        $polls = LessonActivityService::polls($s);
        $chat = Message::where('room_id', $room->id)->whereBetween('created_at', [$s->started_at, $end])->count();
        $service = app(AdminLessonsService::class);
        $teacherUrl = AdminLessonsService::userUrl($teacher?->id);
        $recording = $live ? null : Recording::where('meeting_id', $s->meeting_id)->listed()->get()->first(fn (Recording $r) => $r->belongsToSession($s));

        $facts = collect([
            $live
                ? '<span class="font-semibold text-ink">Идёт сейчас · с ' . $s->started_at->format('H:i') . '</span>'
                : e(Str::ucfirst(HumanDate::day($s->started_at)) . ', ' . $s->started_at->format('H:i') . '–' . $end->format('H:i')),
            e($service->kind($room)),
            $teacher ? ($teacherUrl ? '<a href="' . e($teacherUrl) . '" class="link">' . e($teacher->name) . '</a>' : e($teacher->name)) : null,
            '<a href="' . e(route('cabinet.admin.lesson', ['room' => $room->id])) . '" class="link">Страница занятия</a>',
        ])->filter()->implode(' · ');

        $students = $att['students']->map(function (array $st) use ($raw, $activity, $live) {
            $a = $activity->get((string) $st['id']);
            $a = $a && $a['minutes'] > 0 ? $a : null; // без событий класса показываем прочерки
            $p = $raw->get((string) $st['id'], []);
            $came = $st['attended'] || ($live && isset($p['joined_at']));

            return [
                'id' => $st['id'],
                'name' => $st['name'],
                'came' => $came,
                'inRoom' => $a ? $a['minutes'] . ' мин' : '—',
                'mic' => $a && $a['talk'] ? $a['talk'] . ' мин' : '—',
                'cam' => $a && $a['camera'] ? $a['camera'] . ' мин' : '—',
                'msg' => (string) (int) ($p['message_count'] ?? 0),
                'react' => (string) (int) ($p['emoji_count'] ?? 0),
                'hand' => (string) (int) ($p['raise_hand_count'] ?? 0),
                'score' => $a ? $a['score'] . ' из ' . LessonActivityService::MAX_SCORE : '—',
            ];
        });
        $cameCount = $students->where('came', true)->count();

        return view('livewire.cabinet.admin.session', [
            'room' => $room,
            'live' => $live,
            'facts' => new HtmlString($facts),
            'backUrl' => route('cabinet.admin.lessons', ['tab' => $s->deletion_requested_at ? 'deletions' : 'sessions']),
            'backLabel' => $s->deletion_requested_at ? 'Запросы на удаление' : 'Проведённые занятия',
            'recordingUrl' => $recording && $recording->status() !== 'processing' ? route('cabinet.admin.lessons', ['tab' => 'recordings', 'open' => $recording->id, 'period' => 'all']) : null,
            'canDelete' => ! $live && ! $s->deletion_requested_at,
            'request' => $s->deletion_requested_at ? [
                'title' => AdminLessonsService::firstName($teacher) . ' просит удалить это занятие',
                'sent' => 'Запрос отправлен ' . HumanDate::at($s->deletion_requested_at),
                'reason' => $s->deletion_reason,
            ] : null,
            'stats' => [
                ['value' => $s->started_at->format('H:i'), 'label' => 'начало'],
                ['value' => $live ? '—' : $end->format('H:i'), 'label' => 'конец'],
                ['value' => $minutes . ' мин', 'label' => $live ? 'идёт' : 'длительность'],
                ['value' => $att['total'] ? $cameCount . ' из ' . $att['total'] : '—', 'label' => $live ? 'в классе' : 'пришли'],
                ['value' => (string) $polls, 'label' => plural_ru($polls, 'голосование', 'голосования', 'голосований', false)],
                ['value' => (string) $chat, 'label' => plural_ru($chat, 'сообщение', 'сообщения', 'сообщений', false) . ' в чате'],
            ],
            'students' => $students,
            'teacherNote' => $teacher
                ? 'Учитель ' . $teacher->name . ($live ? ' ведёт занятие с ' . $s->started_at->format('H:i') : ': занятие шло ' . plural_ru($minutes, 'минуту', 'минуты', 'минут')) . '.'
                : null,
            'feed' => $this->feed($s, $live, $end, $raw, $teacher?->id),
            'sessionId' => $s->id,
        ] + $this->decisionView())->title($room->name);
    }

    /**
     * Ход занятия по событиям класса: начало, входы, голосования, ранние выходы, конец.
     *
     * @return Collection<int, array{time: string, text: string}>
     */
    private function feed(MeetingSession $s, bool $live, Carbon $end, Collection $raw, ?int $teacherId): Collection
    {
        $tz = config('app.timezone');
        $at = fn (?string $v) => $v ? rescue(fn () => Carbon::parse($v)->timezone($tz), null, false) : null;
        $events = collect([['at' => $s->started_at, 'text' => 'Занятие началось']]);

        $students = $raw->reject(fn ($p) => is_numeric($p['user_id']) && (int) $p['user_id'] === $teacherId);

        $students->groupBy(fn ($p) => ($t = $at($p['joined_at'] ?? null)) ? $t->format('H:i') : '')
            ->reject(fn ($g, $k) => $k === '')
            ->each(function (Collection $group) use (&$events, $at) {
                $events->push([
                    'at' => $at($group->first()['joined_at']),
                    'text' => 'Вход в класс: ' . $group->pluck('full_name')->filter()->implode(', '),
                ]);
            });

        foreach ($s->analytics_data['timeline'] ?? [] as $t) {
            if (($t['type'] ?? null) === 'poll' && ($time = $at($t['timestamp'] ?? null))) {
                $events->push(['at' => $time, 'text' => 'Голосование']);
            }
        }

        if (! $live) {
            foreach ($students as $p) {
                $left = $at($p['left_at'] ?? null);
                $rejoined = $at($p['last_joined_at'] ?? null);
                if ($left && (! $rejoined || $left->gte($rejoined)) && $left->lt($end->copy()->subMinutes(5))) {
                    $events->push(['at' => $left, 'text' => 'Выход из класса: ' . ($p['full_name'] ?? 'ученик')]);
                }
            }
            $events->push(['at' => $end, 'text' => 'Занятие завершено']);
        }

        $events = $events->filter(fn ($e) => $e['at'])->sortBy(fn ($e) => $e['at']->timestamp)->values();

        if ($live) {
            $events->push(['at' => now(), 'text' => 'Идёт сейчас']);
        }

        return $events->map(fn ($e) => ['time' => $e['at']->format('H:i'), 'text' => $e['text']]);
    }
}
