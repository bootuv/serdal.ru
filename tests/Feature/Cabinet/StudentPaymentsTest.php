<?php

namespace Tests\Feature\Cabinet;

use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Оплата ученика в новом кабинете (/cabinet/student/payments). */
class StudentPaymentsTest extends TestCase
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

    private function lesson(User $teacher, string $name, $endedAt): MeetingSession
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => $name,
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);

        return MeetingSession::create([
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'status' => 'completed',
            'started_at' => $endedAt->copy()->subHour(),
            'ended_at' => $endedAt,
        ]);
    }

    public function test_guest_is_redirected_and_teacher_is_forbidden(): void
    {
        $this->get(route('cabinet.student.payments'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.student.payments'))
            ->assertForbidden();
    }

    public function test_student_without_records_sees_empty_state(): void
    {
        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.student.payments'))
            ->assertOk()
            ->assertSee('Оплат пока нет');
    }

    public function test_unpaid_lessons_are_grouped_by_teacher_with_contacts(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Мария Соколова', 'telegram' => 'sokolova', 'phone' => '+79161234567']);
        $student = $this->user(User::ROLE_STUDENT);
        $other = $this->user(User::ROLE_STUDENT);

        $session = $this->lesson($teacher, 'Английский язык', now()->subDays(5));
        // Просрочено: срок вчера, после срока занятий не было — до закрытия входа 3 занятия
        $this->record($teacher, $student, ['meeting_session_id' => $session->id, 'due_date' => now()->subDay()]);
        $this->record($teacher, $other, ['meeting_session_id' => $session->id]);

        $this->actingAs($student)
            ->get(route('cabinet.student.payments'))
            ->assertOk()
            ->assertSee('К оплате')
            ->assertSee('Мария Соколова')
            ->assertSee('Английский язык')
            ->assertSee('Просрочено')
            ->assertSee('вход на занятия закроется через')
            ->assertSee('3 занятия')
            ->assertSee('https://t.me/sokolova', false)
            ->assertSee('tel:+79161234567', false)
            ->assertSee('Написать учителю')
            ->assertSee('Перевели учителю напрямую? Он сам отметит оплату.')
            ->assertDontSee('Оплатить онлайн');
    }

    public function test_history_shows_exceptions_not_paid_badges(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Иван Орлов']);
        $student = $this->user(User::ROLE_STUDENT);

        $late = $this->lesson($teacher, 'Математика', now()->startOfMonth()->addDay());
        $free = $this->lesson($teacher, 'Физика', now()->startOfMonth()->addDays(2));

        $this->record($teacher, $student, [
            'meeting_session_id' => $late->id,
            'status' => PaymentRecord::STATUS_PAID,
            'due_date' => now()->startOfMonth()->addDays(2),
            'paid_at' => now()->startOfMonth()->addDays(6),
        ]);
        $this->record($teacher, $student, ['meeting_session_id' => $free->id, 'status' => PaymentRecord::STATUS_CANCELLED]);

        $this->actingAs($student)
            ->get(route('cabinet.student.payments'))
            ->assertOk()
            ->assertSee('Долгов нет')
            ->assertSee('История оплат')
            ->assertSee(\Illuminate\Support\Str::ucfirst(\App\Support\HumanDate::month(now())))
            ->assertSee('позже срока')
            ->assertSee('Без оплаты')
            ->assertSee('оплата не требуется')
            ->assertDontSee('>Оплачено<', false);
    }

    public function test_blocked_access_warning(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);

        $this->record($teacher, $student, ['due_date' => now()->subDays(10)]);

        // После срока ученик посетил 3 занятия — вход закрыт
        foreach ([8, 6, 4] as $daysAgo) {
            $this->lesson($teacher, 'Английский язык', now()->subDays($daysAgo))
                ->update(['pricing_snapshot' => ['participants' => [['user_id' => $student->id, 'attended' => true]]]]);
        }

        $this->actingAs($student)
            ->get(route('cabinet.student.payments'))
            ->assertOk()
            ->assertSee('Вход на занятия закрыт');
    }
}
