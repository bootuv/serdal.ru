<?php

namespace App\Services;

use App\Mail\StudentInvitation;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use App\Notifications\NewTeacher;
use App\Notifications\TeacherAssignedLesson;
use App\Notifications\TeacherRemoved;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Ученики учителя: список, приглашение, занятия ученика, оплата (отметка, продление, условия) и удаление из списка.
 * Единая логика для старого кабинета (Filament StudentResource) и нового (Livewire, /cabinet/teacher/students).
 */
class TeacherStudentsService
{
    /*
     |--------------------------------------------------------------------------
     | Список учеников
     |--------------------------------------------------------------------------
     */

    /** Ученики учителя (связь teacher_student). */
    public function query(User $teacher): Builder
    {
        return User::query()->whereHas('teachers', fn (Builder $q) => $q->whereKey($teacher->id));
    }

    /** Ученик в списке учителя. */
    public function owns(User $teacher, int $studentId): bool
    {
        return DB::table('teacher_student')
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $studentId)
            ->exists();
    }

    /** Строка связи учитель–ученик (is_free, payment_type_override, created_at). */
    public function pivot(User $teacher, int $studentId): ?object
    {
        return DB::table('teacher_student')
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $studentId)
            ->first();
    }

    /** Занимается ли ученик у учителя бесплатно. */
    public function isFree(User $teacher, int $studentId): bool
    {
        return (bool) $this->pivot($teacher, $studentId)?->is_free;
    }

    /** Персональный тип оплаты ученика (null — как в базовых ценах). */
    public function paymentTypeOverride(User $teacher, int $studentId): ?string
    {
        return $this->pivot($teacher, $studentId)?->payment_type_override;
    }

    /**
     * С какого времени ученик занимается у учителя: дата связи, иначе — самое раннее добавление в его занятие.
     */
    public function since(User $teacher, int $studentId): ?Carbon
    {
        $at = $this->pivot($teacher, $studentId)?->created_at
            ?? DB::table('room_user')
                ->join('rooms', 'rooms.id', '=', 'room_user.room_id')
                ->where('rooms.user_id', $teacher->id)
                ->where('room_user.user_id', $studentId)
                ->min('room_user.created_at');

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * Ссылка на карточку ученика в новом кабинете. Карточка открывается по username; если его нет — по id
     * (Cabinet\Teacher\Student::mount понимает оба). Нового кабинета нет — старая карточка /tutor/students/{id}.
     */
    public static function studentUrl(User $student, array $query = []): string
    {
        return \Illuminate\Support\Facades\Route::has('cabinet.teacher.student')
            ? route('cabinet.teacher.student', ['student' => $student->username ?: $student->id] + $query)
            : url('/tutor/students/' . $student->id);
    }

    /** Ссылка «Написать ученику»: чат последнего общего занятия. */
    public function chatUrl(User $teacher, int $studentId): string
    {
        $roomId = Room::query()
            ->where('user_id', $teacher->id)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $studentId))
            ->latest('updated_at')
            ->value('id');

        return MessengerService::url($teacher, $roomId);
    }

    /*
     |--------------------------------------------------------------------------
     | Приглашение и добавление
     |--------------------------------------------------------------------------
     */

    /** Ссылка-приглашение учителя: одна для всех учеников, без срока действия. */
    public function invitationLink(User $teacher): string
    {
        return URL::signedRoute('student.invitation', ['teacher' => $teacher->id]);
    }

    /** Отправить приглашение на почту. */
    public function sendInvitation(User $teacher, string $email): void
    {
        Mail::to($email)->send(new StudentInvitation($this->invitationLink($teacher), $teacher->name));
    }

    /** Зарегистрированные ученики, которых ещё нет в списке учителя. */
    public function availableStudentsQuery(User $teacher): Builder
    {
        return User::where('role', User::ROLE_STUDENT)
            ->whereDoesntHave('teachers', fn (Builder $q) => $q->where('users.id', $teacher->id));
    }

    /** Поиск среди доступных учеников по имени или email. */
    public function searchAvailable(User $teacher, string $search, int $limit = 50): Collection
    {
        return $this->availableStudentsQuery($teacher)
            ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Добавить зарегистрированного ученика в список учителя. False — уже был в списке.
     */
    public function attachExisting(User $teacher, User $student): bool
    {
        $changes = $teacher->students()->syncWithoutDetaching([$student->id]);

        if (count($changes['attached']) === 0) {
            return false;
        }

        $student->notify(new NewTeacher($teacher));

        return true;
    }

    /*
     |--------------------------------------------------------------------------
     | Занятия ученика
     |--------------------------------------------------------------------------
     */

    /** Все занятия учителя с количеством участников — варианты для окна «Занятия ученика». */
    public function teacherRooms(User $teacher): Collection
    {
        return Room::query()
            ->where('user_id', $teacher->id)
            ->withCount('participants')
            ->orderBy('name')
            ->get();
    }

    /** Занятия учителя, в которые добавлен ученик. */
    public function studentRooms(User $teacher, User $student): Collection
    {
        return $student->assignedRooms()
            ->where('rooms.user_id', $teacher->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Приводит участие ученика в занятиях учителя к отмеченному набору: добавляет в новые, убирает из снятых.
     * Чужие занятия отбрасываются.
     *
     * @return array{added: Collection<int, Room>, removed: Collection<int, Room>}
     */
    public function syncRooms(User $teacher, User $student, array $roomIds): array
    {
        $teacherRooms = Room::where('user_id', $teacher->id)->get()->keyBy('id');

        $wantedIds = collect($roomIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $teacherRooms->has($id))
            ->unique()
            ->values();

        $currentIds = $student->assignedRooms()
            ->where('rooms.user_id', $teacher->id)
            ->pluck('rooms.id');

        $addedIds = $wantedIds->diff($currentIds)->values();
        $removedIds = $currentIds->diff($wantedIds)->values();

        foreach ($addedIds as $roomId) {
            $room = $teacherRooms[$roomId];
            $room->participants()->attach($student->id);
            $this->refreshRoomType($room);

            // Как и при добавлении через форму занятия: выдаём ученику задания этого занятия
            $room->attachParticipantsToHomeworks([$student->id]);

            $student->notify(new TeacherAssignedLesson($room, $teacher));
        }

        foreach ($removedIds as $roomId) {
            $room = $teacherRooms[$roomId];
            $room->participants()->detach($student->id);
            $this->refreshRoomType($room);
        }

        $student->unsetRelation('assignedRooms');

        return [
            'added' => $addedIds->map(fn (int $id) => $teacherRooms[$id])->values(),
            'removed' => $removedIds->map(fn (int $id) => $teacherRooms[$id])->values(),
        ];
    }

    /** attach()/detach() не сохраняют занятие, поэтому тип пересчитываем сами (как в EditRoom). */
    public function refreshRoomType(Room $room): void
    {
        $count = $room->participants()->count();

        $room->updateQuietly([
            'type' => match (true) {
                $count === 0 => 'pending',
                $count === 1 => 'individual',
                default => 'group',
            },
        ]);
    }

    /**
     * Прошедшие занятия ученика у учителя (завершённые, в занятиях, где ученик сейчас участник), новые сверху.
     *
     * @return array<int, array{session_id:int, room_id:int, room_name:string, started_at:?Carbon, ended_at:?Carbon,
     *     attended:bool, activity_score:int|float}>
     */
    public function attendanceHistory(User $teacher, User $student): array
    {
        $sessions = MeetingSession::whereHas('room', fn ($q) => $q->where('user_id', $teacher->id))
            ->where('status', 'completed')
            ->with('room.participants:users.id')
            ->orderBy('ended_at', 'desc')
            ->get();

        $history = [];
        $studentIdStr = (string) $student->id;

        foreach ($sessions as $session) {
            $room = $session->room;
            if (! $room || ! $room->participants->contains($student->id)) {
                continue;
            }

            $isAttended = false;
            $activityScore = 0;

            if (isset($session->pricing_snapshot['participants'])) {
                foreach ($session->pricing_snapshot['participants'] as $p) {
                    if (($p['user_id'] ?? '') == $studentIdStr && ($p['attended'] ?? false)) {
                        $isAttended = true;
                        break;
                    }
                }
            }

            // Активность: (минуты речи × 2) + сообщения + реакции + (поднятая рука × 2), не больше 10
            foreach ($session->analytics_data['participants'] ?? [] as $p) {
                if (($p['user_id'] ?? '') == $studentIdStr) {
                    $isAttended = true;
                    $talkMinutes = ($p['talking_time'] ?? 0) / 60;
                    $rawScore = ($talkMinutes * 2) + ($p['message_count'] ?? 0) + ($p['emoji_count'] ?? 0) + (($p['raise_hand_count'] ?? 0) * 2);
                    $activityScore = min(10, round($rawScore));
                    break;
                }
            }

            $history[] = [
                'session_id' => $session->id,
                'room_id' => $room->id,
                'room_name' => $room->name ?? 'Занятие',
                'started_at' => $session->started_at,
                'ended_at' => $session->ended_at,
                'attended' => $isAttended,
                'activity_score' => $activityScore,
            ];
        }

        return $history;
    }

    /**
     * Убирает ученика из списка учителя: снимает связь, удаляет из всех занятий учителя
     * и уведомляет ученика (с предложением оставить отзыв, если были занятия и отзыва ещё нет).
     */
    public function removeFromList(User $teacher, User $student): void
    {
        $teacher->students()->detach($student);

        // Как syncRooms: после detach() пересчитываем тип занятия (группа → индивидуальное → без учеников)
        $student->assignedRooms()->where('rooms.user_id', $teacher->id)->get()->each(function (Room $room) use ($student) {
            $room->participants()->detach($student->id);
            $this->refreshRoomType($room);
        });
        $student->unsetRelation('assignedRooms');

        $studentId = (string) $student->id;
        $hasCompletedLesson = MeetingSession::whereHas('room', fn ($q) => $q->where('user_id', $teacher->id))
            ->where(function ($q) use ($studentId) {
                $q->whereJsonContains('analytics_data->participants', ['user_id' => $studentId])
                    ->orWhereJsonContains('analytics_data->participants', ['user_id' => (int) $studentId]);
            })
            ->exists();

        $hasExistingReview = Review::where('user_id', $student->id)
            ->where('teacher_id', $teacher->id)
            ->exists();

        $student->notify(new TeacherRemoved($teacher, $hasCompletedLesson && ! $hasExistingReview));
    }

    /*
     |--------------------------------------------------------------------------
     | Оплата
     |--------------------------------------------------------------------------
     */

    /** Неоплаченные начисления ученика у учителя, по сроку. */
    public function unpaidRecords(User $teacher, int $studentId): Collection
    {
        return PaymentRecord::unpaid()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $studentId)
            ->with('meetingSession.room')
            ->orderBy('due_date')
            ->get();
    }

    /** Есть ли что отмечать (бесплатным ученикам — нет). */
    public function hasUnpaidRecords(User $teacher, int $studentId): bool
    {
        return ! $this->isFree($teacher, $studentId) && PaymentRecord::unpaid()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $studentId)
            ->exists();
    }

    /**
     * Состояние оплаты ученика для списка: free | none | paid | waived | blocked | overdue | unpaid.
     * records — все начисления ученика у учителя (если уже загружены), blocked — закрыт ли вход.
     *
     * @return array{state:string, unpaid:Collection, overdue:Collection, monthly_overdue:bool}
     */
    public function paymentState(User $teacher, int $studentId, ?bool $isFree = null, ?Collection $records = null, ?bool $blocked = null): array
    {
        $isFree ??= $this->isFree($teacher, $studentId);
        $records ??= PaymentRecord::where('teacher_id', $teacher->id)->where('student_id', $studentId)->get();

        $unpaid = $records->where('status', PaymentRecord::STATUS_UNPAID)->sortBy('due_date')->values();
        $overdue = $unpaid->filter(fn (PaymentRecord $r) => $r->isOverdue())->values();

        $state = match (true) {
            $isFree => 'free',
            $records->isEmpty() => 'none',
            $unpaid->isEmpty() => $records->contains('status', PaymentRecord::STATUS_PAID) ? 'paid' : 'waived',
            $overdue->isNotEmpty() => ($blocked ?? PaymentRecordService::isBlockedForTeacher($studentId, $teacher->id)) ? 'blocked' : 'overdue',
            default => 'unpaid',
        };

        return [
            'state' => $state,
            'unpaid' => $unpaid,
            'overdue' => $overdue,
            'monthly_overdue' => (bool) $overdue->firstWhere('type', PaymentRecord::TYPE_MONTHLY),
        ];
    }

    /**
     * Отметить выбранные неоплаченные начисления: paid — оплачено, cancelled — оплата не требуется.
     *
     * @return Collection<int, PaymentRecord> отмеченные начисления
     */
    public function markRecords(User $teacher, int $studentId, array $recordIds, string $status): Collection
    {
        $records = PaymentRecord::unpaid()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $studentId)
            ->whereIn('id', $recordIds)
            ->get();

        foreach ($records as $record) {
            $record->markAs($status, $teacher->id);
        }

        // Заявки «Ученик сообщил об оплате», где не осталось неоплаченного, закрываются
        if ($records->isNotEmpty()) {
            app(PaymentClaimService::class)->settle($teacher->id, $studentId);
        }

        return $records;
    }

    /** Не чаще раза в сутки: «Напомнить» об оплате. */
    public const REMIND_EVERY_HOURS = 24;

    /**
     * «Напомнить» об оплате: уведомление PaymentReminder ученику по его неоплаченным начислениям у учителя.
     * Не чаще раза в сутки (по reminded_at начислений — его же ставит ежедневная проверка просрочек).
     *
     * @return string sent | too_soon | nothing
     */
    public function remind(User $teacher, int $studentId): string
    {
        $records = $this->isFree($teacher, $studentId) ? collect() : $this->unpaidRecords($teacher, $studentId);

        if ($records->isEmpty()) {
            return 'nothing';
        }

        $last = $records->pluck('reminded_at')->filter()->max();
        if ($last && $last->gt(now()->subHours(self::REMIND_EVERY_HOURS))) {
            return 'too_soon';
        }

        $student = User::find($studentId);
        $student?->notify(new \App\Notifications\PaymentReminder($teacher, $records->count()));
        PaymentRecord::whereIn('id', $records->pluck('id'))->update(['reminded_at' => now()]);

        return 'sent';
    }

    /**
     * Отменить только что сделанную отметку «оплачено»: начисления снова ждут оплаты.
     * Трогаем только оплаченные этим учителем.
     */
    public function undoPaid(User $teacher, int $studentId, array $recordIds): int
    {
        $records = PaymentRecord::where('status', PaymentRecord::STATUS_PAID)
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $studentId)
            ->whereIn('id', $recordIds)
            ->get();

        foreach ($records as $record) {
            $record->markAs(PaymentRecord::STATUS_UNPAID);
        }

        return $records->count();
    }

    /**
     * Продлить срок выбранных неоплаченных начислений. Возвращает самый поздний новый срок.
     * Напоминание сбрасывается, блокировка снимается сама, если просроченных долгов не осталось.
     */
    public function extendRecords(User $teacher, int $studentId, array $recordIds, int $days): ?Carbon
    {
        $days = max(1, min(60, $days));

        $records = PaymentRecord::unpaid()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $studentId)
            ->whereIn('id', $recordIds)
            ->get();

        foreach ($records as $record) {
            $record->extendDue($days);
        }

        return $records->map(fn (PaymentRecord $r) => $r->fresh()->due_date)->max();
    }

    /**
     * Сохранить условия оплаты ученика: бесплатно (неоплаченные начисления отменяются) и персональный тип оплаты.
     *
     * @return array{free_changed:bool, is_free:bool, override_changed:bool, override:?string, cancelled:int}
     */
    public function applyPaymentSettings(User $teacher, User $student, bool $isFree, ?string $override): array
    {
        $wasFree = $this->isFree($teacher, $student->id);
        $result = ['free_changed' => $isFree !== $wasFree, 'is_free' => $isFree, 'override_changed' => false, 'override' => null, 'cancelled' => 0];

        if ($isFree !== $wasFree) {
            $teacher->students()->updateExistingPivot($student->id, ['is_free' => $isFree]);

            if ($isFree) {
                // Отменяем все неоплаченные начисления, чтобы не осталось долгов и напоминаний
                $records = PaymentRecord::unpaid()
                    ->where('teacher_id', $teacher->id)
                    ->where('student_id', $student->id)
                    ->get();
                $records->each(fn (PaymentRecord $r) => $r->markAs(PaymentRecord::STATUS_CANCELLED, $teacher->id));
                $result['cancelled'] = $records->count();
                app(PaymentClaimService::class)->settle($teacher->id, $student->id);

                return $result;
            }
        }

        // Персональный тип оплаты (для бесплатного ученика неактуален)
        if (! $isFree) {
            $override = in_array($override, [PaymentRecord::TYPE_PER_LESSON, PaymentRecord::TYPE_MONTHLY], true) ? $override : null;

            if ($override !== $this->paymentTypeOverride($teacher, $student->id)) {
                $teacher->students()->updateExistingPivot($student->id, ['payment_type_override' => $override]);
                $result['override_changed'] = true;
            }
            $result['override'] = $override;
        }

        return $result;
    }

    /**
     * Условия оплаты ученика по-человечески: строка и пояснение для карточки ученика,
     * плюс подписи вариантов для окна «Условия оплаты».
     *
     * @return array{line:string, note:string, options:array<string, array{title:string, sub:string}>}
     */
    public function paymentTerms(User $teacher, User $student): array
    {
        $pivot = $this->pivot($teacher, $student->id);
        $lessonTypes = $teacher->lessonTypes()->get();
        $rooms = $this->studentRooms($teacher, $student);

        $dueDays = (int) ($lessonTypes->firstWhere('type', $rooms->first()?->type ?? 'individual')?->payment_due_days
            ?? $lessonTypes->firstWhere('payment_type', PaymentRecord::TYPE_PER_LESSON)?->payment_due_days
            ?? PaymentRecordService::PER_LESSON_DUE_DAYS);
        $dueDay = (int) ($lessonTypes->firstWhere('payment_type', PaymentRecord::TYPE_MONTHLY)?->payment_due_day
            ?? PaymentRecordService::MONTHLY_DUE_DAY);

        // Как в базовых ценах: тип оплаты форматов занятий ученика (по умолчанию — поурочно)
        $defaultType = $rooms->map(fn (Room $r) => $lessonTypes->firstWhere('type', $r->type)?->payment_type)
            ->filter()->unique()->first() ?? PaymentRecord::TYPE_PER_LESSON;

        $perLesson = 'оплата в течение ' . plural_ru($dueDays, 'дня', 'дней', 'дней') . ' после занятия';
        $monthly = 'счёт 1-го числа, оплатить до ' . $dueDay . '-го';
        $block = 'Если оплата просрочена и ученик придёт ещё на ' . plural_ru(PaymentRecordService::BLOCK_AFTER_LESSONS, 'занятие', 'занятия', 'занятий')
            . ', вход в ваши занятия закроется, пока вы не отметите оплату.';

        $options = [
            'default' => ['title' => 'Как в ваших ценах', 'sub' => $defaultType === PaymentRecord::TYPE_MONTHLY ? 'Сейчас — за месяц' : 'Сейчас — за каждое занятие'],
            PaymentRecord::TYPE_PER_LESSON => ['title' => 'За каждое занятие', 'sub' => 'Счёт после занятия, оплатить за ' . plural_ru($dueDays, 'день', 'дня', 'дней')],
            PaymentRecord::TYPE_MONTHLY => ['title' => 'За месяц', 'sub' => 'Один счёт 1-го числа, оплатить до ' . $dueDay . '-го'],
        ];

        if ($pivot?->is_free) {
            return [
                'line' => 'Бесплатно · оплата не отслеживается',
                'note' => 'Счета за занятия не появляются, напоминания не приходят, вход в занятия не закрывается.',
                'options' => $options,
            ];
        }

        $override = $pivot?->payment_type_override;
        $type = $override ?: $defaultType;
        $price = $type === PaymentRecord::TYPE_PER_LESSON ? $rooms->first()?->getEffectivePrice($student->id) : null;

        return [
            'line' => $type === PaymentRecord::TYPE_MONTHLY
                ? 'Помесячно · ' . $monthly
                : 'Поурочно' . ($price ? ' · ' . self::rub((int) $price) . ' за занятие' : '') . ' · ' . $perLesson,
            'note' => ($override ? 'Выбрано для этого ученика отдельно от ваших цен. ' : 'Как в ваших базовых ценах. ') . $block,
            'options' => $options,
        ];
    }

    /**
     * Условия оплаты глазами ученика («Как вы платите»): цена и срок у учителя.
     * price — «1 500 ₽» или null, если цена не указана; free — занимается бесплатно.
     *
     * @return array{free:bool, price:?string, unit:string, due:string}
     */
    public function studentTerms(User $teacher, User $student): array
    {
        $pivot = $this->pivot($teacher, $student->id);

        if ($pivot?->is_free) {
            return ['free' => true, 'price' => null, 'unit' => '', 'due' => ''];
        }

        $lessonTypes = $teacher->lessonTypes()->get();
        $rooms = $this->studentRooms($teacher, $student);
        $type = $pivot?->payment_type_override
            ?: ($rooms->map(fn (Room $r) => $lessonTypes->firstWhere('type', $r->type)?->payment_type)->filter()->first()
                ?? PaymentRecord::TYPE_PER_LESSON);

        if ($type === PaymentRecord::TYPE_MONTHLY) {
            $monthly = $lessonTypes->firstWhere('payment_type', PaymentRecord::TYPE_MONTHLY);
            $dueDay = (int) ($monthly?->payment_due_day ?? PaymentRecordService::MONTHLY_DUE_DAY);

            return [
                'free' => false,
                'price' => $monthly?->price ? self::rub((int) $monthly->price) : null,
                'unit' => 'в месяц',
                'due' => 'до ' . $dueDay . '-го числа',
            ];
        }

        $room = $rooms->first();
        $price = $room?->getEffectivePrice($student->id);
        $dueDays = (int) ($lessonTypes->firstWhere('type', $room?->type ?? 'individual')?->payment_due_days
            ?? $lessonTypes->firstWhere('payment_type', PaymentRecord::TYPE_PER_LESSON)?->payment_due_days
            ?? PaymentRecordService::PER_LESSON_DUE_DAYS);

        return [
            'free' => false,
            'price' => $price ? self::rub((int) $price) : null,
            'unit' => 'за занятие',
            'due' => 'в течение ' . plural_ru($dueDays, 'дня', 'дней', 'дней'),
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | Подписи для кабинета
     |--------------------------------------------------------------------------
     */

    /** «2 занятия», «1 месяц», «3 счёта» — по типам начислений. */
    public function countLabel(Collection $records): string
    {
        $n = $records->count();
        $types = $records->pluck('type')->unique();

        return match (true) {
            $types->count() === 1 && $types->first() === PaymentRecord::TYPE_MONTHLY => plural_ru($n, 'месяц', 'месяца', 'месяцев'),
            $types->count() <= 1 => plural_ru($n, 'занятие', 'занятия', 'занятий'),
            default => plural_ru($n, 'счёт', 'счёта', 'счетов'),
        };
    }

    /** Сумма начислений «3 000 ₽» — только если она известна у всех (у помесячных суммы нет). */
    public function amountLabel(Collection $records): ?string
    {
        $amounts = $records->map(fn (PaymentRecord $r) => $r->amount());

        if ($amounts->isEmpty() || $amounts->contains(fn ($a) => $a === null) || $amounts->sum() <= 0) {
            return null;
        }

        return self::rub((int) $amounts->sum());
    }

    /** «1 500 ₽». */
    public static function rub(int $amount): string
    {
        return number_format($amount, 0, ',', "\u{00A0}") . "\u{00A0}₽";
    }
}
