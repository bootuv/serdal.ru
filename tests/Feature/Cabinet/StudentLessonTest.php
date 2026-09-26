<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Lesson;
use App\Models\Homework;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Recording;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\RoomScheduleException;
use App\Models\TeacherMaterial;
use App\Models\User;
use App\Services\StudentScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Страница занятия ученика (/cabinet/student/lessons/{room}) и правило «Войти в класс». */
class StudentLessonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Середина дня: занятия «через 10/20 минут» остаются сегодняшними
        $this->travelTo(now()->setTime(12, 0));
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

    private function room(User $teacher, ?User $student, string $name = 'Английский язык', array $attrs = []): Room
    {
        $room = Room::create($attrs + [
            'user_id' => $teacher->id,
            'name' => $name,
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
        if ($student) {
            $room->participants()->attach($student->id);
            $teacher->students()->syncWithoutDetaching([$student->id]);
        }

        return $room;
    }

    private function oneTime(Room $room, $at, int $minutes = 60): RoomSchedule
    {
        return RoomSchedule::create([
            'room_id' => $room->id,
            'type' => 'once',
            'scheduled_at' => $at,
            'duration_minutes' => $minutes,
            'is_active' => true,
        ]);
    }

    private function weekly(Room $room, string $time = '16:00', int $inDays = 2): RoomSchedule
    {
        return RoomSchedule::create([
            'room_id' => $room->id,
            'type' => 'recurring',
            'recurrence_type' => 'weekly',
            'recurrence_days' => [now()->addDays($inDays)->dayOfWeek],
            'recurrence_time' => $time,
            'start_date' => today()->subMonth(),
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    private function held(Room $room, User $student, bool $attended, $startedAt): MeetingSession
    {
        return MeetingSession::create([
            'user_id' => $room->user_id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'internal_meeting_id' => 'int-' . uniqid(),
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMinutes(55),
            'status' => 'completed',
            'participant_count' => 2,
            'pricing_snapshot' => ['participants' => [['user_id' => $student->id, 'attended' => $attended]]],
        ]);
    }

    private function recording(Room $room, $startedAt, array $attrs = []): Recording
    {
        return Recording::create($attrs + [
            'meeting_id' => $room->meeting_id,
            'record_id' => 'rec-' . uniqid(),
            'name' => $room->name,
            'start_time' => $startedAt,
            'end_time' => $startedAt->copy()->addMinutes(55),
            's3_url' => 'https://s3.example/recordings/' . uniqid() . '.mp4',
        ]);
    }

    /*
     | Доступ
     */

    public function test_guest_is_redirected_to_login(): void
    {
        $room = $this->room($this->user(User::ROLE_TUTOR), $this->user(User::ROLE_STUDENT));

        $this->get(route('cabinet.student.lesson', $room))->assertRedirect();
    }

    public function test_participant_opens_lesson(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student);
        $this->weekly($room);

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertSee('Английский язык')
            ->assertSee('Мария Соколова')
            ->assertSee('в 16:00 · 60 минут')
            ->assertSee('Написать учителю')
            ->assertSee(route('cabinet.student.messages', ['room' => $room->id]), false);
    }

    public function test_not_participant_gets_404(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $room = $this->room($teacher, $this->user(User::ROLE_STUDENT));

        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.student.lesson', $room))
            ->assertNotFound();

        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.student.lesson', 999999))
            ->assertNotFound();
    }

    public function test_teacher_is_redirected_to_own_cabinet(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $room = $this->room($teacher, $this->user(User::ROLE_STUDENT));

        $this->actingAs($teacher)
            ->get(route('cabinet.student.lesson', $room))
            ->assertRedirect(route('cabinet.teacher.today'));
    }

    public function test_archived_lesson_stays_available_to_participant(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Старое занятие');
        $room->delete();

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room->id))
            ->assertOk()
            ->assertSee('занятие в архиве')
            ->assertDontSee('Войти в класс');
    }

    /*
     | Содержимое
     */

    public function test_shows_tasks_of_this_lesson_only(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student);
        $other = $this->room($teacher, $student, 'Другое занятие');

        $task = Homework::create(['teacher_id' => $teacher->id, 'room_id' => $room->id, 'title' => 'Эссе о каникулах', 'is_visible' => true, 'deadline' => now()->addDays(2)]);
        $task->students()->attach($student->id);
        $hidden = Homework::create(['teacher_id' => $teacher->id, 'room_id' => $room->id, 'title' => 'Черновик задания', 'is_visible' => false]);
        $hidden->students()->attach($student->id);
        $foreign = Homework::create(['teacher_id' => $teacher->id, 'room_id' => $other->id, 'title' => 'Задание другого занятия', 'is_visible' => true]);
        $foreign->students()->attach($student->id);

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertSee('Эссе о каникулах')
            ->assertSee(route('cabinet.student.task', $task), false)
            ->assertDontSee('Черновик задания')
            ->assertDontSee('Задание другого занятия');
    }

    public function test_shows_only_own_lesson_recordings(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student);
        $other = $this->room($teacher, $this->user(User::ROLE_STUDENT), 'Чужое занятие');

        $own = $this->recording($room, now()->subDays(2)->setTime(16, 0));
        $foreign = $this->recording($other, now()->subDays(3)->setTime(16, 0));

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertSee(route('cabinet.student.recordings', ['open' => $own->id]), false)
            ->assertDontSee(route('cabinet.student.recordings', ['open' => $foreign->id]), false);
    }

    public function test_past_lessons_show_attendance_and_cancelled_with_reason(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student);
        $schedule = $this->weekly($room);

        $this->held($room, $student, true, now()->subDays(7)->setTime(16, 0));
        $this->held($room, $student, false, now()->subDays(14)->setTime(16, 0));
        RoomScheduleException::create([
            'room_id' => $room->id,
            'room_schedule_id' => $schedule->id,
            'original_date' => now()->subDays(21)->toDateString(),
            'original_starts_at' => now()->subDays(21)->setTime(16, 0),
            'status' => RoomScheduleException::STATUS_CANCELLED,
            'reason' => 'учитель заболел',
        ]);

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertSee('Прошедшие занятия')
            ->assertSee('55 минут')
            ->assertSee('Вы не были на занятии')
            ->assertSee('Отменено: учитель заболел');
    }

    public function test_nearest_cancelled_lesson_shows_reason_and_following(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student);
        $schedule = $this->weekly($room);
        $date = now()->addDays(2);
        RoomScheduleException::create([
            'room_id' => $room->id,
            'room_schedule_id' => $schedule->id,
            'original_date' => $date->toDateString(),
            'original_starts_at' => $date->copy()->setTime(16, 0),
            'status' => RoomScheduleException::STATUS_CANCELLED,
            'reason' => 'праздник',
        ]);

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertSee('отменено: праздник')
            ->assertSee('следующее — ')
            ->assertSee('Отменено: праздник');
    }

    public function test_moved_lesson_is_marked(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student);
        $schedule = $this->weekly($room);
        $date = now()->addDays(2);
        RoomScheduleException::create([
            'room_id' => $room->id,
            'room_schedule_id' => $schedule->id,
            'original_date' => $date->toDateString(),
            'original_starts_at' => $date->copy()->setTime(16, 0),
            'status' => RoomScheduleException::STATUS_MOVED,
            'starts_at' => $date->copy()->setTime(18, 0),
            'duration_minutes' => 45,
        ]);

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertSee('в 18:00 · 45 минут')
            ->assertSee('перенесено с ');
    }

    public function test_materials_opened_to_this_lesson(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student);

        $shared = TeacherMaterial::create([
            'teacher_id' => $teacher->id,
            'title' => 'Словарь к занятию',
            'file_path' => 'materials/words.pdf',
            'original_name' => 'words.pdf',
            'visibility' => TeacherMaterial::VISIBILITY_ROOMS,
        ]);
        $shared->rooms()->attach($room->id);

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertSee('Словарь к занятию');
    }

    /*
     | «Войти в класс»
     */

    public function test_join_is_hidden_20_minutes_before_start(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($this->user(User::ROLE_TUTOR), $student);
        $this->oneTime($room, now()->addMinutes(20));

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertDontSee(route('rooms.connect', $room), false)
            ->assertSee('Вход откроется в ' . now()->addMinutes(5)->format('H:i'));
    }

    public function test_join_is_open_10_minutes_before_start(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($this->user(User::ROLE_TUTOR), $student);
        $this->oneTime($room, now()->addMinutes(10));

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertSee('Войти в класс')
            ->assertSee(route('rooms.connect', $room), false);
    }

    public function test_join_is_open_while_lesson_is_running(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($this->user(User::ROLE_TUTOR), $student, 'Математика', ['is_running' => true]);

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertSee('идёт сейчас')
            ->assertSee(route('rooms.connect', $room), false);
    }

    public function test_join_is_closed_for_debt(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, $student, 'Математика', ['is_running' => true]);

        PaymentRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => 'per_lesson',
            'status' => PaymentRecord::STATUS_UNPAID,
            'due_date' => today()->subDays(10),
        ]);
        foreach ([8, 6, 4] as $daysAgo) {
            $this->held($room, $student, true, now()->subDays($daysAgo));
        }

        $this->actingAs($student)
            ->get(route('cabinet.student.lesson', $room))
            ->assertOk()
            ->assertSee('Вход закрыт до оплаты')
            ->assertDontSee(route('rooms.connect', $room), false);
    }

    public function test_join_rule(): void
    {
        $this->assertFalse(StudentScheduleService::canJoin(false, now()->addMinutes(20)));
        $this->assertTrue(StudentScheduleService::canJoin(false, now()->addMinutes(10)));
        $this->assertTrue(StudentScheduleService::canJoin(true, now()->addDays(3)));
        $this->assertFalse(StudentScheduleService::canJoin(false, now()->subHours(2), now()->subHour()));
        $this->assertFalse(StudentScheduleService::canJoin(false, null));
        $this->assertSame('Вход откроется за 15 минут до начала', StudentScheduleService::joinOpensLabel(now()->addDays(2)));
    }

    public function test_earlier_past_lessons_can_be_loaded(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($this->user(User::ROLE_TUTOR), $student);
        foreach (range(1, 12) as $week) {
            $this->held($room, $student, true, now()->subWeeks($week));
        }

        Livewire::actingAs($student)
            ->test(Lesson::class, ['room' => $room->id])
            ->assertSee('Показать раньше')
            ->call('showEarlier')
            ->assertDontSee('Показать раньше');
    }
}
