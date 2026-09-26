<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Schedule;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Recording;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\User;
use App\Services\StudentScheduleService;
use App\Services\TeacherLessonService;
use App\Services\TeacherScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Расписание ученика в новом кабинете (/cabinet/student/schedule). */
class StudentScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Середина дня: «через N дней» не зависит от времени запуска
        $this->travelTo(now()->setTime(10, 0));
    }

    private function user(string $role, ?string $name = null): User
    {
        return User::factory()->create(array_filter([
            'role' => $role,
            'name' => $name,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], fn ($v) => $v !== null));
    }

    private function room(User $teacher, User $student, string $name, array $attrs = []): Room
    {
        $room = Room::create($attrs + [
            'user_id' => $teacher->id,
            'name' => $name,
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
        $room->participants()->attach($student->id);
        $teacher->students()->syncWithoutDetaching([$student->id]);

        return $room;
    }

    private function weekly(Room $room, string $time = '16:00'): RoomSchedule
    {
        return RoomSchedule::create([
            'room_id' => $room->id,
            'type' => 'recurring',
            'recurrence_type' => 'weekly',
            'recurrence_days' => [now()->addDays(2)->dayOfWeek],
            'recurrence_time' => $time,
            'start_date' => today()->subMonth(),
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    private function meeting(Room $room, User $student, bool $attended, $startedAt): MeetingSession
    {
        return MeetingSession::create([
            'user_id' => $room->user_id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'internal_meeting_id' => 'int-' . uniqid(),
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addHour(),
            'status' => 'completed',
            'participant_count' => 2,
            'pricing_snapshot' => ['participants' => [['user_id' => $student->id, 'attended' => $attended]]],
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cabinet.student.schedule'))->assertRedirect();
    }

    public function test_teacher_cannot_open_student_schedule(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.student.schedule'))
            ->assertRedirect(route('cabinet.teacher.today'));
    }

    public function test_student_without_lessons_sees_empty_state(): void
    {
        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.student.schedule'))
            ->assertOk()
            ->assertSee('Ближайших занятий нет')
            ->assertSee('Добавить в Google Календарь');
    }

    public function test_recurring_lessons_are_expanded_for_two_weeks(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Английский язык');
        $this->weekly($room);

        // Чужое занятие того же учителя ученик не видит
        $other = $this->user(User::ROLE_STUDENT);
        $this->weekly($this->room($teacher, $other, 'Чужое занятие'));

        $weekday = ['каждое воскресенье', 'каждый понедельник', 'каждый вторник', 'каждую среду',
            'каждый четверг', 'каждую пятницу', 'каждую субботу'][now()->addDays(2)->dayOfWeek];

        $this->actingAs($student)
            ->get(route('cabinet.student.schedule'))
            ->assertOk()
            ->assertSee('16:00 · Английский язык')
            ->assertSee('Начнётся через 2 дня')
            // До занятия два дня — вход ещё закрыт
            ->assertDontSee(route('rooms.connect', $room), false)
            ->assertSee('Вход откроется за 15 минут до начала')
            ->assertSee(route('cabinet.student.lesson', $room), false)
            ->assertSee('Следующие занятия')
            ->assertSee('Мария Соколова · ' . $weekday)
            ->assertDontSee('Чужое занятие');

        // Два вхождения за 14 дней (через 2 и через 9 дней), третье — за горизонтом
        $events = app(StudentScheduleService::class)->events($student->id, today(), today()->addDays(14)->endOfDay());
        $this->assertCount(2, $events);
    }

    public function test_cancelled_lesson_with_reason_is_visible_to_student(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Английский язык');
        $schedule = $this->weekly($room);
        $first = today()->addDays(2)->setTime(16, 0);

        app(TeacherLessonService::class)->cancelOccurrence($schedule, $first, 'учитель на конференции', false, $teacher);

        // Отменённое занятие не в фокусе: в фокусе — следующее, отменённое — в списке с причиной
        Livewire::actingAs($student)
            ->test(Schedule::class)
            ->assertSee('Начнётся через 9 дней')
            ->assertSee('Отменено: учитель на конференции · Мария Соколова');

        $this->assertCount(1, app(StudentScheduleService::class)->events($student->id, today(), today()->addDays(14)->endOfDay()));
        $this->assertCount(2, app(StudentScheduleService::class)->events($student->id, today(), today()->addDays(14)->endOfDay(), withCancelled: true));
    }

    public function test_moved_lesson_shows_new_time_and_origin(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Английский язык');
        $schedule = $this->weekly($room);
        $first = today()->addDays(2)->setTime(16, 0);

        app(TeacherLessonService::class)->moveOccurrence($schedule, $first, $first->copy()->addDay()->setTime(18, 30), 60, false, $teacher);

        Livewire::actingAs($student)
            ->test(Schedule::class)
            ->assertSee('18:30 · Английский язык')
            ->assertSee('Перенесено')
            ->assertSee(TeacherScheduleService::movedFromLabel($first));

        $this->assertSame($first->copy()->addDay()->setTime(18, 30)->format('Y-m-d H:i'), $room->fresh()->next_start->format('Y-m-d H:i'));
    }

    public function test_past_tab_shows_cancelled_lessons(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Английский язык');
        $schedule = $this->weekly($room);
        $past = today()->addDays(2)->subWeek()->setTime(16, 0);

        app(TeacherLessonService::class)->cancelOccurrence($schedule, $past, 'вы предупредили, что заболели', false, $teacher);

        Livewire::actingAs($student)
            ->test(Schedule::class)
            ->set('tab', 'past')
            ->assertSee('16:00 · Английский язык')
            ->assertSee('Отменено: вы предупредили, что заболели');
    }

    public function test_extra_one_time_lesson_is_marked(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $student = $this->user(User::ROLE_STUDENT);
        $this->weekly($this->room($teacher, $student, 'Английский язык'));
        $extra = $this->room($teacher, $student, 'Английский, дополнительно');
        RoomSchedule::create([
            'room_id' => $extra->id, 'type' => 'once', 'scheduled_at' => today()->addDays(3)->setTime(16, 0),
            'duration_minutes' => 60, 'is_active' => true,
        ]);

        Livewire::actingAs($student)
            ->test(Schedule::class)
            ->assertSee('Дополнительное');
    }

    public function test_join_opens_15_minutes_before_start(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Математика');
        $schedule = RoomSchedule::create([
            'room_id' => $room->id,
            'type' => 'once',
            'scheduled_at' => now()->addMinutes(20),
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        // За 20 минут — кнопки нет, вместо неё время открытия входа
        $this->actingAs($student)
            ->get(route('cabinet.student.schedule'))
            ->assertOk()
            ->assertDontSee(route('rooms.connect', $room), false)
            ->assertSee('Вход откроется в ' . now()->addMinutes(5)->format('H:i'));

        // За 10 минут — «Войти в класс»
        $schedule->update(['scheduled_at' => now()->addMinutes(10)]);

        $this->actingAs($student)
            ->get(route('cabinet.student.schedule'))
            ->assertOk()
            ->assertSee('Войти в класс')
            ->assertSee(route('rooms.connect', $room), false);
    }

    public function test_running_lesson_is_in_focus(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Математика', ['is_running' => true]);

        $this->actingAs($student)
            ->get(route('cabinet.student.schedule'))
            ->assertOk()
            ->assertSee('Математика')
            ->assertSee('Идёт сейчас')
            ->assertSee(route('rooms.connect', $room), false);
    }

    public function test_join_is_locked_when_blocked_for_payment(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Математика');
        $this->weekly($room);

        PaymentRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => 'per_lesson',
            'status' => PaymentRecord::STATUS_UNPAID,
            'due_date' => today()->subDays(10),
        ]);
        foreach ([8, 6, 4] as $daysAgo) {
            $this->meeting($room, $student, true, now()->subDays($daysAgo));
        }

        $this->actingAs($student)
            ->get(route('cabinet.student.schedule'))
            ->assertOk()
            ->assertSee('Вход закрыт до оплаты')
            ->assertSee('сообщить об оплате')
            ->assertDontSee('>оплатить<', false)
            ->assertSee(route('cabinet.student.payments', ['report' => $teacher->id]), false)
            ->assertDontSee(route('rooms.connect', $room), false);
    }

    public function test_past_lessons_show_attendance_debt_and_recording(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Английский язык');

        $attended = $this->meeting($room, $student, true, now()->subDays(5)->setTime(16, 0));
        $this->meeting($room, $student, false, now()->subDays(12)->setTime(16, 0));

        PaymentRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => 'per_lesson',
            'meeting_session_id' => $attended->id,
            'status' => PaymentRecord::STATUS_UNPAID,
            'due_date' => today()->subDays(2),
        ]);

        $recording = Recording::create([
            'meeting_id' => $room->meeting_id,
            'record_id' => $attended->internal_meeting_id . '-1',
            'name' => 'Английский язык',
            'start_time' => $attended->started_at,
            'end_time' => $attended->ended_at,
            's3_url' => 'https://s3.example/recordings/1.mp4',
        ]);

        $this->actingAs($student)
            ->get(route('cabinet.student.schedule', ['tab' => 'past']))
            ->assertOk()
            ->assertSee('Последние 3 недели')
            ->assertSee('16:00 · Английский язык')
            ->assertSee('Вы не были на занятии')
            ->assertSee('Не оплачено')
            ->assertSee('Смотреть запись')
            ->assertSee(route('cabinet.student.recordings', ['open' => $recording->id]), false);
    }

    public function test_past_lessons_can_be_loaded_earlier(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Химия');
        $this->meeting($room, $student, true, now()->subWeeks(5));

        Livewire::actingAs($student)
            ->test(Schedule::class)
            ->set('tab', 'past')
            ->assertSee('Показать раньше')
            ->assertDontSee('Химия')
            ->call('showEarlier')
            ->assertSee('Химия')
            ->assertSee('Последние 6 недель');
    }

    public function test_filter_by_teacher(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $en = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $math = $this->user(User::ROLE_TUTOR, 'Иван Орлов');
        $this->weekly($this->room($en, $student, 'Английский язык'), '16:00');
        $this->weekly($this->room($math, $student, 'Математика'), '11:00');

        Livewire::actingAs($student)
            ->test(Schedule::class)
            ->assertSee('Все учителя')
            ->assertSee('Английский язык')
            ->assertSee('Математика')
            ->set('teacher', (string) $math->id)
            ->assertSee('Математика')
            ->assertDontSee('Английский язык');
    }

    public function test_google_disconnect_asks_for_confirmation(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $student->forceFill(['google_access_token' => 'token'])->save();

        Livewire::actingAs($student)
            ->test(Schedule::class)
            ->assertSee('Отключить Google Календарь')
            ->set('confirmGoogleDisconnect', true)
            ->assertSee('Отключить Google Календарь?')
            ->assertSee(route('google.calendar.disconnect'), false);
    }
}
