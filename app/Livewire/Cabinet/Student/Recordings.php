<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\Recording;
use App\Models\Room;
use App\Models\User;
use App\Services\RecordingStorageService;
use App\Support\HumanDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Записи занятий ученика. Макет: «Ученик · Записи» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Записи', 'active' => 'recordings'])]
class Recordings extends Component
{
    /** За сколько дней до удаления запись попадает в «Скоро удалятся». */
    private const SOON_DAYS = 7;

    private const PAGE = 60;

    /** Открытая в плеере запись. */
    #[Url(as: 'open')]
    public ?int $open = null;

    /** Учитель, чьи записи показаны ('all' — все). */
    #[Url(as: 'teacher', except: 'all')]
    public string $teacher = 'all';

    /** Поиск по названию занятия и имени учителя. */
    #[Url(except: '')]
    public string $search = '';

    public int $limit = self::PAGE;

    /** Запись загрузилась в хранилище или появилась новая. */
    #[On('echo:recordings,.recording.updated')]
    public function refreshRecordings(): void {}

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_STUDENT, 403);

        if ($this->open) {
            $this->playable($this->open);
        }
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

    public function updatedTeacher(): void
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

    /** Запись, которую ученик может открыть в плеере: своя (занятия его учителей) и с видео. */
    private function playable(int $id): Recording
    {
        $recording = Recording::forStudent(auth()->user())->whereKey($id)->first();
        abort_unless($recording, 404);
        abort_unless($recording->s3_url, 404);

        return $recording;
    }

    public function render()
    {
        $student = auth()->user();
        $storage = app(RecordingStorageService::class);

        $teachers = $student->teachers()->orderBy('name')->get(['users.id', 'users.name']);
        $teacherId = $teachers->count() > 1 && $teachers->contains('id', (int) $this->teacher) ? (int) $this->teacher : null;

        $searching = filled(trim($this->search));
        $query = Recording::forStudent($student)
            ->listed()
            ->when($teacherId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('user_id', $teacherId)))
            ->when($searching, fn ($q) => $q->search($this->search));

        // Срок хранения — по тарифу учителя (как в recordings:cleanup)
        $teacherIds = Recording::studentRooms($student)->distinct()->pluck('user_id');
        $retention = User::whereKey($teacherIds)->get()
            ->mapWithKeys(fn (User $t) => [$t->id => $storage->retentionDays($t)]);

        // «Скоро удалятся» — отдельным запросом по сроку у каждого учителя: удаляются самые старые,
        // а список ниже — последние записи
        $until = now()->addDays(self::SOON_DAYS);
        $expiring = $retention->filter();
        $soonRecords = $expiring->isEmpty() ? collect() : (clone $query)
            ->where(function ($q) use ($expiring, $until) {
                foreach ($expiring as $tid => $days) {
                    $q->orWhere(fn ($w) => $w->whereHas('room', fn ($r) => $r->where('user_id', $tid))->expiringBy($days, $until));
                }
            })
            ->with('room.user')
            ->orderByExpiry()
            ->limit(self::PAGE)
            ->get();

        $recordings = (clone $query)
            ->whereKeyNot($soonRecords->pluck('id')->all())
            ->with('room.user')
            ->orderByDesc('start_time')
            ->limit($this->limit)
            ->get();

        $expiresAt = fn (Recording $r) => $storage->expiresAt($r, $retention[$r->room?->user_id] ?? null);
        // У учителей разные сроки хранения — первыми те, что удалятся раньше
        $soon = $soonRecords->sortBy(fn (Recording $r) => $expiresAt($r)?->getTimestamp())
            ->map(fn (Recording $r) => $this->view($r, $expiresAt($r)))->values();
        $rest = $recordings->map(fn (Recording $r) => $this->view($r, $expiresAt($r)));
        $items = $soon->concat($rest);

        $current = $this->open ? $items->firstWhere('id', $this->open) : null;
        if ($this->open && ! $current) {
            // Открыта по ссылке, но не попала в текущий список (фильтр, старая) — показываем всё равно
            $r = $this->playable($this->open)->load('room.user');
            $current = $this->view($r, $storage->expiresAt($r, $r->room?->user ? $storage->retentionDays($r->room->user) : null));
        }

        return view('livewire.cabinet.student.recordings', [
            'sub' => $this->subtitle($retention->only($soonRecords->concat($recordings)->pluck('room.user_id')->filter()->unique()->all()), $items->count()),
            'current' => $current,
            'soon' => $soon,
            'weeks' => $this->byWeek($rest),
            'searching' => $searching,
            'hasAny' => $items->isNotEmpty() || Recording::forStudent($student)->listed()->exists(),
            'hasMore' => $recordings->count() >= $this->limit && (clone $query)->count() > $this->limit + $soon->count(),
            'teacherFilter' => $teachers->count() > 1
                ? ['all' => 'Все учителя'] + $teachers->pluck('name', 'id')->all()
                : [],
        ]);
    }

    private function view(Recording $r, ?CarbonInterface $expiresAt): array
    {
        $room = $r->room;
        $start = $r->start_time;
        $minutes = $start && $r->end_time ? (int) round($start->diffInMinutes($r->end_time)) : null;
        $length = $minutes ? plural_ru($minutes, 'минута', 'минуты', 'минут') : null;
        $day = $start ? HumanDate::day($start) : null;
        $soon = $expiresAt && $expiresAt->lte(now()->addDays(self::SOON_DAYS));

        return [
            'id' => $r->id,
            'title' => implode(' · ', array_filter([$r->name ?: $room?->name ?: 'Запись занятия', $room?->user?->name])),
            'meta' => Str::ucfirst(implode(' · ', array_filter([$room?->type === 'group' ? 'Групповое' : null, $day, $length]))),
            'when' => Str::ucfirst(implode(' · ', array_filter([$start ? $day . ', ' . $start->format('H:i') : null, $length]))),
            'start' => $start ?? $r->created_at,
            'soon' => $soon,
            'status' => $r->status(),
            'expires' => $expiresAt ? ($soon ? 'через ' . $this->left($expiresAt) : 'до ' . HumanDate::date($expiresAt)) : null,
            // Видео в хранилище — смотрим здесь; пока не перенесено — открываем проигрыватель сервера занятий
            'video' => $r->s3_url,
            'externalUrl' => ! $r->s3_url && $r->url ? $r->url : null,
            // Учитель мог запретить скачивание — тогда только просмотр в кабинете
            'downloadUrl' => $r->s3_url && ($room?->user?->recordings_downloadable ?? true) ? route('recordings.download', $r) : null,
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
                $from = \Illuminate\Support\Carbon::parse($monday);
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

    /** «Каждая запись хранится 90 дней», если срок у всех учителей один; иначе — сколько записей. */
    private function subtitle(Collection $retention, int $count): ?string
    {
        $days = $retention->filter()->unique();

        if ($days->count() === 1 && $retention->count() === $retention->filter()->count()) {
            return 'Каждая запись хранится ' . plural_ru($days->first(), 'день', 'дня', 'дней');
        }

        return $count ? plural_ru($count, 'запись', 'записи', 'записей') : null;
    }
}
