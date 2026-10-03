<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\Recording;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\User;
use App\Notifications\LessonCreatedByAdmin;
use App\Support\HumanDate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Занятия всех учителей для админки (раздел «Занятия», страница занятия, отчёт о проведённом).
 * Вхождения расписания — TeacherScheduleService (учитывает исключения серии), создание и архив — TeacherLessonService,
 * завершение — LessonStopService, удаление проведённых — SessionDeletionService.
 * Оплату занятий между учеником и учителем администратор не видит (решение владельца), поэтому здесь её нет.
 */
class AdminLessonsService
{
    public const LIVE = 'live';

    public const PLAN = 'plan';

    public const NONE = 'none';

    public const ARCHIVED = 'arch';

    private const DAYS_DATIVE = ['воскресеньям', 'понедельникам', 'вторникам', 'средам', 'четвергам', 'пятницам', 'субботам'];

    private const DAYS_SHORT = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];

    public function __construct(
        private TeacherScheduleService $schedule,
        private StudentScheduleService $occurrences,
    ) {}

    /*
     |--------------------------------------------------------------------------
     | Учителя
     |--------------------------------------------------------------------------
     */

    /** Все учителя по имени (id => имя): фильтры и окно «Создать занятие». */
    public function teacherOptions(): array
    {
        return User::where('role', User::ROLE_TUTOR)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Имя для текста «Мария получит уведомление». */
    public static function firstName(?User $user): string
    {
        return $user ? ($user->first_name ?: $user->name) : 'Учитель';
    }

    /** Ссылка на страницу пользователя в админке (если экран уже есть). */
    public static function userUrl(?int $id): ?string
    {
        return $id && Route::has('cabinet.admin.user') ? route('cabinet.admin.user', ['user' => $id]) : null;
    }

    /*
     |--------------------------------------------------------------------------
     | Список занятий
     |--------------------------------------------------------------------------
     */

    /** Поиск по названию занятия, имени учителя или ученика. */
    public static function searchRooms(Builder $query, string $search): Builder
    {
        $term = '%' . addcslashes(trim($search), '%_\\') . '%';

        return $query->where(fn (Builder $q) => $q
            ->where('rooms.name', 'like', $term)
            ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $term))
            ->orWhereHas('participants', fn (Builder $p) => $p->where('users.name', 'like', $term)));
    }

    private function roomsQuery(?int $teacherId, string $search): Builder
    {
        return Room::query()
            ->when($teacherId, fn ($q) => $q->where('user_id', $teacherId))
            ->when(trim($search) !== '', fn ($q) => self::searchRooms($q, $search))
            ->with(['user:id,name,first_name', 'participants:id,name,avatar']);
    }

    /**
     * Занятия для списка: идут сейчас → по расписанию (ближайшие раньше) → без расписания → в архиве.
     *
     * @return array{rows: Collection<int, array>, counts: array<string, int>, teachers: int, active: int}
     */
    public function rooms(?int $teacherId, string $search, string $filter = 'all', int $limit = 50): array
    {
        $active = $this->roomsQuery($teacherId, $search)
            ->with(['schedules' => fn ($q) => $q->where('is_active', true)->with('exceptions')])
            ->get();

        $running = MeetingSession::whereIn('room_id', $active->where('is_running', true)->pluck('id'))
            ->where('status', 'running')->orderByDesc('started_at')->get()->unique('room_id')->keyBy('room_id');
        $lastHeld = MeetingSession::whereIn('room_id', $active->pluck('id'))->where('status', 'completed')
            ->selectRaw('room_id, max(started_at) as last_at')->groupBy('room_id')->pluck('last_at', 'room_id');

        $rows = $active->map(fn (Room $room) => $this->row($room, $running->get($room->id), $lastHeld->get($room->id)));

        $archivedQuery = $this->roomsQuery($teacherId, $search)->onlyTrashed();
        $counts = [
            self::LIVE => $rows->where('state', self::LIVE)->count(),
            self::NONE => $rows->where('state', self::NONE)->count(),
            self::ARCHIVED => (clone $archivedQuery)->count(),
        ];

        $order = [self::LIVE => 0, self::PLAN => 1, self::NONE => 2];
        $rows = $rows->sortBy(fn (array $r) => sprintf('%d-%012d-%s', $order[$r['state']], $r['sort'], mb_strtolower($r['title'])))->values();

        if (in_array($filter, [self::LIVE, self::NONE], true)) {
            $rows = $rows->where('state', $filter)->values();
        } elseif ($filter === self::ARCHIVED) {
            $rows = collect();
        }

        if (in_array($filter, ['all', self::ARCHIVED], true) && $rows->count() < $limit) {
            $archived = $archivedQuery->orderByDesc('deleted_at')->limit($limit - $rows->count())->get()
                ->map(fn (Room $room) => $this->row($room, null, null));
            $rows = $rows->concat($archived);
        }

        $available = match ($filter) {
            self::ARCHIVED => $counts[self::ARCHIVED],
            'all' => $active->count() + $counts[self::ARCHIVED],
            default => $rows->count(),
        };

        return [
            'rows' => $rows->take($limit)->values(),
            'hasMore' => $available > $limit,
            'counts' => $counts,
            'teachers' => $active->pluck('user_id')->unique()->count(),
            'active' => $active->count(),
        ];
    }

    /** Строка списка занятий. */
    private function row(Room $room, ?MeetingSession $running, ?string $lastHeld): array
    {
        $participants = $room->participants;
        $next = $room->trashed() ? null : $this->nextOf($room);
        $state = match (true) {
            $room->trashed() => self::ARCHIVED,
            (bool) $room->is_running => self::LIVE,
            (bool) $next => self::PLAN,
            default => self::NONE,
        };

        $present = $state === self::LIVE ? count($this->present($room, $running)) : 0;
        $count = $participants->count();

        return [
            'id' => $room->id,
            'state' => $state,
            'sort' => match ($state) {
                self::LIVE => $running?->started_at?->timestamp ?? 0,
                self::PLAN => $next['start']->timestamp,
                default => 0,
            },
            'title' => $room->name,
            'rule' => $state === self::ARCHIVED ? $this->kind($room) : $this->ruleShort($room),
            'teacher' => $room->user?->name,
            'participants' => $participants->take(3)->values(),
            'count' => $count,
            'liveNote' => $state === self::LIVE
                ? collect([$running?->started_at ? 'с ' . $running->started_at->format('H:i') : null,
                    $count === 1 ? ($present ? 'ученик в классе' : 'ученика нет') : $present . ' из ' . $count . ' в классе'])->filter()->implode(' · ')
                : null,
            'when' => $next ? Str::ucfirst(HumanDate::at($next['start'])) : null,
            'whenNote' => match ($state) {
                self::PLAN => $this->nextNote($next),
                self::NONE => $lastHeld ? 'последнее ' . HumanDate::date(Carbon::parse($lastHeld)) : 'ещё не проводилось',
                self::ARCHIVED => $room->deleted_at ? 'с ' . HumanDate::date($room->deleted_at) : null,
                default => null,
            },
            'url' => route('cabinet.admin.lesson', ['room' => $room->id]),
            'joinUrl' => $state === self::LIVE ? route('rooms.connect', $room) : null,
        ];
    }

    /** Состояние занятия: идёт, по расписанию, без расписания, в архиве. */
    public function state(Room $room): string
    {
        return match (true) {
            $room->trashed() => self::ARCHIVED,
            (bool) $room->is_running => self::LIVE,
            $this->nextOf($room) !== null => self::PLAN,
            default => self::NONE,
        };
    }

    /**
     * Ближайшее вхождение с учётом исключений по уже загруженным правилам (или запросом).
     *
     * @return array{start: Carbon, duration: int, original: Carbon, exception: ?\App\Models\RoomScheduleException, schedule: RoomSchedule}|null
     */
    public function nextOf(Room $room): ?array
    {
        if ($room->trashed()) {
            return null;
        }

        $schedules = $room->relationLoaded('schedules')
            ? $room->schedules->where('is_active', true)
            : $room->schedules()->where('is_active', true)->with('exceptions')->get();

        $best = null;
        foreach ($schedules as $schedule) {
            $next = $schedule->nextOccurrenceDetails($schedule->relationLoaded('exceptions') ? $schedule->exceptions : null);
            if ($next && (! $best || $next['start']->lt($best['start']))) {
                $best = $next + ['schedule' => $schedule];
            }
        }

        return $best;
    }

    /** Подпись под ближайшим занятием: «через 25 минут», «перенесено с пятницы, 15:00», «каждую пятницу», «разовое». */
    private function nextNote(array $next): ?string
    {
        $until = $next['start']->isToday() ? HumanDate::until($next['start']) : null;

        return match (true) {
            (bool) $until => $until,
            (bool) $next['exception']?->isMoved() => 'перенесено ' . TeacherScheduleService::movedFromLabel($next['original']),
            $next['schedule']->type === 'once' => 'разовое',
            default => TeacherScheduleService::repeatLabel($next['schedule']),
        };
    }

    /** «Индивидуальное» / «Группа · 4 ученика». */
    public function kind(Room $room): string
    {
        $count = $room->participants->count();

        return $count > 1 || $room->type === 'group'
            ? 'Группа · ' . plural_ru($count, 'ученик', 'ученика', 'учеников')
            : 'Индивидуальное';
    }

    /** Коротко о расписании: «пн и чт в 15:00 · 60 минут», «разовое · 45 минут», «группа · создано 20 сентября». */
    public function ruleShort(Room $room): string
    {
        $schedules = ($room->relationLoaded('schedules') ? $room->schedules : $room->schedules()->get())->where('is_active', true)->values();

        if ($schedules->isEmpty()) {
            $group = $room->participants->count() > 1 || $room->type === 'group';

            return ($group ? 'группа' : 'индивидуальное') . ' · создано ' . HumanDate::date($room->created_at ?? now());
        }

        $parts = $schedules->map(function (RoomSchedule $s) {
            $time = $s->recurrence_time ? substr((string) $s->recurrence_time, 0, 5) : null;
            $days = collect($s->recurrence_days ?? [])->map(fn ($d) => (int) $d)->sortBy(fn ($d) => $d === 0 ? 7 : $d)->values();

            return match (true) {
                $s->type === 'once' => 'разовое',
                $s->recurrence_type === 'weekly' && $days->count() > 1 && $days->count() < 7
                    => TeacherScheduleService::joinAnd($days->map(fn ($d) => self::DAYS_SHORT[$d])->all()) . ' в ' . $time,
                default => trim(TeacherScheduleService::repeatLabel($s) . ($time ? ' в ' . $time : '')),
            };
        })->unique()->values();

        return $parts->implode(', ') . ' · ' . plural_ru($schedules->first()->minutes(), 'минута', 'минуты', 'минут');
    }

    /** Правило полностью: «По понедельникам и четвергам в 15:00 · 60 минут», «Каждый четверг в 16:00 · 60 минут». */
    public function ruleLong(RoomSchedule $s): string
    {
        $time = $s->recurrence_time ? substr((string) $s->recurrence_time, 0, 5) : null;
        $days = collect($s->recurrence_days ?? [])->map(fn ($d) => (int) $d)->sortBy(fn ($d) => $d === 0 ? 7 : $d)->values();
        $minutes = plural_ru($s->minutes(), 'минута', 'минуты', 'минут');

        $rule = match (true) {
            $s->type === 'once' => 'Разовое занятие' . ($s->scheduled_at ? ' ' . HumanDate::at($s->scheduled_at) : ''),
            $s->recurrence_type === 'weekly' && $days->count() > 1 && $days->count() < 7
                => 'По ' . TeacherScheduleService::joinAnd($days->map(fn ($d) => self::DAYS_DATIVE[$d])->all()) . ' в ' . $time,
            $s->recurrence_type === 'monthly' && $s->recurrence_day_of_month => 'Каждый месяц ' . $s->recurrence_day_of_month . '-го числа в ' . $time,
            default => Str::ucfirst(trim(TeacherScheduleService::repeatLabel($s) . ($time ? ' в ' . $time : ''))),
        };

        return $rule . ' · ' . $minutes;
    }

    /** «С 7 сентября, без даты окончания», «С 2 сентября до 31 мая», «Назначено 24 сентября». */
    public function ruleSince(RoomSchedule $s): string
    {
        if ($s->type === 'once') {
            return 'Назначено ' . HumanDate::date($s->created_at ?? now());
        }

        return 'С ' . HumanDate::date($s->start_date ?? $s->created_at ?? now())
            . ($s->end_date ? ' до ' . HumanDate::date($s->end_date) : ', без даты окончания');
    }

    /**
     * Ближайшие занятия по расписанию с исключениями: перенесённые — в новое время, отменённые — с причиной.
     *
     * @return Collection<int, array{when: string, note: ?string, cancelled: bool}>
     */
    public function upcoming(Room $room, int $count = 3): Collection
    {
        if ($room->trashed()) {
            return collect();
        }

        $schedules = $room->schedules()->where('is_active', true)->with('exceptions')->get()
            ->each(fn (RoomSchedule $s) => $s->setRelation('room', $room));

        return $this->occurrences->occurrences($schedules, today(), today()->addMonths(3)->endOfDay(), true)
            ->filter(fn (array $e) => $e['end']->isFuture())
            ->take($count)
            ->map(fn (array $e) => [
                'when' => Str::ucfirst(HumanDate::at($e['start'])),
                'cancelled' => $e['cancelled'],
                'note' => match (true) {
                    $e['cancelled'] => 'отменено' . ($e['reason'] ? ' · ' . $e['reason'] : ''),
                    (bool) $e['moved_from'] => 'перенесено ' . TeacherScheduleService::movedFromLabel($e['moved_from']),
                    $e['start']->isToday() && $e['start']->isFuture() => HumanDate::until($e['start']),
                    default => null,
                },
            ])
            ->values();
    }

    /**
     * Кто из учеников сейчас в классе: user_id => «15:04» (время входа). Источник — события класса (вебхуки).
     *
     * @return array<int, string>
     */
    public function present(Room $room, ?MeetingSession $running): array
    {
        $ids = $room->participants->pluck('id')->map(fn ($id) => (int) $id)->all();
        $present = [];

        foreach ($running?->analytics_data['participants'] ?? [] as $p) {
            $id = is_numeric($p['user_id'] ?? null) ? (int) $p['user_id'] : null;
            if (! $id || ! in_array($id, $ids, true)) {
                continue;
            }
            $joined = $p['last_joined_at'] ?? $p['joined_at'] ?? null;
            $left = $p['left_at'] ?? null;
            if ($left && (! $joined || Carbon::parse($joined)->lte(Carbon::parse($left)))) {
                continue;
            }
            $present[$id] = isset($p['joined_at']) ? Carbon::parse($p['joined_at'])->timezone(config('app.timezone'))->format('H:i') : '';
        }

        return $present;
    }

    /** Презентации занятия: имя, тип и размер (если известен), ссылка. */
    public function presentations(Room $room): Collection
    {
        return collect($room->presentations ?? [])->filter(fn ($p) => is_string($p) && $p !== '')->map(function (string $path) use ($room) {
            $name = TeacherLessonService::presentationName($room, $path);
            $ext = mb_strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION));
            $size = Cache::get('file_size_' . $path);

            try {
                $url = Storage::disk('s3')->url($path);
            } catch (\Throwable) {
                $url = null;
            }

            return [
                'name' => $name,
                'meta' => collect([$ext ?: null, $size ? self::size((int) $size) : null])->filter()->implode(' · '),
                'url' => $url,
            ];
        })->values();
    }

    /** «2,4 МБ», «180 КБ». */
    public static function size(int $bytes): string
    {
        return $bytes >= 1048576
            ? str_replace('.', ',', (string) round($bytes / 1048576, 1)) . ' МБ'
            : max(1, (int) round($bytes / 1024)) . ' КБ';
    }

    /*
     |--------------------------------------------------------------------------
     | Календарь (неделя, месяц): все учителя, цвет — по учителю
     |--------------------------------------------------------------------------
     */

    /**
     * Вхождения занятий всех учителей (или одного) в интервале, с отменёнными; к каждому добавлен teacher (User).
     *
     * @return Collection<int, array> см. TeacherScheduleService::describe()
     */
    public function calendar(?int $teacherId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $schedules = RoomSchedule::with(['room.user', 'room.participants:id,name,avatar', 'exceptions'])
            ->whereHas('room', fn ($q) => $q->when($teacherId, fn ($r) => $r->where('user_id', $teacherId)))
            ->where('is_active', true)
            ->get();

        $teacherIds = $schedules->pluck('room.user_id')
            ->merge(Room::where('is_running', true)->when($teacherId, fn ($q) => $q->where('user_id', $teacherId))->pluck('user_id'))
            ->filter()->unique()->values();
        $teachers = User::whereIn('id', $teacherIds)->get(['id', 'name', 'first_name'])->keyBy('id');

        return $teacherIds->flatMap(function ($id) use ($schedules, $from, $to, $teachers) {
            $own = $schedules->filter(fn (RoomSchedule $s) => (int) $s->room->user_id === (int) $id)->values();

            return $this->schedule->lessons((int) $id, $from, $to, $own, true)
                ->map(fn (array $l) => $l + ['teacher' => $teachers->get($id)]);
        })->sortBy(fn (array $l) => $l['start']->timestamp)->values();
    }

    /*
     |--------------------------------------------------------------------------
     | Проведённые занятия и записи
     |--------------------------------------------------------------------------
     */

    /** Начало периода фильтра: эта неделя, этот месяц или всё время. */
    public static function periodStart(string $period): ?Carbon
    {
        return match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => null,
        };
    }

    /** Проведённые (и идущие) занятия: сначала идущие, потом новые. */
    public function sessionsQuery(?int $teacherId, string $period, string $search, ?int $roomId = null): Builder
    {
        $from = self::periodStart($period);

        return MeetingSession::query()
            ->whereIn('status', ['running', 'completed'])
            ->whereNotNull('started_at')
            ->whereHas('room', fn ($q) => $q->withTrashed()
                ->when($teacherId, fn ($r) => $r->where('user_id', $teacherId))
                ->when(trim($search) !== '', fn ($r) => self::searchRooms($r, $search)))
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->when($from, fn ($q) => $q->where('started_at', '>=', $from))
            ->with(['room' => fn ($q) => $q->withTrashed()->with(['user:id,name,first_name', 'participants:id,name'])])
            ->orderByRaw("CASE WHEN status = 'running' THEN 0 ELSE 1 END")
            ->orderByDesc('started_at');
    }

    /** Записи: учитель, период, поиск; новые сверху. */
    public function recordingsQuery(?int $teacherId, string $period, string $search): Builder
    {
        $from = self::periodStart($period);

        return Recording::query()
            ->listed()
            ->when($teacherId, fn ($q) => $q->whereIn('meeting_id', Room::withTrashed()->where('user_id', $teacherId)->pluck('meeting_id')->filter()))
            ->when($from, fn ($q) => $q->where('start_time', '>=', $from))
            ->when(trim($search) !== '', fn ($q) => $q->search($search))
            ->with(['room' => fn ($q) => $q->withTrashed()->with('user:id,name,first_name')])
            ->orderByDesc('start_time');
    }

    /*
     |--------------------------------------------------------------------------
     | Действия
     |--------------------------------------------------------------------------
     */

    /**
     * Создать занятие за учителя: как будто его создал сам учитель (ученики получают «Новое занятие»),
     * учитель получает уведомление, что занятие создал администратор.
     *
     * @param  array<int>  $studentIds  только ученики этого учителя
     * @param  array<int, array>  $schedules  TeacherLessonService::scheduleAttributes()
     */
    public function create(User $teacher, string $name, array $studentIds, array $schedules, ?Carbon $first = null): Room
    {
        abort_unless($teacher->role === User::ROLE_TUTOR, 422);

        $room = app(TeacherLessonService::class)->create($teacher, trim($name), $studentIds, $schedules);
        $names = $room->participants->pluck('name');

        $teacher->notify(new LessonCreatedByAdmin($room, collect([
            $names->isNotEmpty() ? 'ученики — ' . $names->implode(', ') : null,
            $first ? 'первое — ' . TeacherLessonService::when($first) : null,
        ])->filter()->implode(' · ')));

        return $room;
    }

    /** В архив: расписание удаляется, проведённые занятия и записи остаются. */
    public function archive(Room $room): void
    {
        abort_if($room->trashed(), 422);

        app(TeacherLessonService::class)->archive($room, $room->user ?? auth()->user(), notify: false);
    }

    /** Вернуть из архива: расписания нет — время назначает учитель. */
    public function restore(Room $room): void
    {
        abort_unless($room->trashed(), 422);

        $room->restore();
    }

    /** Удалить навсегда (только из архива): проведённые занятия, чат и презентации; записи и задания остаются. */
    public function forceDelete(Room $room): void
    {
        abort_unless($room->trashed(), 422);

        $room->forceDelete();
    }
}
