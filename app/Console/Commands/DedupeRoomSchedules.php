<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\RoomScheduleException;
use App\Services\TeacherScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Совпадающие правила расписания одного занятия: в один день и час занятие стоит дважды.
 * Без --apply только показывает, что будет сделано. Лишнее правило удаляется (или заканчивается вчера, если у него
 * есть история отмен и переносов), у частично совпадающего убираются повторяющиеся дни. Ученикам ничего не отправляется.
 */
class DedupeRoomSchedules extends Command
{
    protected $signature = 'schedules:dedupe {--apply : Исправить (без ключа — только показать)} {--room= : Только это занятие}';

    protected $description = 'Убрать совпадающие правила расписания одного занятия';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $fixed = $manual = 0;

        $roomIds = RoomSchedule::where('is_active', true)
            ->when($this->option('room'), fn ($q, $id) => $q->where('room_id', $id))
            ->groupBy('room_id')
            ->havingRaw('count(*) > 1')
            ->pluck('room_id');

        foreach (Room::with('user:id,name')->whereIn('id', $roomIds)->orderBy('id')->get() as $room) {
            $rules = RoomSchedule::with('exceptions')->where('room_id', $room->id)->where('is_active', true)->orderBy('id')->get();
            $title = "Занятие #{$room->id} «{$room->name}» ({$room->user?->name})";
            $reported = [];

            do {
                $changed = false;

                foreach ($rules as $i => $a) {
                    foreach ($rules as $j => $b) {
                        if ($j <= $i || isset($reported[$a->id . '-' . $b->id]) || ! $this->clash($a, $b)) {
                            continue;
                        }

                        // Остаётся более раннее правило; если его нельзя оставить — более позднее
                        [$rule, $days] = $this->trim($a, $b) ?? $this->trim($b, $a) ?? [null, null];

                        if (! $rule) {
                            $reported[$a->id . '-' . $b->id] = true;
                            $manual++;
                            $this->warn("{$title}: правила #{$a->id} ({$this->label($a)}) и #{$b->id} ({$this->label($b)}) совпадают — разберите вручную");

                            continue;
                        }

                        $was = $this->label($rule);
                        if ($days === []) {
                            $end = $rule->exceptions->isNotEmpty();
                            $this->line("{$title}: правило #{$rule->id} ({$was}) — " . ($end ? 'заканчивается вчера' : 'удаляется'));
                            if ($apply) {
                                $end ? $rule->update(['end_date' => today()->subDay()->toDateString()]) : $rule->delete();
                            }
                            $rules = $rules->reject(fn (RoomSchedule $s) => $s->id === $rule->id)->values();
                        } else {
                            $rule->recurrence_days = $days;
                            $this->line("{$title}: правило #{$rule->id} ({$was}) — остаётся {$this->label($rule)}");
                            if ($apply) {
                                $rule->save();
                            }
                        }

                        $fixed++;
                        $changed = true;

                        break 2;
                    }
                }
            } while ($changed);
        }

        $this->info(($apply ? 'Исправлено' : 'Будет исправлено') . ": {$fixed}, вручную: {$manual}." . ($apply || ! $fixed ? '' : ' Чтобы исправить, запустите с --apply.'));

        return self::SUCCESS;
    }

    /** Есть ли у двух правил занятие в одно и то же время в ближайшие восемь недель. */
    private function clash(RoomSchedule $a, RoomSchedule $b): bool
    {
        $from = today();
        $to = $from->copy()->addWeeks(8);
        $starts = array_flip(array_map(fn (Carbon $at) => $at->timestamp, $a->rawOccurrences($from, $to)));

        return collect($b->rawOccurrences($from, $to))->contains(fn (Carbon $at) => isset($starts[$at->timestamp]));
    }

    /**
     * Что убрать у правила $other, если остаётся $keeper: [правило, оставшиеся дни недели] ([] — правило лишнее целиком).
     * null — автоматически не разобрать: разные виды повтора, $keeper не покрывает срок $other или у $other есть
     * будущие отмены и переносы в совпадающие дни.
     *
     * @return array{0: RoomSchedule, 1: array<int>}|null
     */
    private function trim(RoomSchedule $keeper, RoomSchedule $other): ?array
    {
        $future = $other->exceptions->filter(fn (RoomScheduleException $e) => $e->original_date->gte(today()));

        if ($keeper->type === 'once' && $other->type === 'once') {
            return $future->isEmpty() ? [$other, []] : null;
        }

        if ($keeper->type === 'once' || $other->type === 'once' || $keeper->recurrence_type !== 'weekly' || $other->recurrence_type !== 'weekly'
            || substr((string) $keeper->recurrence_time, 0, 5) !== substr((string) $other->recurrence_time, 0, 5)) {
            return null;
        }

        $covers = $keeper->start_date->lte($other->start_date->max(today()))
            && (! $keeper->end_date || ($other->end_date && $keeper->end_date->gte($other->end_date)));
        $own = $this->days($other);
        $common = $own->intersect($this->days($keeper));

        if (! $covers || $common->isEmpty() || $future->contains(fn (RoomScheduleException $e) => $common->contains($e->original_date->dayOfWeek))) {
            return null;
        }

        return [$other, $own->diff($common)->values()->all()];
    }

    /** @return Collection<int, int> дни недели правила (0 — вс) */
    private function days(RoomSchedule $rule): Collection
    {
        return collect($rule->recurrence_days ?? [])->map(fn ($d) => (int) $d)->unique()->values();
    }

    private function label(RoomSchedule $rule): string
    {
        return $rule->type === 'once'
            ? 'разовое, ' . $rule->scheduled_at?->format('d.m.Y H:i')
            : TeacherScheduleService::repeatLabel($rule) . ' в ' . substr((string) $rule->recurrence_time, 0, 5);
    }
}
