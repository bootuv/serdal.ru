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
            ->assertForbidden();
    }

    public function test_student_without_lessons_sees_empty_state(): void
    {
        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Занятий пока нет')
            ->assertDontSee('К оплате');
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
}
