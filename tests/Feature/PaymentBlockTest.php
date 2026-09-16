<?php

namespace Tests\Feature;

use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Services\PaymentRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Tests\TestCase;

/**
 * Блокировка занятий за неоплату: вычисляется из записей об оплате и истории
 * занятий, действует только на занятия того преподавателя, перед которым есть
 * просрочка, наступает после BLOCK_AFTER_LESSONS занятий, посещённых с долгом,
 * и снимается сама при любом исчезновении долга.
 */
class PaymentBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTutor(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    protected function makeStudent(User ...$teachers): User
    {
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'username' => 'student' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
        ]);

        foreach ($teachers as $teacher) {
            $teacher->students()->attach($student->id);
        }

        return $student;
    }

    protected function makeRoom(User $teacher, User $student): Room
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Занятие ' . uniqid(),
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'is_running' => true,
        ]);

        $room->participants()->attach($student->id);

        return $room;
    }

    /**
     * Завершённое занятие преподавателя, которое ученик посетил $daysAgo дней назад.
     */
    protected function attendLesson(User $teacher, User $student, int $daysAgo, bool $attended = true): \App\Models\MeetingSession
    {
        $room = Room::where('user_id', $teacher->id)->first() ?? $this->makeRoom($teacher, $student);
        $ended = now()->subDays($daysAgo)->setTime(12, 0);

        return \App\Models\MeetingSession::create([
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'started_at' => $ended->copy()->subHour(),
            'ended_at' => $ended,
            'status' => 'completed',
            'pricing_snapshot' => [
                'payment_type' => PaymentRecord::TYPE_PER_LESSON,
                'participants' => [['user_id' => $student->id, 'attended' => $attended]],
            ],
        ]);
    }

    /**
     * Ученик с долгом перед преподавателем, посетивший лимит занятий после срока оплаты.
     */
    protected function makeBlocked(User $teacher, User $student, int $overdueDays = 10): void
    {
        $this->makeRecord($teacher, $student, $overdueDays);
        foreach (range(1, PaymentRecordService::BLOCK_AFTER_LESSONS) as $i) {
            $this->attendLesson($teacher, $student, $overdueDays - $i);
        }
    }

    /**
     * Неоплаченная запись со сроком, истёкшим $daysOverdue дней назад
     * (0 — срок сегодня, отрицательное — срок ещё впереди).
     */
    protected function makeRecord(User $teacher, User $student, int $daysOverdue, string $status = PaymentRecord::STATUS_UNPAID): PaymentRecord
    {
        return PaymentRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => PaymentRecord::TYPE_PER_LESSON,
            'status' => $status,
            'due_date' => today()->subDays($daysOverdue),
        ]);
    }

    public function test_overdue_without_lessons_is_only_a_warning(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);

        $this->makeRecord($teacher, $student, 5);

        $status = PaymentRecordService::debtStatus($student->id, $teacher->id);
        $this->assertFalse($status['blocked']);
        $this->assertSame(1, $status['overdue_count']);
        $this->assertSame(0, $status['lessons_with_debt']);
        $this->assertSame(PaymentRecordService::BLOCK_AFTER_LESSONS, $status['lessons_left']);
        $this->assertSame([], PaymentRecordService::blockedTeacherIds($student->id));
        $this->assertSame([], PaymentRecordService::blockedStudentIds($teacher->id));
    }

    public function test_block_starts_after_limit_of_lessons_attended_with_debt(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $this->makeRecord($teacher, $student, 10);

        // Занятия ДО срока оплаты и в сам день срока не считаются
        $this->attendLesson($teacher, $student, 12);
        $this->attendLesson($teacher, $student, 10);
        // Пропущенное занятие тоже не считается
        $this->attendLesson($teacher, $student, 9, attended: false);
        $this->assertSame(0, PaymentRecordService::debtStatus($student->id, $teacher->id)['lessons_with_debt']);

        foreach (range(1, PaymentRecordService::BLOCK_AFTER_LESSONS - 1) as $i) {
            $this->attendLesson($teacher, $student, 8 - $i);
            $status = PaymentRecordService::debtStatus($student->id, $teacher->id);
            $this->assertFalse($status['blocked'], "После {$i} занятий ещё предупреждение");
            $this->assertSame($i, $status['lessons_with_debt']);
            $this->assertSame(PaymentRecordService::BLOCK_AFTER_LESSONS - $i, $status['lessons_left']);
        }

        $this->attendLesson($teacher, $student, 1);
        $status = PaymentRecordService::debtStatus($student->id, $teacher->id);
        $this->assertTrue($status['blocked']);
        $this->assertSame(0, $status['lessons_left']);
        $this->assertSame([$teacher->id], PaymentRecordService::blockedTeacherIds($student->id));
        $this->assertSame([$student->id], PaymentRecordService::blockedStudentIds($teacher->id));
        $this->assertSame([$student->id], PaymentRecordService::allBlockedStudentIds());
    }

    public function test_block_applies_only_to_the_teacher_owed(): void
    {
        $debtTeacher = $this->makeTutor();
        $otherTeacher = $this->makeTutor();
        $student = $this->makeStudent($debtTeacher, $otherTeacher);

        $this->makeBlocked($debtTeacher, $student);
        // У другого преподавателя тоже есть долг и занятия после него, но лимит не исчерпан
        $this->makeRecord($otherTeacher, $student, 10);
        $this->attendLesson($otherTeacher, $student, 3);

        $this->assertTrue($student->isPaymentBlockedFor($debtTeacher->id));
        $this->assertFalse($student->isPaymentBlockedFor($otherTeacher->id));
        $this->assertSame([$debtTeacher->id], PaymentRecordService::blockedTeacherIds($student->id));
        $this->assertSame([], PaymentRecordService::blockedStudentIds($otherTeacher->id));
        // Занятия другого преподавателя в счёт долга первому не идут
        $this->assertSame(1, PaymentRecordService::debtStatus($student->id, $otherTeacher->id)['lessons_with_debt']);
    }

    public function test_paid_and_cancelled_records_never_block(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);

        $this->makeRecord($teacher, $student, 30, PaymentRecord::STATUS_PAID);
        $this->makeRecord($teacher, $student, 30, PaymentRecord::STATUS_CANCELLED);
        foreach (range(1, PaymentRecordService::BLOCK_AFTER_LESSONS + 1) as $i) {
            $this->attendLesson($teacher, $student, $i);
        }

        $this->assertFalse($student->isPaymentBlockedFor($teacher->id));
        $this->assertTrue(PaymentRecordService::debtStatuses($student->id)->isEmpty());
    }

    public function test_block_lifts_when_debt_is_paid_cancelled_or_extended(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);

        $oldest = $this->makeRecord($teacher, $student, 20);
        $this->makeBlocked($teacher, $student, 10);
        $newer = PaymentRecord::where('student_id', $student->id)->where('due_date', today()->subDays(10))->first();
        $this->assertTrue($student->isPaymentBlockedFor($teacher->id));

        // Оплатили самый старый долг — отсчёт идёт от следующего, занятий после него столько же → всё ещё блок
        $oldest->markAs(PaymentRecord::STATUS_PAID, $teacher->id);
        $this->assertTrue($student->isPaymentBlockedFor($teacher->id));
        $this->assertSame(today()->subDays(10)->toDateString(), PaymentRecordService::debtStatus($student->id, $teacher->id)['debt_since']->toDateString());

        // Продлили срок — долг больше не просрочен, блокировка снята сама
        $newer->extendDue(3);
        $this->assertSame(today()->addDays(3)->toDateString(), $newer->fresh()->due_date->toDateString());
        $this->assertFalse($student->isPaymentBlockedFor($teacher->id));

        // Снова просрочен → снова блок; «не требовать оплату» снимает
        $newer->update(['due_date' => today()->subDays(10)]);
        $this->assertTrue($student->isPaymentBlockedFor($teacher->id));
        $newer->markAs(PaymentRecord::STATUS_CANCELLED, $teacher->id);
        $this->assertFalse($student->isPaymentBlockedFor($teacher->id));
    }

    public function test_block_lifts_when_records_disappear_by_any_means(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);

        $this->makeBlocked($teacher, $student);
        $record = PaymentRecord::where('student_id', $student->id)->first();
        $this->assertTrue($student->isPaymentBlockedFor($teacher->id));

        // Запись удалили напрямую (миграция, админка, каскад) — никакого «зависшего» флага
        $record->delete();
        $this->assertFalse($student->isPaymentBlockedFor($teacher->id));

        // Каскадное удаление вместе с преподавателем
        $this->makeRecord($teacher, $student, 10);
        $this->assertTrue($student->isPaymentBlockedFor($teacher->id));
        $teacherId = $teacher->id;
        $teacher->delete();
        $this->assertFalse(PaymentRecordService::isBlockedForTeacher($student->id, $teacherId));
    }

    public function test_marking_student_free_lifts_block(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $this->makeBlocked($teacher, $student);
        $this->assertTrue($student->isPaymentBlockedFor($teacher->id));

        $this->actingAs($teacher);
        \App\Filament\App\Resources\StudentResource::applyPaymentSettings($student, ['is_free' => true]);

        $this->assertFalse($student->isPaymentBlockedFor($teacher->id));
    }

    public function test_blocked_student_is_redirected_from_debt_teachers_lesson(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $room = $this->makeRoom($teacher, $student);
        $this->makeBlocked($teacher, $student);

        // До BBB дело не доходит
        Bigbluebutton::shouldReceive('isMeetingRunning')->never();

        $this->actingAs($student)
            ->get(route('rooms.connect', $room))
            ->assertRedirect(route('filament.student.pages.payment-debts'));
    }

    public function test_blocked_student_can_still_join_other_teachers_lesson(): void
    {
        $debtTeacher = $this->makeTutor();
        $otherTeacher = $this->makeTutor();
        $student = $this->makeStudent($debtTeacher, $otherTeacher);
        $otherRoom = $this->makeRoom($otherTeacher, $student);
        $this->makeBlocked($debtTeacher, $student);

        // Проверка пройдена, контроллер идёт дальше к BBB (встреча не запущена → страница ожидания)
        Bigbluebutton::shouldReceive('isMeetingRunning')->once()->andReturn(false);

        $this->actingAs($student)
            ->get(route('rooms.connect', $otherRoom))
            ->assertRedirect(route('rooms.join', $otherRoom));
    }

    public function test_student_with_warning_can_still_join(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $room = $this->makeRoom($teacher, $student);
        $this->makeRecord($teacher, $student, 10);
        $this->attendLesson($teacher, $student, 3);

        Bigbluebutton::shouldReceive('isMeetingRunning')->once()->andReturn(false);

        $this->actingAs($student)
            ->get(route('rooms.connect', $room))
            ->assertRedirect(route('rooms.join', $room));
    }

    public function test_room_owner_is_never_blocked_from_own_lesson(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $room = $this->makeRoom($teacher, $student);
        // Даже если у владельца комнаты вдруг есть собственные долги перед кем-то
        $this->makeBlocked($this->makeTutor(), $teacher);

        Bigbluebutton::shouldReceive('isMeetingRunning')->once()->andReturn(false);

        $this->actingAs($teacher)
            ->get(route('rooms.connect', $room))
            ->assertRedirect(route('rooms.join', $room));
    }

    public function test_student_with_warning_sees_lessons_left(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $room = $this->makeRoom($teacher, $student);
        $this->makeRecord($teacher, $student, 10);
        $this->attendLesson($teacher, $student, 3);

        $this->withoutVite();

        $this->actingAs($student)
            ->get(route('filament.student.pages.dashboard'))
            ->assertOk()
            ->assertSee('Предупреждение: занятия не оплачены в срок')
            ->assertSee($teacher->name)
            ->assertDontSee('Доступ к занятиям ограничен');

        $this->actingAs($student)
            ->get(route('filament.student.resources.rooms.index'))
            ->assertOk()
            ->assertSee(route('rooms.connect', $room))
            ->assertDontSee('Доступ ограничен');
    }

    public function test_blocked_student_keeps_access_to_cabinet(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $this->makeBlocked($teacher, $student);

        $this->withoutVite();

        $this->actingAs($student)
            ->get(route('filament.student.pages.dashboard'))
            ->assertOk()
            ->assertSee('Доступ к занятиям ограничен')
            ->assertSee($teacher->name);

        $this->actingAs($student)
            ->get(route('filament.student.pages.payment-debts'))
            ->assertOk()
            ->assertSee('Доступ к занятиям ограничен');
    }

    public function test_blocked_student_sees_lock_instead_of_join_button(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $room = $this->makeRoom($teacher, $student);
        $this->makeBlocked($teacher, $student);

        $this->withoutVite();

        $this->actingAs($student)
            ->get(route('filament.student.resources.rooms.index'))
            ->assertOk()
            ->assertSee('Доступ ограничен')
            ->assertDontSee(route('rooms.connect', $room));

        $this->actingAs($student)
            ->get(route('filament.student.resources.rooms.view', $room))
            ->assertOk()
            ->assertSee('Доступ ограничен')
            ->assertDontSee(route('rooms.connect', $room));

        $this->actingAs($student)
            ->get(route('filament.student.pages.schedule-calendar'))
            ->assertOk();
    }

    public function test_admin_sees_who_is_blocked_and_why(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'username' => 'admin' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
        ]);
        $teacher = $this->makeTutor();
        $blocked = $this->makeStudent($teacher);
        $clean = $this->makeStudent($teacher);
        $this->makeBlocked($teacher, $blocked);
        // Просрочка есть, но лимит занятий не исчерпан — не блокировка
        $this->makeRecord($teacher, $clean, 5);
        $this->attendLesson($teacher, $clean, 2);

        $this->withoutVite();
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->actingAs($admin);

        // Колонка «Блокировка занятий» показывает преподавателя, фильтр оставляет только заблокированных
        \Livewire\Livewire::test(\App\Filament\Resources\UserResource\Pages\ListUsers::class)
            ->assertCanSeeTableRecords([$blocked, $clean])
            ->assertTableColumnStateSet('payment_block', [$teacher->name], $blocked)
            ->assertTableColumnStateSet('payment_block', null, $clean)
            ->filterTable('payment_blocked')
            ->assertCanSeeTableRecords([$blocked])
            ->assertCanNotSeeTableRecords([$clean]);

        $this->assertStringContainsString($teacher->name, \App\Filament\Resources\UserResource::paymentBlockTooltip($blocked));
        $this->assertNull(\App\Filament\Resources\UserResource::paymentBlockTooltip($clean));

        // На странице ученика — таблица начислений с причиной блокировки
        \Livewire\Livewire::test(\App\Filament\Resources\UserResource\RelationManagers\PaymentRecordsRelationManager::class, [
            'ownerRecord' => $blocked,
            'pageClass' => \App\Filament\Resources\UserResource\Pages\EditUser::class,
        ])
            ->assertCanSeeTableRecords($blocked->paymentRecords)
            ->assertTableColumnStateSet('status', 'Блокирует занятия', $blocked->paymentRecords->first())
            ->assertSee($teacher->name);
    }

    public function test_completed_session_creates_record_without_any_block_flag(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $room = $this->makeRoom($teacher, $student);

        $session = \App\Models\MeetingSession::create([
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'started_at' => now()->subHour(),
            'status' => 'running',
            'pricing_snapshot' => [
                'payment_type' => PaymentRecord::TYPE_PER_LESSON,
                'participants' => [['user_id' => $student->id, 'attended' => true]],
            ],
        ]);
        $session->update(['status' => 'completed', 'ended_at' => now()]);

        $record = PaymentRecord::where('student_id', $student->id)->where('meeting_session_id', $session->id)->first();
        $this->assertNotNull($record);
        $this->assertSame(PaymentRecord::STATUS_UNPAID, $record->status);
        $this->assertSame(today()->addDays(PaymentRecordService::PER_LESSON_DUE_DAYS)->toDateString(), $record->due_date->toDateString());
        $this->assertFalse($student->isPaymentBlockedFor($teacher->id));
    }
}
