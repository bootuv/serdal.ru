<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Student;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Notifications\TeacherAssignedLesson;
use App\Notifications\TeacherRemoved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Карточка ученика в новом кабинете учителя (/cabinet/teacher/students/{student}). */
class TeacherStudentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], $attrs));
    }

    /** @return array{0: User, 1: User} учитель и его ученица */
    private function pair(): array
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT, [
            'first_name' => 'Алина', 'last_name' => 'Смирнова',
            'email' => 'alina@example.com', 'telegram' => 'alina_sm',
        ]);
        $teacher->students()->attach($student->id);

        return [$teacher, $student];
    }

    private function room(User $teacher, string $name, array $students = []): Room
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => $name,
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
        $room->participants()->attach(collect($students)->pluck('id')->all());

        return $room;
    }

    private function record(User $teacher, User $student, array $attrs = []): PaymentRecord
    {
        return PaymentRecord::create(array_merge([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => PaymentRecord::TYPE_PER_LESSON,
            'status' => PaymentRecord::STATUS_UNPAID,
            'due_date' => now()->addDays(3),
        ], $attrs));
    }

    public function test_access(): void
    {
        [$teacher, $student] = $this->pair();
        $url = route('cabinet.teacher.student', $student);

        $this->get($url)->assertRedirect();
        $this->actingAs($student)->get($url)->assertRedirect(route('cabinet.student.home'));
        $this->actingAs($this->user(User::ROLE_TUTOR))->get($url)->assertNotFound();
        $this->actingAs($teacher)->get($url)->assertOk();
    }

    public function test_overview_shows_header_performance_debts_and_tasks(): void
    {
        [$teacher, $student] = $this->pair();
        $room = $this->room($teacher, 'Английский язык', [$student]);
        $room->updateQuietly(['next_start' => now()->addDay()->setTime(16, 0), 'duration' => 60]);
        $this->record($teacher, $student, ['due_date' => now()->addDays(2)]);

        $homework = Homework::create(['teacher_id' => $teacher->id, 'title' => 'Тест по Present Perfect', 'type' => 'homework', 'is_visible' => true]);
        $homework->students()->attach($student->id);
        HomeworkSubmission::create(['homework_id' => $homework->id, 'student_id' => $student->id, 'status' => 'submitted', 'submitted_at' => now()->subDays(2)]);

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.student', $student))
            ->assertOk()
            ->assertSee('Смирнова Алина')
            ->assertSee('следующее занятие')
            ->assertSee('завтра в 16:00')
            ->assertSee('Написать')
            ->assertSee('Запланировать занятие')
            ->assertSee('Успеваемость')
            ->assertSee('Посещаемость')
            ->assertSee('Ждёт оплаты')
            ->assertSee('Тест по Present Perfect')
            ->assertSee('ждёт 2 дня')
            ->assertSee('alina@example.com')
            ->assertSee('Telegram · @alina_sm');
    }

    public function test_lessons_and_pay_tabs(): void
    {
        [$teacher, $student] = $this->pair();
        $room = $this->room($teacher, 'Разговорная практика', [$student]);
        $session = MeetingSession::create([
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'status' => 'completed',
            'started_at' => now()->subDays(3)->setTime(16, 0),
            'ended_at' => now()->subDays(3)->setTime(17, 0),
            'pricing_snapshot' => ['participants' => [['user_id' => $student->id, 'price' => 1500, 'attended' => true]]],
        ]);
        $this->record($teacher, $student, ['meeting_session_id' => $session->id]);
        $this->record($teacher, $student, ['status' => PaymentRecord::STATUS_PAID, 'paid_at' => now()->subDays(10), 'due_date' => now()->subDays(9)]);

        Livewire::actingAs($teacher)->test(Student::class, ['student' => $student])
            ->set('tab', 'lessons')
            ->assertSee('Предстоящие')
            ->assertSee('Прошедшие')
            ->assertSee('Разговорная практика')
            ->assertSee('60 минут')
            ->assertSee('Не оплачено')
            ->set('tab', 'pay')
            ->assertSee("1\u{00A0}500\u{00A0}₽")
            ->assertSee('История оплат')
            ->assertSee('Оплачено ')
            ->assertSee('Условия оплаты')
            ->assertSee('Поурочно');
    }

    public function test_mark_selected_as_paid_and_undo(): void
    {
        [$teacher, $student] = $this->pair();
        $first = $this->record($teacher, $student);
        $second = $this->record($teacher, $student, ['due_date' => now()->addDays(5)]);

        $component = Livewire::actingAs($teacher)->test(Student::class, ['student' => $student])
            ->call('openModal', 'mark')
            ->assertSee('Что оплачено')
            ->set('selected', [])
            ->call('markPaid')
            ->assertHasErrors('selected')
            ->set('selected', [(string) $first->id])
            ->call('markPaid')
            ->assertSet('modal', null)
            ->assertDispatched('toast')
            ->assertSee('Отменить');

        $this->assertSame(PaymentRecord::STATUS_PAID, $first->fresh()->status);
        $this->assertSame(PaymentRecord::STATUS_UNPAID, $second->fresh()->status);

        $component->call('undoPaid');
        $this->assertSame(PaymentRecord::STATUS_UNPAID, $first->fresh()->status);
    }

    public function test_waive_and_extend(): void
    {
        [$teacher, $student] = $this->pair();
        $overdue = $this->record($teacher, $student, ['due_date' => now()->subDays(2)]);
        $other = $this->record($teacher, $student, ['due_date' => now()->addDays(4)]);

        Livewire::actingAs($teacher)->test(Student::class, ['student' => $student])
            ->call('openModal', 'extend')
            ->assertSee('считаем от сегодня')
            ->set('extendDays', 14)
            ->call('extend')
            ->assertDispatched('toast');

        $this->assertTrue($overdue->fresh()->due_date->isSameDay(today()->addDays(14)));
        $this->assertTrue($other->fresh()->due_date->isSameDay(today()->addDays(18)));

        Livewire::actingAs($teacher)->test(Student::class, ['student' => $student])
            ->call('openModal', 'waive')
            ->assertSee('Вернуть в долг нельзя')
            ->set('selected', [$overdue->id])
            ->call('waive');

        $this->assertSame(PaymentRecord::STATUS_CANCELLED, $overdue->fresh()->status);
        $this->assertSame(PaymentRecord::STATUS_UNPAID, $other->fresh()->status);
    }

    public function test_payment_settings(): void
    {
        [$teacher, $student] = $this->pair();
        $record = $this->record($teacher, $student);

        Livewire::actingAs($teacher)->test(Student::class, ['student' => $student])
            ->call('openModal', 'settings')
            ->assertSee('Как Алина платит')
            ->set('settingsType', PaymentRecord::TYPE_MONTHLY)
            ->call('saveSettings')
            ->assertDispatched('toast', message: 'Условия оплаты сохранены');

        $pivot = fn () => DB::table('teacher_student')->where('teacher_id', $teacher->id)->where('student_id', $student->id)->first();
        $this->assertSame(PaymentRecord::TYPE_MONTHLY, $pivot()->payment_type_override);

        Livewire::actingAs($teacher)->test(Student::class, ['student' => $student])
            ->call('openModal', 'settings')
            ->set('settingsFree', true)
            ->assertSee('спишется')
            ->call('saveSettings');

        $this->assertTrue((bool) $pivot()->is_free);
        $this->assertSame(PaymentRecord::STATUS_CANCELLED, $record->fresh()->status);
    }

    public function test_assign_rooms(): void
    {
        Notification::fake();
        [$teacher, $student] = $this->pair();
        $current = $this->room($teacher, 'Английский язык', [$student]);
        $club = $this->room($teacher, 'Разговорный клуб');
        $foreign = $this->room($this->user(User::ROLE_TUTOR), 'Чужое занятие');

        Livewire::actingAs($teacher)->test(Student::class, ['student' => $student])
            ->call('openModal', 'assign')
            ->assertSee('Разговорный клуб')
            ->assertDontSee('Чужое занятие')
            ->set('roomIds', [(string) $club->id, (string) $foreign->id])
            ->call('saveRooms')
            ->assertDispatched('toast', message: 'Занятия ученика сохранены');

        $this->assertTrue($club->participants()->whereKey($student->id)->exists());
        $this->assertFalse($current->participants()->whereKey($student->id)->exists());
        $this->assertFalse($foreign->participants()->whereKey($student->id)->exists());
        Notification::assertSentTo($student, TeacherAssignedLesson::class);
    }

    public function test_remove_from_list(): void
    {
        Notification::fake();
        [$teacher, $student] = $this->pair();
        $room = $this->room($teacher, 'Английский язык', [$student]);

        Livewire::actingAs($teacher)->test(Student::class, ['student' => $student])
            ->call('openModal', 'remove')
            ->assertSee('Удалить из списка?')
            ->call('remove')
            ->assertRedirect(route('cabinet.teacher.students'));

        $this->assertFalse($teacher->students()->whereKey($student->id)->exists());
        $this->assertFalse($room->participants()->whereKey($student->id)->exists());
        Notification::assertSentTo($student, TeacherRemoved::class);

        // Тост об удалении показывается уже в списке учеников
        $this->assertSame('Смирнова Алина больше не в вашем списке', session('cabinet_toast'));
        $this->actingAs($teacher)->withSession(['cabinet_toast' => session('cabinet_toast')])
            ->get(route('cabinet.teacher.students'))
            ->assertSee("\$dispatch('toast'", false);
    }
}
