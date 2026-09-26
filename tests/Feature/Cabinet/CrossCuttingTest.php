<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\GuestJoinRoom;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Support\CabinetUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Livewire\Livewire;
use Tests\TestCase;

/** Сквозное: страницы ошибок, возвраты в новый кабинет, блокировка, вход в класс, меню «Ещё». */
class CrossCuttingTest extends TestCase
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

    private function room(User $teacher, array $students = []): Room
    {
        $room = Room::create(['user_id' => $teacher->id, 'name' => 'Английский язык', 'meeting_id' => 'm-' . uniqid(), 'moderator_pw' => 'a', 'attendee_pw' => 'b', 'duration' => 60]);
        $room->participants()->attach(collect($students)->pluck('id'));

        return $room;
    }

    public function test_branded_404_for_guest_and_user(): void
    {
        $this->get('/cabinet/teacher/tasks/review/999999')->assertRedirect();
        $this->get('/no/such/page/here')->assertNotFound()->assertSee('Такой страницы нет')->assertSee('На главную');

        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get('/cabinet/teacher/lessons/999999')
            ->assertNotFound()
            ->assertSee('Такой страницы нет')
            ->assertSee('Вернуться в кабинет')
            ->assertSee('Написать в поддержку');
    }

    public function test_cabinet_entry_is_not_a_teacher_page(): void
    {
        $this->actingAs($this->user(User::ROLE_STUDENT))->get('/cabinet')->assertRedirect(route('cabinet.student.home'));
    }

    public function test_after_lesson_users_return_to_new_cabinet(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, [$student]);
        $session = MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id, 'started_at' => now()->subHour(), 'status' => 'completed']);

        $this->actingAs($teacher)->get(route('session.logout', $session))->assertRedirect(route('cabinet.teacher.lesson', $room));
        $this->actingAs($student)->get(route('session.logout', $session))->assertRedirect();
        $this->assertStringContainsString('/cabinet/student', $this->actingAs($student)->get(route('session.logout', $session))->headers->get('Location'));
    }

    public function test_student_with_debt_goes_to_new_payments_page(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, [$student]);
        PaymentRecord::create(['teacher_id' => $teacher->id, 'student_id' => $student->id, 'type' => 'per_lesson', 'status' => PaymentRecord::STATUS_UNPAID, 'due_date' => now()->subDays(10)]);
        // Долг просрочен, и после срока ученик посетил лимит занятий — вход закрывается
        foreach (range(1, \App\Services\PaymentRecordService::BLOCK_AFTER_LESSONS) as $i) {
            $ended = now()->subDays(10 - $i)->setTime(12, 0);
            MeetingSession::create([
                'user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id,
                'started_at' => $ended->copy()->subHour(), 'ended_at' => $ended, 'status' => 'completed',
                'pricing_snapshot' => ['payment_type' => PaymentRecord::TYPE_PER_LESSON, 'participants' => [['user_id' => $student->id, 'attended' => true]]],
            ]);
        }
        $this->assertTrue(\App\Services\PaymentRecordService::isBlockedForTeacher($student->id, $teacher->id));

        $this->actingAs($student)->get(route('rooms.connect', $room))
            ->assertRedirect(route('cabinet.student.payments'))
            ->assertSessionHas('error');
    }

    public function test_blocked_during_session_sees_blocked_screen(): void
    {
        $student = $this->user(User::ROLE_STUDENT, ['email' => 'alina@mail.ru']);
        $this->actingAs($student);
        $student->forceFill(['is_blocked' => true])->save();

        $this->get(route('cabinet.student.home'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('login'))->assertSee('Доступ к кабинету приостановлен')->assertSee('alina@mail.ru');
    }

    public function test_join_page_waits_for_teacher_then_lets_in(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Мария Соколова']);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, [$student]);

        Bigbluebutton::shouldReceive('isMeetingRunning')->andReturn(false, true);

        $c = Livewire::actingAs($student)->test(GuestJoinRoom::class, ['room' => $room])
            ->assertSee('Ждём учителя')
            ->assertSee('Проверить сейчас')
            ->assertSee('Написать учителю')
            ->call('checkRoomStatus')
            ->assertSee('Занятие началось')
            ->assertSee(route('rooms.connect', $room), false);

        // Учитель своего занятия уходит на страницу занятия в кабинете
        Livewire::actingAs($teacher)->test(GuestJoinRoom::class, ['room' => $room])->assertRedirect(route('cabinet.teacher.lesson', $room));
    }

    public function test_guest_enters_name_when_lesson_started(): void
    {
        $room = $this->room($this->user(User::ROLE_TUTOR));
        Bigbluebutton::shouldReceive('isMeetingRunning')->andReturn(true);

        Livewire::test(GuestJoinRoom::class, ['room' => $room])
            ->assertSee('Как вас зовут')
            ->assertSee('Есть аккаунт на Serdal?')
            ->call('submitName')
            ->assertHasErrors('name')
            ->set('name', 'Елена Смирнова')
            ->call('submitName')
            ->assertRedirect(route('rooms.connect', $room));

        $this->assertSame('Елена Смирнова', session('guest_name'));
    }

    public function test_mobile_more_menu_lists_all_sections(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.teacher.today'))
            ->assertSee('more-open')
            ->assertSee('Все разделы')
            ->assertSeeInOrder(['Все разделы', 'Ученики', 'Материалы', 'Записи', 'Отзывы', 'Поддержка', 'Профиль и тариф']);
    }

    public function test_more_legacy_links_are_translated(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, [$student]);
        $session = MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id, 'started_at' => now(), 'status' => 'completed']);

        $this->assertSame(route('cabinet.teacher.lesson', ['room' => $room->id]), CabinetUrl::fromLegacy(url('/tutor/meeting-sessions/' . $session->id), $teacher));
        $this->assertSame(route('cabinet.teacher.review', ['submission' => 5]), CabinetUrl::fromLegacy(url('/tutor/homework-submissions/5'), $teacher));
        $this->assertSame(route('cabinet.teacher.student', ['student' => $student->username]), CabinetUrl::fromLegacy(url('/tutor/students/' . $student->id . '/edit'), $teacher));
        $this->assertSame(route('cabinet.teacher.profile'), CabinetUrl::fromLegacy(url('/tutor/edit-profile'), $teacher));
        $this->assertSame(route('cabinet.student.profile'), CabinetUrl::fromLegacy(url('/student/profile'), $student));
    }
}
