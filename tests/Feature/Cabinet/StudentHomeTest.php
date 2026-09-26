<?php

namespace Tests\Feature\Cabinet;

use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Главная ученика в новом кабинете (/cabinet/student). */
class StudentHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cabinet.student.home'))->assertRedirect();
    }

    public function test_teacher_cannot_open_student_home(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.student.home'))
            ->assertRedirect(route('cabinet.teacher.today'));
    }

    public function test_student_without_lessons_sees_empty_state(): void
    {
        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Занятий пока нет')
            ->assertSee('Учитель ещё не назначил время')
            ->assertDontSee('Ваш учитель')
            ->assertSee('Первые шаги')
            ->assertSeeText('0 из 2')
            ->assertDontSee('К оплате');
    }

    public function test_empty_home_shows_teacher_and_first_steps(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $teacher->update(['name' => 'Мария Соколова', 'telegram' => 'maria_english']);
        $student = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);
        // Класс указан — профиль заполнен
        $student->update(['grade' => [7]]);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Занятий пока нет')
            ->assertSee('Мария Соколова')
            ->assertSee('Ваш учитель')
            ->assertSee('Написать учителю')
            ->assertSee('@maria_english')
            ->assertSee('https://t.me/maria_english', false)
            ->assertSee('Первые шаги')
            ->assertSeeText('1 из 2')
            ->assertSee('Включите уведомления')
            ->assertSee('Заполните профиль')
            ->assertSee(route('cabinet.student.profile'), false);
    }

    public function test_first_steps_disappear_when_done(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $student->update(['grade' => [7]]);
        $student->updatePushSubscription('https://push.example/' . uniqid(), 'key', 'token');

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Занятий пока нет')
            ->assertDontSee('Первые шаги');
    }

    private function upcoming(User $student, array $attrs): Room
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $room = Room::create($attrs + [
            'user_id' => $teacher->id,
            'name' => 'Английский язык',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'duration' => 60,
        ]);
        $room->participants()->attach($student->id);

        return $room;
    }

    public function test_join_is_hidden_until_15_minutes_before_start(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->upcoming($student, ['next_start' => now()->addMinutes(20)]);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Английский язык')
            ->assertDontSee(route('rooms.connect', $room), false)
            ->assertSee('Вход откроется в ' . now()->addMinutes(5)->format('H:i'))
            ->assertSee(route('cabinet.student.lesson', $room), false);

        $room->update(['next_start' => now()->addMinutes(10)]);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Войти в класс')
            ->assertSee(route('rooms.connect', $room), false);
    }

    public function test_student_sees_running_lesson_and_unpaid_lessons(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);

        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Английский язык',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'is_running' => true,
            'next_start' => now()->subMinutes(5),
            'duration' => 60,
        ]);
        $room->participants()->attach($student->id);

        PaymentRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => 'per_lesson',
            'status' => PaymentRecord::STATUS_UNPAID,
            'due_date' => now()->addDays(2),
        ]);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Английский язык')
            ->assertSee('идёт сейчас')
            ->assertSee('Войти в класс')
            ->assertSee(route('rooms.connect', $room), false)
            ->assertSee('К оплате')
            ->assertSee('1 занятие');
    }

    public function test_debt_card_warns_and_week_list_marks_closed_entry(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $teacher->update(['name' => 'Мария Соколова']);
        $student = $this->user(User::ROLE_STUDENT);

        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Английский язык',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'next_start' => now()->addHours(2),
            'duration' => 60,
        ]);
        $room->participants()->attach($student->id);
        $later = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Разговорная практика',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'next_start' => now()->addDays(2),
            'duration' => 60,
        ]);
        $later->participants()->attach($student->id);

        // Долг просрочен, после срока — 2 занятия: следующее пройдёт, потом вход закроется
        $session = fn ($daysAgo, $price) => \App\Models\MeetingSession::create([
            'user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id, 'status' => 'completed',
            'started_at' => now()->subDays($daysAgo)->subHour(), 'ended_at' => now()->subDays($daysAgo),
            'pricing_snapshot' => ['participants' => [['user_id' => $student->id, 'attended' => true, 'price' => $price]]],
        ]);
        $first = $session(12, 1500);
        PaymentRecord::create(['teacher_id' => $teacher->id, 'student_id' => $student->id, 'type' => 'per_lesson',
            'status' => PaymentRecord::STATUS_UNPAID, 'meeting_session_id' => $first->id, 'due_date' => now()->subDays(10)]);
        $session(6, 1500);
        $session(3, 1500);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Оплатите занятия')
            ->assertSee('Просрочено')
            ->assertSee('Мария Соколова · 1 занятие')
            ->assertSee('1 500 ₽')
            ->assertSee('Сегодняшнее занятие пройдёт как обычно')
            ->assertSee('Сообщить об оплате')
            ->assertSee(route('cabinet.student.payments', ['report' => $teacher->id]), false)
            ->assertSee('Написать учителю')
            ->assertDontSee('вход закрыт');

        // Третье занятие после срока — вход закрыт, в списке недели пометка
        $session(1, 1500);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Вход на занятия закрыт')
            ->assertSee('Не оплачено')
            ->assertSee('вход закрыт')
            ->assertSee('Откроется после оплаты');
    }
}
