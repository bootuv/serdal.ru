<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\ScheduleDemoData;
use App\Demo\Screen;
use App\Demo\World;
use App\Support\HumanDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * «Записи» учителя — App\Livewire\Cabinet\Teacher\Recordings.
 *
 * Записи — проведённые занятия за последние 3 недели (ScheduleDemoData: те же, что в «Расписании» → «Прошедшие»,
 * номер записи = номер проведённого занятия, поэтому ссылки «Запись» оттуда открывают её здесь) и несколько
 * старых, которые скоро удалятся (хранятся 90 дней по тарифу «Профи»). Видео у всех — ролик /videos/about/lesson.mp4.
 * Состояние: ?open=<номер> — плеер (?open=last — последняя запись: для DemoCabinetTest и ссылок без номера), ?student=<id>, ?search=…, ?confirmDelete=1.
 */
class Recordings extends Screen
{
    use ScheduleDemoData;

    public const PATH = 'recordings';

    private const RETENTION = 90;

    private const SOON_DAYS = 7;

    private const VIDEO = '/videos/about/lesson.mp4';

    public string $view = 'livewire.cabinet.teacher.recordings';

    public string $title = 'Записи';

    public ?string $active = 'recordings';

    public const EXAMPLES = [
        'recordings',
        'recordings?student=103',
        'recordings?search=физика',
        'recordings?search=нет-такого',
        'recordings?open=last',
        'recordings?open=last&confirmDelete=1',
    ];

    public function modalParams(): array
    {
        return ['confirmDelete'];
    }

    public function actions(): array
    {
        return [
            'play' => ['set' => ['open' => '{0}', 'confirmDelete' => null]],
            'close' => ['set' => ['open' => null, 'confirmDelete' => null]],
            'askDelete' => ['set' => ['confirmDelete' => '1']],
            'deleteOpen' => ['set' => ['open' => null, 'confirmDelete' => null], 'toast' => 'Запись удалена'],
        ];
    }

    public function data(): array
    {
        $openParam = (string) $this->state('open', '');
        $search = trim($this->state('search', ''));
        $searching = $search !== '';
        $students = World::students()->sortBy('name');
        $studentId = $students->has((int) $this->state('student', '')) ? (int) $this->state('student', '') : null;

        $filter = fn (array $r) => (! $studentId || in_array($studentId, $r['ids'], true))
            && (! $searching || mb_stripos($r['search'], $search) !== false);

        $soon = $this->records(Carbon::today()->subDays(self::RETENTION - 2), Carbon::today()->subDays(self::RETENTION - 4)->endOfDay())
            ->take(3)->filter($filter)->sortBy(fn (array $r) => $r['start']->timestamp)->values();
        $rest = $this->records(Carbon::today()->subWeeks(3), Carbon::now())
            ->filter($filter)->sortByDesc(fn (array $r) => $r['start']->timestamp)->values();
        $items = $soon->concat($rest);

        $open = $openParam === 'last' ? $rest->first()['id'] ?? null : ((int) $openParam ?: null);
        $current = $open ? $items->firstWhere('id', $open) : null;
        if ($open && ! $current) {
            // Открыта по ссылке, но не попала в список (фильтр, старая) — показываем всё равно
            $current = $this->bySession($open);
        }

        $strip = fn (Collection $rows) => $rows->map(fn (array $r) => collect($r)->except(['ids', 'search'])->all());

        return [
            'open' => $current ? $open : null,
            'student' => $studentId ? (string) $studentId : '',
            'search' => $search,
            'limit' => 60,
            'confirmDelete' => $current && $this->state('confirmDelete', false),
            'sub' => 'Хранятся ' . plural_ru(self::RETENTION, 'день', 'дня', 'дней') . ' по тарифу «' . Today::tariff()['name'] . '»',
            'current' => $current ? collect($current)->except(['ids', 'search'])->all() : null,
            'soon' => $strip($soon),
            'weeks' => $this->byWeek($strip($rest)),
            'isEmpty' => $items->isEmpty(),
            'hasAny' => true,
            'searching' => $searching,
            'hasMore' => false,
            'studentOptions' => ['' => 'Все ученики'] + $students->mapWithKeys(fn ($s) => [$s->id => $s->name])->all(),
        ];
    }

    /** Записи проведённых занятий (кто-то из учеников был) за период. */
    private function records(Carbon $from, Carbon $to): Collection
    {
        return self::demoLessons($from, $to, false)
            ->map(fn (array $l) => ($held = self::demoHeld($l)) && $held['attended'] ? $this->row($l, $held) : null)
            ->filter()
            ->values();
    }

    /** Запись по номеру проведённого занятия (minute timestamp начала). */
    private function bySession(int $id): ?array
    {
        $at = Carbon::createFromTimestamp($id * 60, config('app.timezone'));
        if ($at->lt(Carbon::now()->subDays(self::RETENTION)) || $at->gt(Carbon::now())) {
            return null;
        }

        return $this->records($at->copy()->startOfDay(), $at->copy()->endOfDay())->firstWhere('id', $id);
    }

    /** Строка в формате Recordings::view() настоящего компонента. */
    private function row(array $l, array $held): array
    {
        $person = $l['group'] ? null : $l['student'];
        $start = $held['started'];
        $expiresAt = $held['ended']->copy()->addDays(self::RETENTION);
        $soon = $expiresAt->lte(Carbon::now()->addDays(self::SOON_DAYS));
        $title = $l['group'] ? 'Группа «' . $l['title'] . '»' : implode(' · ', [$l['title'], $person->name]);

        return [
            'id' => $held['id'],
            'title' => $title,
            'meta' => Str::ucfirst(HumanDate::day($start) . ', ' . $start->format('H:i') . ' · ' . plural_ru($held['minutes'], 'минута', 'минуты', 'минут')),
            'person' => $person,
            'group' => $l['group'],
            'start' => $start,
            'soon' => $soon,
            'status' => 'ready',
            'expires' => $soon
                ? 'через ' . Str::after(HumanDate::until($expiresAt) ?? 'через несколько часов', 'через ')
                : 'до ' . HumanDate::date($expiresAt),
            'video' => self::VIDEO,
            'externalUrl' => null,
            // Скачивание ведёт мимо демо — demo-cabinet.js покажет тост
            'downloadUrl' => route('recordings.download', $held['id']),
            // Для фильтров демо (убираются перед выдачей в шаблон)
            'ids' => $l['participants']->pluck('id')->all(),
            'search' => $title . ' ' . $l['participants']->pluck('name')->implode(' '),
        ];
    }

    /** Как Recordings::byWeek: «Эта неделя, 29 сентября – 5 октября», «Прошлая неделя, …». */
    private function byWeek(Collection $items): Collection
    {
        $thisWeek = Carbon::now()->startOfWeek();

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
