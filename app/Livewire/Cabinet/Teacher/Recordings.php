<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Recording;
use App\Services\RecordingStorageService;
use App\Services\TeacherRecordingsService;
use App\Support\HumanDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Записи занятий учителя. Макет: «Учитель · Записи» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Записи', 'active' => 'recordings'])]
class Recordings extends Component
{
    use TeacherScreen;

    /** За сколько дней до удаления запись попадает в «Скоро удалятся». */
    private const SOON_DAYS = 7;

    private const PAGE = 60;

    /** Открытая в плеере запись. */
    #[Url(as: 'open')]
    public ?int $open = null;

    /** Ученик, чьи записи показаны ('' — все). */
    #[Url(except: '')]
    public string $student = '';

    /** Поиск по названию занятия и имени ученика. */
    #[Url(except: '')]
    public string $search = '';

    public int $limit = self::PAGE;

    /** Режим «Выбрать» и выбранные записи. */
    public bool $selecting = false;

    public array $picked = [];

    public bool $confirmDelete = false;

    /** Запись загрузилась в хранилище или появилась новая. */
    #[On('echo:recordings,.recording.updated')]
    public function refreshRecordings(): void {}

    public function mount(): void
    {
        $teacher = $this->authorizeTeacher();

        if ($this->open) {
            $this->playable($this->open);
        }

        app(TeacherRecordingsService::class)->syncInBackground($teacher);
    }

    public function play(int $id): void
    {
        $this->playable($id);
        $this->open = $id;
    }

    public function close(): void
    {
        $this->open = null;
    }

    public function updatedStudent(): void
    {
        $this->limit = self::PAGE;
    }

    public function updatedSearch(): void
    {
        $this->limit = self::PAGE;
    }

    public function showMore(): void
    {
        $this->limit += self::PAGE;
    }

    public function startSelect(): void
    {
        $this->selecting = true;
        $this->picked = [];
        $this->open = null;
    }

    public function cancelSelect(): void
    {
        $this->selecting = false;
        $this->picked = [];
        $this->confirmDelete = false;
    }

    public function toggle(int $id): void
    {
        if (! in_array($id, $this->picked, true) && ! Recording::forTeacher(auth()->user())->whereKey($id)->exists()) {
            return;
        }

        $this->picked = in_array($id, $this->picked, true)
            ? array_values(array_diff($this->picked, [$id]))
            : [...$this->picked, $id];
    }

    public function askDelete(): void
    {
        $this->confirmDelete = $this->picked !== [];
    }

    public function deleteSelected(): void
    {
        $deleted = app(TeacherRecordingsService::class)->delete(auth()->user(), $this->picked);

        $this->cancelSelect();
        $this->dispatch('toast', message: 'Удалено: ' . plural_ru($deleted, 'запись', 'записи', 'записей'));
    }

    /** Запись учителя с видео — её можно открыть в плеере. */
    private function playable(int $id): Recording
    {
        $recording = Recording::forTeacher(auth()->user())->whereKey($id)->first();
        abort_unless($recording && $recording->s3_url, 404);

        return $recording;
    }

    public function render()
    {
        $teacher = auth()->user();
        $storage = app(RecordingStorageService::class);
        $retention = $storage->retentionDays($teacher);

        $students = $teacher->students()->orderBy('name')->get(['users.id', 'users.name']);
        $studentId = $students->contains('id', (int) $this->student) ? (int) $this->student : null;

        $searching = filled(trim($this->search));
        $query = Recording::forTeacher($teacher)
            ->listed()
            ->when($studentId, fn ($q) => $q->whereHas('room.participants', fn ($p) => $p->where('users.id', $studentId)))
            ->when($searching, fn ($q) => $q->search($this->search));

        // «Скоро удалятся» — отдельным запросом по сроку: удаляются самые старые, а список ниже — последние записи
        $soonRecords = $retention === null ? collect() : (clone $query)
            ->expiringBy($retention, now()->addDays(self::SOON_DAYS))
            ->with('room.participants:id,name')
            ->orderByExpiry()
            ->limit(self::PAGE)
            ->get();

        $recordings = (clone $query)
            ->whereKeyNot($soonRecords->pluck('id')->all())
            ->with('room.participants:id,name')
            ->orderByDesc('start_time')
            ->limit($this->limit)
            ->get();

        $soon = $soonRecords->map(fn (Recording $r) => $this->view($r, $storage->expiresAt($r, $retention)));
        $rest = $recordings->map(fn (Recording $r) => $this->view($r, $storage->expiresAt($r, $retention)));
        $items = $soon->concat($rest);

        $current = $this->open && ! $this->selecting ? $items->firstWhere('id', $this->open) : null;
        if ($this->open && ! $this->selecting && ! $current) {
            // Открыта по ссылке, но не попала в список (фильтр, старая) — показываем всё равно
            $r = $this->playable($this->open)->load('room.participants:id,name');
            $current = $this->view($r, $storage->expiresAt($r, $retention));
        }

        $tariff = $teacher->activeSubscription()?->tariff?->name;

        return view('livewire.cabinet.teacher.recordings', [
            'sub' => $retention
                ? 'Хранятся ' . plural_ru($retention, 'день', 'дня', 'дней') . ($tariff ? ' по тарифу «' . $tariff . '»' : '')
                : ($items->isNotEmpty() ? plural_ru((clone $query)->count(), 'запись', 'записи', 'записей') : null),
            'current' => $current,
            'soon' => $soon, // первыми — те, что удалятся раньше
            'weeks' => $this->byWeek($rest),
            'isEmpty' => $items->isEmpty(),
            'hasAny' => $items->isNotEmpty() || Recording::forTeacher($teacher)->listed()->exists(),
            'searching' => $searching,
            'hasMore' => $recordings->count() >= $this->limit && (clone $query)->count() > $this->limit + $soon->count(),
            'studentOptions' => $students->count() > 1 ? ['' => 'Все ученики'] + $students->pluck('name', 'id')->all() : [],
        ]);
    }

    private function view(Recording $r, ?CarbonInterface $expiresAt): array
    {
        $room = $r->room;
        $isGroup = $room?->type === 'group' || ($room && $room->participants->count() > 1);
        $person = $isGroup ? null : $room?->participants->first();
        $start = $r->start_time;
        $minutes = $start && $r->end_time ? (int) round($start->diffInMinutes($r->end_time)) : null;
        $soon = $expiresAt && $expiresAt->lte(now()->addDays(self::SOON_DAYS));

        return [
            'id' => $r->id,
            'title' => $isGroup
                ? 'Группа «' . ($room?->name ?: $r->name) . '»'
                : implode(' · ', array_filter([$room?->name ?: $r->name ?: 'Запись занятия', $person?->name])),
            'meta' => Str::ucfirst(implode(' · ', array_filter([
                $start ? HumanDate::day($start) . ', ' . $start->format('H:i') : null,
                $minutes ? plural_ru($minutes, 'минута', 'минуты', 'минут') : null,
            ]))),
            'person' => $person,
            'group' => $isGroup,
            'start' => $start ?? $r->created_at,
            'soon' => $soon,
            'status' => $r->status(),
            'expires' => $expiresAt ? ($soon ? 'через ' . $this->left($expiresAt) : 'до ' . HumanDate::date($expiresAt)) : null,
            // Видео в хранилище — смотрим здесь; пока не перенесено — открываем проигрыватель сервера занятий
            'video' => $r->s3_url,
            'externalUrl' => ! $r->s3_url && $r->url ? $r->url : null,
            'downloadUrl' => $r->s3_url ? route('recordings.download', $r) : null,
        ];
    }

    /** «4 дня», «5 часов»; если срок уже прошёл, а очистка ещё не прошла — «несколько часов». */
    private function left(CarbonInterface $at): string
    {
        return Str::after(HumanDate::until($at) ?? 'через несколько часов', 'через ');
    }

    /** Группы по неделям: «Эта неделя, 23–29 сентября», «Прошлая неделя, 16–22 сентября», «9–15 сентября». */
    private function byWeek(Collection $items): Collection
    {
        $thisWeek = now()->startOfWeek();

        return $items->groupBy(fn (array $r) => $r['start']->copy()->startOfWeek()->toDateString())
            ->map(function (Collection $group, string $monday) use ($thisWeek) {
                $from = Carbon::parse($monday);
                $to = $from->copy()->endOfWeek();
                $range = $from->month === $to->month
                    ? $from->day . '–' . HumanDate::date($to)
                    : HumanDate::date($from) . ' – ' . HumanDate::date($to);
                $prefix = match (true) {
                    $from->equalTo($thisWeek) => 'Эта неделя, ',
                    $from->equalTo($thisWeek->copy()->subWeek()) => 'Прошлая неделя, ',
                    default => '',
                };

                return ['title' => $prefix . $range, 'items' => $group->values()];
            })
            ->values();
    }
}
