<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Homework;
use App\Models\HomeworkActivity;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Services\HomeworkSubmissionService as Hw;
use App\Support\HumanDate;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Задания учителя: работы на проверку, выданные, все. Макет: «Учитель · Задания» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Задания', 'active' => 'tasks'])]
class Tasks extends Component
{
    use TeacherScreen;

    private const PAGE = 30;

    /** Колонки заданий для строк списка (без описания и файлов). */
    private const COLUMNS = ['homeworks.id', 'homeworks.title', 'homeworks.deadline', 'homeworks.is_visible', 'homeworks.room_id', 'homeworks.max_score', 'homeworks.created_at'];

    /** Вкладка: review — нужно проверить, issued — выданные, all — все. */
    #[Url(except: 'review')]
    public string $tab = 'review';

    /** Фильтры вкладки «Все»: ученик, занятие, поиск по названию. */
    #[Url(as: 'student', except: '')]
    public string $studentId = '';

    #[Url(as: 'lesson', except: '')]
    public string $roomId = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Сколько строк показано в длинных списках. */
    public int $shown = self::PAGE;

    public function mount(): void
    {
        $this->authorizeTeacher();

        if (! in_array($this->tab, ['review', 'issued', 'all'], true)) {
            $this->tab = 'review';
        }

        // Сообщение после сохранения задания на соседнем экране
        if ($message = session('toast')) {
            $this->dispatch('toast', message: $message);
        }
    }

    public function updatedTab(): void
    {
        $this->shown = self::PAGE;
    }

    public function updatedStudentId(): void
    {
        $this->shown = self::PAGE;
    }

    public function updatedRoomId(): void
    {
        $this->shown = self::PAGE;
    }

    public function updatedSearch(): void
    {
        $this->shown = self::PAGE;
    }

    public function showMore(): void
    {
        $this->shown += self::PAGE;
    }

    /**
     * Списки грузим по частям: очередь проверки — первые $shown работ, «Выданные» — только незакрытые задания
     * (порядок считаем по числам из withProgress, без загрузки работ), «Все» — постранично в базе.
     * Учеников и работы подгружаем только для показанных строк.
     */
    public function render()
    {
        $teacherId = auth()->id();
        $mine = fn () => Homework::query()->where('teacher_id', $teacherId);

        $reviewCount = Hw::toReview($teacherId)->count();

        $review = collect();
        $waiting = collect();
        $list = collect();
        $listMore = 0;

        if ($this->tab === 'review') {
            $review = Hw::toReview($teacherId)
                ->with(['student:id,name,avatar', 'homework:id,title,deadline,room_id', 'homework.room:id,name,type'])
                ->withExists(['activities as resubmitted' => fn ($q) => $q->where('type', HomeworkActivity::TYPE_RESUBMITTED)])
                ->limit($this->shown)
                ->get()
                ->values()
                ->map(fn (HomeworkSubmission $s, int $i) => $this->reviewRow($s, $i === 0));
            $waiting = $this->waiting($teacherId);
        } elseif ($this->tab === 'issued') {
            $issued = Hw::notDone(Hw::withProgress($mine()->select(self::COLUMNS)))
                ->get()
                ->sortBy(fn (Homework $h) => $this->sortKey($h))
                ->values();
            $listMore = max(0, $issued->count() - $this->shown);
            $list = $this->rows($issued->take($this->shown));
        } else {
            $query = $this->filtered($mine());
            $listMore = max(0, (clone $query)->count() - $this->shown);
            $list = $this->rows($query->latest()->latest('id')->limit($this->shown)->get(self::COLUMNS));
        }

        return view('livewire.cabinet.teacher.tasks', [
            'reviewCount' => $reviewCount,
            'review' => $review,
            'reviewMore' => max(0, $reviewCount - $this->shown),
            'waiting' => $waiting,
            'list' => $list,
            'listMore' => $listMore,
            'students' => $this->tab === 'all' ? $this->studentOptions($teacherId) : [],
            'rooms' => $this->tab === 'all' ? $this->roomOptions($teacherId) : [],
            'filtered' => $this->studentId !== '' || $this->roomId !== '' || trim($this->search) !== '',
            'hasAny' => $mine()->exists(),
            'newUrl' => route('cabinet.teacher.task-new'),
        ]);
    }

    /** «Все» с фильтрами: ученик, занятие, название. */
    private function filtered($query)
    {
        $needle = trim($this->search);

        return $query
            ->when($this->studentId !== '', fn ($q) => $q->whereHas('students', fn ($s) => $s->where('users.id', (int) $this->studentId)))
            ->when($this->roomId !== '', fn ($q) => $q->where('room_id', (int) $this->roomId))
            ->when($needle !== '', fn ($q) => $q->where(function ($w) use ($needle) {
                // SQLite сравнивает без учёта регистра только латиницу — ищем и «как ввели», и строчными, и с заглавной
                $lower = mb_strtolower($needle);
                $variants = array_unique([$needle, $lower, mb_strtoupper(mb_substr($lower, 0, 1)) . mb_substr($lower, 1)]);
                foreach ($variants as $v) {
                    $w->orWhere('title', 'like', '%' . addcslashes($v, '%_\\') . '%');
                }
            }));
    }

    /** Строки для показанных заданий: подгружаем учеников, занятие и работы только для них. */
    private function rows(Collection $homeworks): Collection
    {
        $homeworks = new \Illuminate\Database\Eloquent\Collection($homeworks->all());
        $homeworks->load([
            'students:id,name,avatar',
            'submissions:id,homework_id,student_id,status,grade,submitted_at,updated_at',
            'room:id,name,type',
        ]);

        return $homeworks->map(fn (Homework $h) => $this->taskRow($h))->values();
    }

    /**
     * Порядок во «Выданных» по числам withProgress: ждём сдачи (по сроку), на проверке, на доработке, просрочено, черновики.
     */
    private function sortKey(Homework $h): array
    {
        $total = (int) $h->students_count;
        $deadline = $h->deadline?->timestamp ?? PHP_INT_MAX;

        return match (true) {
            ! $h->is_visible => [4, 0, -$h->id],
            $total !== 1 => [$h->is_overdue ? 3 : 0, $deadline, -$h->id],
            $h->submitted_count === 0 => $h->is_overdue ? [3, 0, -$h->id] : [0, $deadline, -$h->id],
            $h->revision_count > 0 => [2, 0, -$h->id],
            $h->graded_count > 0 => [5, 0, -$h->id],
            default => [1, 0, -$h->id],
        };
    }

    /** Строка работы на проверку. first — самая давняя, с кнопкой «Проверить». */
    private function reviewRow(HomeworkSubmission $s, bool $first): array
    {
        $h = $s->homework;
        $late = $h->deadline && $s->submitted_at->gt($h->deadline);
        $days = (int) $s->submitted_at->copy()->startOfDay()->diffInDays(today(), true);

        $sent = match (true) {
            (bool) $s->resubmitted => 'исправлено ' . HumanDate::day($s->submitted_at),
            $late => 'сдано ' . HumanDate::day($s->submitted_at) . ', срок был до ' . HumanDate::date($h->deadline),
            default => 'сдано ' . HumanDate::day($s->submitted_at),
        };

        return [
            'id' => $s->id,
            'title' => $h->title,
            'student' => $s->student?->name ?? 'Ученик',
            'studentId' => $s->student_id,
            'studentPhoto' => $s->student?->photoThumb(),
            'sub' => implode(' · ', array_filter([
                $s->student?->name,
                $h->room?->type === 'group' ? 'группа «' . $h->room->name . '»' : null,
                $sent,
            ])),
            'wait' => $first && $days >= 1 ? 'ждёт ' . plural_ru($days, 'день', 'дня', 'дней') : null,
            'overdue' => $days >= HomeworkSubmission::REVIEW_OVERDUE_DAYS,
            'badge' => match (true) {
                (bool) $s->resubmitted => ['neutral', 'Пересдано'],
                $late => ['danger', 'Позже срока'],
                default => null,
            },
            'url' => route('cabinet.teacher.review', $s),
        ];
    }

    /**
     * Строка задания: кому, срок, прогресс или состояние работы.
     * Ведёт на экран задания; работа одного ученика, которую уже сдали, — сразу на проверку; черновик — в редактор.
     */
    private function taskRow(Homework $h): array
    {
        $students = $h->students;
        $total = $students->count();
        $subs = $h->submissions->whereIn('student_id', $students->pluck('id'));
        $submitted = $subs->whereNotNull('submitted_at')->count();
        $graded = $subs->whereNotNull('grade')->count();
        $group = $total > 1;
        $done = $h->is_visible && $total > 0 && $graded === $total;
        $single = $group ? null : $subs->first();

        $who = match (true) {
            $total === 0 => 'Ученики не выбраны',
            $group && $h->room?->type === 'group' => 'Группа «' . $h->room->name . '»',
            $group => plural_ru($total, 'ученик', 'ученика', 'учеников'),
            default => $students->first()->name,
        };

        $row = [
            'id' => $h->id,
            'title' => $h->title,
            'who' => $who,
            'group' => $group,
            'avatarId' => $group ? 0 : (int) $students->first()?->id,
            'photo' => $group ? null : $students->first()?->photoThumb(),
            'due' => $h->deadline ? 'до ' . HumanDate::date($h->deadline) : null,
            'dueEm' => null,
            'prog' => null,
            'badge' => null,
            'done' => $done,
            'url' => route('cabinet.teacher.task', $h),
        ];

        if (! $h->is_visible) {
            return array_merge($row, [
                'due' => $group ? 'ученики пока не видят' : 'пока не видно ученику',
                'badge' => ['neutral', 'Черновик'],
                'url' => route('cabinet.teacher.task-new', ['edit' => $h->id]),
            ]);
        }

        if ($group || $total === 0) {
            $row['prog'] = $done ? ['проверено', "{$graded} из {$total}"] : ['сдали', "{$submitted} из {$total}"];
            if (! $done && $h->deadline) {
                $row = array_merge($row, $this->deadline($h, $submitted < $total));
            }

            return $row;
        }

        $state = Hw::state($h, $single);
        $row['url'] = $single?->submitted_at ? route('cabinet.teacher.review', $single) : $row['url'];

        return match ($state) {
            Hw::STATE_GRADED => array_merge($row, [
                'due' => 'сдано ' . HumanDate::date($single->submitted_at),
                'badge' => ['ok', 'Оценка ' . $h->formatGrade($single->grade)],
            ]),
            Hw::STATE_REVIEW => array_merge($row, ['badge' => ['neutral', 'На проверке']]),
            Hw::STATE_REVISION => array_merge($row, ['badge' => ['danger', 'На доработке']]),
            Hw::STATE_OVERDUE => array_merge($row, $this->deadline($h, true), ['badge' => ['danger', 'Просрочено']]),
            default => array_merge($row, $h->deadline ? $this->deadline($h, true) : [], [
                'badge' => ['neutral', 'Ещё не сдано'],
            ]),
        };
    }

    /** Срок: прошедший или близкий (3 дня) — жирным, дальний — обычным текстом. */
    private function deadline(Homework $h, bool $pending): array
    {
        $d = $h->deadline;

        if ($pending && $d->isPast()) {
            return ['due' => null, 'dueEm' => 'срок был ' . HumanDate::date($d)];
        }

        $text = today()->diffInDays($d->copy()->startOfDay()) <= 1
            ? 'до ' . HumanDate::day($d) . ', ' . $d->format('H:i')
            : 'до ' . HumanDate::day($d);

        return $pending && $d->lte(now()->addDays(3))
            ? ['due' => null, 'dueEm' => $text]
            : ['due' => $text, 'dueEm' => null];
    }

    /** «Ждём от учеников»: работы на доработке, затем опубликованные задания, которые ещё не сдали (по сроку). Пять строк. */
    private function waiting(int $teacherId): Collection
    {
        $revisions = HomeworkSubmission::query()
            ->where('status', HomeworkSubmission::STATUS_REVISION_REQUESTED)
            ->whereHas('homework', fn ($q) => $q->where('teacher_id', $teacherId)->where('is_visible', true))
            ->with(['homework:id,title', 'student:id,name'])
            ->orderBy('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (HomeworkSubmission $s) => [
                'title' => $s->homework->title,
                'sub' => trim(($s->student?->name ?? 'Ученик') . ' · вернули ' . HumanDate::date($s->updated_at)),
                'em' => null,
                'badge' => ['danger', 'На доработке'],
                'url' => route('cabinet.teacher.review', $s),
            ]);

        $left = 5 - $revisions->count();
        $pending = $left <= 0 ? collect() : Hw::awaitingSubmissions(Homework::query()->where('teacher_id', $teacherId))
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>', now()))
            ->orderByRaw('deadline is null, deadline asc')
            ->latest('id')
            ->limit($left)
            ->get(self::COLUMNS);

        $pending = $this->rows($pending)->map(fn (array $row) => [
            'title' => $row['title'],
            'sub' => $row['who'] . ($row['due'] ? ' · ' . $row['due'] : ''),
            'em' => $row['dueEm'],
            'progress' => $row['group'] ? 'сдали ' . $row['prog'][1] : null,
            'badge' => null,
            'url' => $row['url'],
        ]);

        return $revisions->concat($pending)->values();
    }

    /** Ученики для фильтра вкладки «Все»: кому учитель выдавал задания. */
    private function studentOptions(int $teacherId): array
    {
        return User::query()
            ->whereHas('assignedHomeworks', fn ($q) => $q->where('teacher_id', $teacherId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    /** Занятия для фильтра вкладки «Все»: те, к которым привязаны задания. */
    private function roomOptions(int $teacherId): array
    {
        return \App\Models\Room::withTrashed()
            ->whereIn('id', Homework::query()->where('teacher_id', $teacherId)->whereNotNull('room_id')->select('room_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }
}
