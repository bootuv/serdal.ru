<?php

namespace App\Services;

use App\Helpers\FileUploadHelper;
use App\Models\LessonPlan;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\RoomScheduleException;
use App\Models\User;
use App\Notifications\TeacherAssignedLesson;
use App\Notifications\TeacherUpdatedSchedule;
use App\Support\HumanDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Планирование занятий учителя: создание занятия с расписанием, изменение и удаление правила расписания,
 * архивирование занятия. Логика — как в старом кабинете (RoomResource, CreateRoom, EditRoom), уведомления ученикам те же.
 */
class TeacherLessonService
{
    /** Ученики, которых учитель может добавить в занятие: связь teacher_student (как в RoomResource). */
    public function studentsQuery(User $teacher): Builder
    {
        return User::query()->whereHas('teachers', fn ($q) => $q->where('teacher_student.teacher_id', $teacher->id));
    }

    /**
     * Создать занятие с расписанием (CreateRoom): пароли и meeting_id, тип по числу участников, уведомление ученикам.
     *
     * @param  array<int>  $participantIds
     * @param  array<int, array>  $schedules  атрибуты RoomSchedule (см. scheduleAttributes())
     */
    public function create(User $teacher, string $name, array $participantIds, array $schedules): Room
    {
        $allowed = $this->studentsQuery($teacher)->whereIn('users.id', $participantIds)->pluck('users.id')->all();

        $room = DB::transaction(function () use ($teacher, $name, $allowed, $schedules) {
            $room = Room::create([
                'user_id' => $teacher->id,
                'name' => $name,
                'type' => 'pending',
                'meeting_id' => (string) Str::uuid(),
                'moderator_pw' => Str::random(8),
                'attendee_pw' => Str::random(8),
            ]);

            $room->participants()->sync($allowed);

            foreach ($schedules as $attributes) {
                $room->schedules()->create($attributes);
            }

            return $room;
        });

        app(TeacherStudentsService::class)->refreshRoomType($room);
        $room->refresh();

        foreach ($room->participants as $student) {
            $student->notify(new TeacherAssignedLesson($room, $teacher));
        }

        return $room;
    }

    /*
     |--------------------------------------------------------------------------
     | Ученики и название (EditRoom)
     |--------------------------------------------------------------------------
     */

    /** Из кого учитель выбирает учеников занятия: его ученики и те, кто уже в занятии (как в RoomResource). */
    public function participantOptions(User $teacher, Room $room): Collection
    {
        $current = $room->participants()->pluck('users.id')->all();

        return User::query()
            ->where(fn (Builder $q) => $q
                ->whereHas('teachers', fn ($t) => $t->where('teacher_student.teacher_id', $teacher->id))
                ->orWhereIn('users.id', $current))
            ->orderBy('name')
            ->get();
    }

    /**
     * Изменить название и учеников занятия. Чужие ученики отбрасываются; тип — по числу учеников;
     * новым ученикам — задания занятия и уведомление «Новое занятие», как после сохранения в EditRoom.
     * Цены, назначенные ученикам в занятии, у оставшихся сохраняются.
     *
     * @param  array<int>  $participantIds
     * @return array{added: Collection<int, User>, removed: Collection<int, User>}
     */
    public function updateLesson(Room $room, User $teacher, string $name, array $participantIds): array
    {
        $allowed = $this->participantOptions($teacher, $room)->pluck('id')->all();
        $wanted = array_values(array_intersect(array_unique(array_map('intval', $participantIds)), $allowed));
        $before = $room->participants()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($room, $name, $wanted) {
            $room->participants()->sync($wanted);
            $room->update(['name' => trim($name)]);
        });

        $added = array_values(array_diff($wanted, $before));
        $removed = array_values(array_diff($before, $wanted));

        $this->participantsChanged($room, $added, $teacher);

        return [
            'added' => User::whereIn('id', $added)->orderBy('name')->get(),
            'removed' => User::whereIn('id', $removed)->orderBy('name')->get(),
        ];
    }

    /**
     * После изменения состава занятия (EditRoom::afterSave и новый кабинет): тип по числу учеников,
     * новым ученикам — все задания занятия и уведомление «Новое занятие».
     *
     * @param  array<int|string>  $addedIds
     */
    public function participantsChanged(Room $room, array $addedIds, User $teacher): void
    {
        app(TeacherStudentsService::class)->refreshRoomType($room);

        $addedIds = array_values(array_unique(array_map('intval', $addedIds)));
        if ($addedIds === []) {
            return;
        }

        $room->attachParticipantsToHomeworks($addedIds);

        User::whereIn('id', $addedIds)->get()
            ->each(fn (User $student) => $student->notify(new TeacherAssignedLesson($room, $teacher)));
    }

    /*
     |--------------------------------------------------------------------------
     | Презентации занятия: открываются в классе при старте (RoomController::start)
     |--------------------------------------------------------------------------
     | Хранятся как в RoomResource: rooms.presentations — пути на диске s3 (presentations/{учитель}/…),
     | фото уменьшаются до 1920×1080. Исходные имена — rooms.presentation_names (путь → имя).
     */

    public const PRESENTATION_MAX_KB = 204800;

    public const PRESENTATION_MIMES = [
        'application/pdf',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'image/jpeg',
        'image/png',
    ];

    /** Расширения для выбора файла (accept). */
    public const PRESENTATION_ACCEPT = '.pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.odp,.odt,.ods,.jpg,.jpeg,.png';

    /** Сохранить файл в презентации занятия. null — файл не сохранился. */
    public function addPresentation(Room $room, TemporaryUploadedFile $file, ?string $clientName = null): ?string
    {
        $name = trim((string) ($clientName ?: $file->getClientOriginalName()));
        $path = FileUploadHelper::processAndStoreFile($file, 'presentations', 1920, 1080, 85);

        if (! $path) {
            return null;
        }

        $room->refresh();
        $names = $room->presentation_names ?? [];
        $names[$path] = mb_substr($name !== '' ? $name : basename($path), 0, 255);

        $room->updateQuietly([
            'presentations' => [...($room->presentations ?? []), $path],
            'presentation_names' => $names,
        ]);

        return $path;
    }

    /** Убрать презентацию из занятия и удалить файл. */
    public function removePresentation(Room $room, string $path): bool
    {
        $room->refresh();
        $files = $room->presentations ?? [];

        if (! in_array($path, $files, true)) {
            return false;
        }

        try {
            Storage::disk('s3')->delete($path);
        } catch (\Throwable $e) {
            Log::error('Презентация занятия: не удалось удалить файл', ['room_id' => $room->id, 'path' => $path, 'error' => $e->getMessage()]);
        }

        $names = $room->presentation_names ?? [];
        unset($names[$path]);

        $room->updateQuietly([
            'presentations' => array_values(array_filter($files, fn ($f) => $f !== $path)),
            'presentation_names' => $names ?: null,
        ]);

        return true;
    }

    /** Имя презентации для экрана: исходное, для загруженных в старом кабинете — имя файла. */
    public static function presentationName(Room $room, string $path): string
    {
        return ($room->presentation_names ?? [])[$path] ?? basename($path);
    }

    /** Изменить правило расписания (EditRoom): если что-то поменялось — ученики получают «Расписание обновлено». */
    public function updateSchedule(RoomSchedule $schedule, array $attributes, User $teacher): bool
    {
        $schedule->fill($attributes);

        if (! $schedule->isDirty()) {
            return false;
        }

        $schedule->save();
        $this->notifyScheduleChanged($schedule->room, $teacher);

        return true;
    }

    /**
     * Совпадает ли правило по времени с другим расписанием этого же занятия (в ближайшие восемь недель с начала правила).
     *
     * @param  array  $attributes  атрибуты RoomSchedule (см. scheduleAttributes())
     */
    public function clashes(Room $room, array $attributes, ?int $exceptScheduleId = null): bool
    {
        $from = Carbon::parse($attributes['start_date'])->startOfDay()->max(today());
        $to = $from->copy()->addWeeks(8);
        $starts = array_flip(array_map(fn (Carbon $at) => $at->timestamp, (new RoomSchedule($attributes))->rawOccurrences($from, $to)));

        return $room->schedules()->where('is_active', true)->get()
            ->reject(fn (RoomSchedule $s) => $s->id === $exceptScheduleId)
            ->contains(fn (RoomSchedule $s) => collect($s->rawOccurrences($from, $to))->contains(fn (Carbon $at) => isset($starts[$at->timestamp])));
    }

    /** Добавить правило расписания занятию (время ещё не назначено). */
    public function addSchedule(Room $room, array $attributes, User $teacher, bool $notify = true): RoomSchedule
    {
        $schedule = $room->schedules()->create($attributes);
        if ($notify) {
            $this->notifyScheduleChanged($room, $teacher);
        }

        return $schedule;
    }

    /** Удалить правило расписания (удаление пункта расписания в RoomResource) с уведомлением ученикам. */
    public function deleteSchedule(RoomSchedule $schedule, User $teacher): void
    {
        $room = $schedule->room;
        $participants = $room ? $room->participants : collect();

        $schedule->delete();

        foreach ($participants as $student) {
            $student->notify(new TeacherUpdatedSchedule($teacher, roomName: $room?->name, roomId: $room?->id));
        }
    }

    /** Убрать занятие в архив (DeleteAction в EditRoom): расписания удаляются, история и записи остаются. */
    public function archive(Room $room, User $teacher, bool $notify = true): void
    {
        $participants = $room->participants;

        $room->delete();

        if ($notify) {
            foreach ($participants as $student) {
                $student->notify(new TeacherUpdatedSchedule($teacher, 'Занятие больше не проводится', $teacher->name . ': занятие «' . $room->name . '» убрано из расписания'));
            }
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Одно занятие серии: отмена и перенос через исключения (RoomScheduleException)
     |--------------------------------------------------------------------------
     | Правило расписания не меняется. $original — время занятия по правилу (для перенесённого — исходное).
     */

    /** Отменить одно занятие. Причину ученик видит в расписании; уведомление — только если $notify. */
    public function cancelOccurrence(RoomSchedule $schedule, Carbon $original, ?string $reason, bool $notify, User $teacher): RoomScheduleException
    {
        $exception = $this->exception($schedule, $original, [
            'status' => RoomScheduleException::STATUS_CANCELLED,
            'starts_at' => null,
            'duration_minutes' => null,
            'reason' => self::cleanReason($reason),
            'notified' => $notify,
            'created_by' => $teacher->id,
        ]);

        if ($notify) {
            $this->notifyStudents($schedule->room, $teacher, 'Занятие отменено',
                '«' . $schedule->room->name . '», ' . self::when($original) . self::reasonText($exception->reason));
        }

        return $exception;
    }

    /**
     * Отменить серию, начиная с этого занятия: оно отменяется с причиной, правило заканчивается на нём,
     * будущие исключения правила удаляются. Прошедшие занятия, записи и оплаты остаются. Разовое — как одно занятие.
     */
    public function cancelSeries(RoomSchedule $schedule, Carbon $original, ?string $reason, bool $notify, User $teacher): RoomScheduleException
    {
        if ($schedule->type === 'once') {
            return $this->cancelOccurrence($schedule, $original, $reason, $notify, $teacher);
        }

        $exception = DB::transaction(function () use ($schedule, $original, $reason, $notify, $teacher) {
            $schedule->exceptions()->whereDate('original_date', '>', $original->toDateString())->get()->each->delete();
            $schedule->update(['end_date' => $original->toDateString()]);

            return $this->exception($schedule, $original, [
                'status' => RoomScheduleException::STATUS_CANCELLED,
                'starts_at' => null,
                'duration_minutes' => null,
                'reason' => self::cleanReason($reason),
                'notified' => $notify,
                'created_by' => $teacher->id,
            ]);
        });

        if ($notify) {
            $this->notifyStudents($schedule->room, $teacher, 'Занятия отменены',
                '«' . $schedule->room->name . '» ' . TeacherScheduleService::repeatLabel($schedule) . ' в ' . $original->format('H:i')
                . ' больше не проводятся, начиная с ' . self::when($original, false) . self::reasonText($exception->reason));
        }

        return $exception;
    }

    /** Перенести одно занятие. Перенос обратно на исходное время убирает исключение. */
    public function moveOccurrence(RoomSchedule $schedule, Carbon $original, Carbon $start, int $duration, bool $notify, User $teacher): ?RoomScheduleException
    {
        $existing = $schedule->exceptions()->whereDate('original_date', $original->toDateString())->first();
        $from = $existing?->isMoved() ? $existing->starts_at->copy() : $original->copy();

        if ($start->equalTo($original) && $duration === $schedule->minutes()) {
            $existing?->delete();
            $exception = null;
        } else {
            $exception = $this->exception($schedule, $original, [
                'status' => RoomScheduleException::STATUS_MOVED,
                'starts_at' => $start,
                'duration_minutes' => $duration,
                'reason' => null,
                'notified' => $notify,
                'created_by' => $teacher->id,
            ]);
        }

        if ($notify) {
            $this->notifyStudents($schedule->room, $teacher, 'Занятие перенесено',
                '«' . $schedule->room->name . '» перенесено с ' . self::when($from) . ' на ' . self::when($start));
        }

        return $exception;
    }

    /**
     * Перенести это и все следующие занятия серии: новое еженедельное правило с даты $date.
     * Если до переносимого занятия у правила занятий не было — правило меняется целиком, иначе старое заканчивается
     * накануне, а с новой даты действует новое (прошедшие занятия не меняются). Отмены будущих дат, которые есть
     * и в новом правиле, сохраняются; перенесённые по одному занятия остаются на своём времени.
     *
     * @param  array<int>  $days  дни недели (0 — вс)
     */
    public function rescheduleFollowing(RoomSchedule $schedule, Carbon $original, string $date, string $time, int $duration, array $days, bool $notify, User $teacher): RoomSchedule
    {
        $until = $schedule->end_date && $schedule->end_date->format('Y-m-d') >= $date ? $schedule->end_date->format('Y-m-d') : null;
        $attributes = self::scheduleAttributes('weekly', $date, $time, $duration, $days, $until);
        $split = Carbon::parse(min($original->toDateString(), $date))->startOfDay();
        $firstNew = self::firstOccurrence('weekly', $date, $time, $days);

        $target = DB::transaction(function () use ($schedule, $original, $attributes, $split) {
            $schedule->exceptions()->whereDate('original_date', $original->toDateString())->get()->each->delete();

            $earlier = $schedule->type !== 'once' && $schedule->start_date
                && $schedule->rawOccurrences($schedule->start_date->copy()->startOfDay(), $split->copy()->subSecond()) !== [];

            if (! $earlier) {
                $schedule->update($attributes);
                $target = $schedule;
            } else {
                $schedule->update(['end_date' => $split->copy()->subDay()->toDateString()]);
                $target = $schedule->room->schedules()->create($attributes);
            }

            // Отмены будущих дат: остаются, если в новом правиле в этот день тоже есть занятие
            $schedule->exceptions()->whereDate('original_date', '>=', $split->toDateString())->get()
                ->each(function (RoomScheduleException $e) use ($target) {
                    if (! $e->isCancelled()) {
                        return;
                    }
                    $at = $target->occurrenceOn($e->original_date);
                    $at ? $e->update(['room_schedule_id' => $target->id, 'original_starts_at' => $at]) : $e->delete();
                });

            return $target;
        });

        // План этого занятия переезжает на первое занятие нового расписания
        if ($firstNew && ! $firstNew->equalTo($original) && ! LessonPlan::where('room_id', $schedule->room_id)->where('starts_at', $firstNew)->exists()) {
            LessonPlan::where('room_id', $schedule->room_id)->where('starts_at', $original)->update(['starts_at' => $firstNew]);
        }

        if ($notify) {
            $this->notifyStudents($schedule->room, $teacher, 'Расписание изменено',
                '«' . $schedule->room->name . '» теперь ' . TeacherScheduleService::repeatLabel($target) . ' в ' . $time
                . ($firstNew ? ', первое занятие — ' . self::when($firstNew) : ''));
        }

        return $target;
    }

    /** План занятия по исходному времени вхождения. */
    public function lessonPlan(Room $room, Carbon $original): ?LessonPlan
    {
        return LessonPlan::where('room_id', $room->id)->where('starts_at', $original)->first();
    }

    /** Сохранить план занятия; пустой текст удаляет план. */
    public function saveLessonPlan(Room $room, Carbon $original, string $body, User $teacher): ?LessonPlan
    {
        $body = trim(preg_replace("/\R{3,}/u", "\n\n", $body));

        if ($body === '') {
            LessonPlan::where('room_id', $room->id)->where('starts_at', $original)->delete();

            return null;
        }

        return LessonPlan::updateOrCreate(
            ['room_id' => $room->id, 'starts_at' => $original],
            ['body' => mb_substr($body, 0, 5000), 'updated_by' => $teacher->id],
        );
    }

    private function exception(RoomSchedule $schedule, Carbon $original, array $attributes): RoomScheduleException
    {
        $exception = $schedule->exceptions()->whereDate('original_date', $original->toDateString())->first()
            ?? new RoomScheduleException(['room_schedule_id' => $schedule->id, 'original_date' => $original->toDateString()]);

        $exception->fill(['room_id' => $schedule->room_id, 'original_starts_at' => $original] + $attributes)->save();

        return $exception;
    }

    private function notifyStudents(?Room $room, User $teacher, string $title, string $body): void
    {
        foreach ($room?->participants ?? [] as $student) {
            $student->notify(new TeacherUpdatedSchedule($teacher, $title, $teacher->name . ': ' . $body, $room->name, $room->id));
        }
    }

    private static function cleanReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : mb_substr($reason, 0, 500);
    }

    private static function reasonText(?string $reason): string
    {
        return $reason ? '. Причина: ' . $reason : '';
    }

    /** «чт, 26 сентября в 16:00» — без «сегодня/завтра»: текст уведомления читают позже. */
    public static function when(Carbon $at, bool $withTime = true): string
    {
        return ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'][$at->dayOfWeek] . ', ' . HumanDate::date($at) . ($withTime ? ' в ' . $at->format('H:i') : '');
    }

    public function notifyScheduleChanged(?Room $room, User $teacher): void
    {
        foreach ($room?->participants ?? [] as $student) {
            $student->notify(new TeacherUpdatedSchedule($teacher, roomName: $room->name, roomId: $room->id));
        }
    }

    /**
     * Атрибуты RoomSchedule из формы нового кабинета.
     * once: date (Y-m-d), time (H:i); weekly: days (0–6, 0 — вс), time, date (начиная с), until (необязательно).
     */
    public static function scheduleAttributes(string $repeat, string $date, string $time, int $duration, array $days = [], ?string $until = null): array
    {
        if ($repeat === 'once') {
            $at = Carbon::parse($date . ' ' . $time);

            return [
                'type' => 'once',
                'scheduled_at' => $at,
                'start_date' => $at->format('Y-m-d'),
                'recurrence_type' => null,
                'recurrence_days' => null,
                'recurrence_time' => null,
                'end_date' => null,
                'duration_minutes' => $duration,
                'is_active' => true,
            ];
        }

        return [
            'type' => 'recurring',
            'scheduled_at' => null,
            'recurrence_type' => 'weekly',
            'recurrence_days' => array_values(array_map('intval', $days)),
            'recurrence_time' => $time,
            'start_date' => $date,
            'end_date' => $until ?: null,
            'duration_minutes' => $duration,
            'is_active' => true,
        ];
    }

    /** Первое занятие по правилу: для разового — дата и время, для еженедельного — ближайший выбранный день с даты начала. */
    public static function firstOccurrence(string $repeat, ?string $date, ?string $time, array $days = []): ?Carbon
    {
        if (! $date || ! $time || ! preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return null;
        }

        try {
            $start = Carbon::parse($date . ' ' . $time);
        } catch (\Throwable) {
            return null;
        }

        if ($repeat === 'once') {
            return $start;
        }

        $days = array_map('intval', $days);
        for ($i = 0; $i < 7; $i++) {
            $candidate = $start->copy()->addDays($i);
            if (in_array($candidate->dayOfWeek, $days, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Варианты длительности занятия: стандартные и текущая, если она нестандартная. */
    public static function durationOptions(int $current): array
    {
        return collect([30, 45, 60, 75, 90, 120, 150, 180])->push($current)->filter(fn ($m) => $m > 0)->unique()->sort()->values()
            ->mapWithKeys(fn ($m) => [$m => plural_ru($m, 'минута', 'минуты', 'минут')])->all();
    }
}
