<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Schedule;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Services\TeacherScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Расписание учителя в новом кабинете (/cabinet/teacher/schedule) и общий расчёт вхождений. */
class TeacherScheduleTest extends TestCase
{
    use RefreshDatabase, TeacherLessonFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->fixNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_is_redirected_and_student_is_forbidden(): void
    {
        $this->get(route('cabinet.teacher.schedule'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.teacher.schedule'))
            ->assertForbidden();
    }

    public function test_service_expands_once_and_weekly_schedules_of_own_rooms_only(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $this->weeklyAt($room, [2, 4], '18:30', 90);
        $this->onceAt($room, '2026-09-26 11:00');

        $foreign = $this->room($this->teacher(), []);
        $this->onceAt($foreign, '2026-09-25 10:00');

        $events = app(TeacherScheduleService::class)->events($teacher->id, Carbon::parse('2026-09-21'), Carbon::parse('2026-09-27 23:59'));

        $this->assertSame(
            ['2026-09-22 18:30', '2026-09-24 18:30', '2026-09-26 11:00'],
            $events->map(fn ($e) => $e['start']->format('Y-m-d H:i'))->all()
        );
        $this->assertSame(90, $events->first()['duration']);
    }

    public function test_upcoming_list_groups_lessons_by_day(): void
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $pavel = $this->studentOf($teacher, 'Павел Ким');
        $room = $this->room($teacher, [$alina]);
        $this->weeklyAt($room, [4], '16:00');
        $math = $this->room($teacher, [$pavel], ['name' => 'Математика']);
        $this->onceAt($math, '2026-09-25 10:00');

        $session = $this->completedSession($math, '2026-09-10 10:00', 60, [$pavel->id]);
        PaymentRecord::create([
            'teacher_id' => $teacher->id, 'student_id' => $pavel->id, 'type' => PaymentRecord::TYPE_PER_LESSON,
            'meeting_session_id' => $session->id, 'status' => PaymentRecord::STATUS_UNPAID, 'due_date' => '2026-09-13',
        ]);

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.schedule'))
            ->assertOk()
            ->assertSee('Ближайшие две недели')
            ->assertSee('Сегодня')
            ->assertSee('Английский язык · Алина Смирнова')
            ->assertSee('каждый четверг')
            ->assertSee('Начать занятие')
            ->assertSee('Завтра')
            ->assertSee('Математика · Павел Ким')
            ->assertSee('разовое занятие')
            ->assertSee('Не оплачено');
    }

    public function test_filter_by_student(): void
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $pavel = $this->studentOf($teacher, 'Павел Ким');
        $this->weeklyAt($this->room($teacher, [$alina]), [5], '16:00');
        $this->weeklyAt($this->room($teacher, [$pavel], ['name' => 'Математика']), [5], '10:00');

        Livewire::actingAs($teacher)
            ->test(Schedule::class)
            ->assertSee('Математика · Павел Ким')
            ->set('who', 's' . $alina->id)
            ->assertSee('Английский язык · Алина Смирнова')
            ->assertDontSee('Математика · Павел Ким');
    }

    public function test_week_and_month_views(): void
    {
        $teacher = $this->teacher();
        $this->weeklyAt($this->room($teacher, [$this->studentOf($teacher)]), [5], '16:00');

        Livewire::actingAs($teacher)
            ->test(Schedule::class)
            ->set('view', 'week')
            ->assertSee('21–27 сентября')
            ->assertSee('16:00 · Алина Смирнова')
            ->call('shift', 1)
            ->assertSee('28 сентября – 4 октября')
            ->assertSet('date', '2026-10-01')
            ->call('goToday')
            ->set('view', 'month')
            ->assertSee('Сентябрь 2026')
            ->assertSee('16:00 Алина Смирнова');
    }

    public function test_past_tab_shows_attendance_and_debt(): void
    {
        $teacher = $this->teacher();
        $students = [$this->studentOf($teacher), $this->studentOf($teacher, 'Иван Петров'), $this->studentOf($teacher, 'Павел Ким')];
        $group = $this->room($teacher, $students, ['name' => 'Группа ЕГЭ']);
        $session = $this->completedSession($group, '2026-09-22 18:30', 88, [$students[0]->id, $students[1]->id]);
        PaymentRecord::create([
            'teacher_id' => $teacher->id, 'student_id' => $students[0]->id, 'type' => PaymentRecord::TYPE_PER_LESSON,
            'meeting_session_id' => $session->id, 'status' => PaymentRecord::STATUS_UNPAID, 'due_date' => '2026-09-23',
        ]);

        Livewire::actingAs($teacher)
            ->test(Schedule::class)
            ->set('tab', 'past')
            ->assertSee('Группа ЕГЭ')
            ->assertSee('88 мин')
            ->assertSee('Были 2 из 3 учеников')
            ->assertSee('Не оплачено')
            ->assertSee(route('cabinet.teacher.lesson', ['room' => $group->id, 'session' => $session->id]), false);
    }

    public function test_plan_group_lesson_with_two_times(): void
    {
        Notification::fake();
        $teacher = $this->teacher();
        $a = $this->studentOf($teacher);
        $b = $this->studentOf($teacher, 'Иван Петров');

        Livewire::actingAs($teacher)
            ->test(Schedule::class)
            ->call('openPlan')
            ->set('planKind', 'group')
            ->set('planAdd', (string) $a->id)
            ->set('planAdd', (string) $b->id)
            ->assertSet('planStudents', [$a->id, $b->id])
            ->set('planName', 'Разговорный клуб')
            ->set('planDate', '2026-09-28')
            ->set('planTime', '17:00')
            ->set('planDays', [1])
            ->call('addPlanSlot')
            ->call('togglePlanDay', 4, 0)
            ->set('planSlots.0.time', '18:00')
            ->call('savePlan')
            ->assertHasNoErrors();

        $room = Room::where('name', 'Разговорный клуб')->first();
        $this->assertSame('group', $room->type);
        $this->assertCount(2, $room->participants);
        $this->assertEqualsCanonicalizing(['17:00', '18:00'], $room->schedules->map(fn ($s) => substr($s->recurrence_time, 0, 5))->all());
    }

    public function test_once_plan_without_days(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);

        Livewire::actingAs($teacher)
            ->test(Schedule::class)
            ->call('openPlan')
            ->set('planStudentId', (string) $student->id)
            ->set('planName', 'Пробное занятие')
            ->set('planRepeat', 'once')
            ->set('planDays', [])
            ->set('planDate', '2026-09-27')
            ->set('planTime', '11:00')
            ->call('savePlan')
            ->assertHasNoErrors();

        $schedule = Room::where('name', 'Пробное занятие')->first()->schedules->first();
        $this->assertSame('once', $schedule->type);
        $this->assertSame('2026-09-27 11:00', $schedule->scheduled_at->format('Y-m-d H:i'));
    }
}
