<?php

namespace Tests\Feature;

use App\Models\RoomScheduleException;
use App\Models\User;
use App\Notifications\LessonStartingSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Cabinet\TeacherLessonFixtures;
use Tests\TestCase;

/** lessons:remind — учителю и ученикам за 15 минут до занятия по расписанию. */
class LessonRemindersTest extends TestCase
{
    use RefreshDatabase, TeacherLessonFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixNow(); // 2026-09-24 12:00
        Notification::fake();
    }

    public function test_teacher_and_students_are_reminded_once(): void
    {
        $teacher = $this->teacher(false);
        $alina = $this->studentOf($teacher);
        $pavel = $this->studentOf($teacher, 'Павел Ким');
        $room = $this->room($teacher, [$alina, $pavel]);
        $this->onceAt($room, '2026-09-24 12:10');

        $this->artisan('lessons:remind')->assertSuccessful();
        $this->artisan('lessons:remind')->assertSuccessful(); // повторный запуск — без дублей

        foreach ([$teacher, $alina, $pavel] as $user) {
            Notification::assertSentToTimes($user, LessonStartingSoon::class, 1);
        }

        $data = (new LessonStartingSoon($room, now()->setTime(12, 10)))->toDatabase($teacher);
        $this->assertSame('Скоро занятие', $data['title']);
        $this->assertSame('«Английский язык» сегодня в 12:10 — через 10 минут', $data['body']);
        $this->assertSame('Начать занятие', $data['action']);
        $this->assertSame(route('cabinet.teacher.lesson', $room), $data['url']);
        $this->assertSame(route('cabinet.student.lesson', $room), (new LessonStartingSoon($room, now()->setTime(12, 10)))->toDatabase($alina)['url']);
    }

    public function test_far_cancelled_and_running_lessons_are_skipped(): void
    {
        $teacher = $this->teacher(false);
        $alina = $this->studentOf($teacher);

        // Через полчаса — рано
        $this->onceAt($this->room($teacher, [$alina]), '2026-09-24 12:30');

        // Отменено
        $cancelledRoom = $this->room($teacher, [$alina]);
        $weekly = $this->weeklyAt($cancelledRoom, [4], '12:10'); // чт
        RoomScheduleException::create([
            'room_id' => $cancelledRoom->id, 'room_schedule_id' => $weekly->id,
            'original_date' => '2026-09-24', 'original_starts_at' => '2026-09-24 12:10:00',
            'status' => RoomScheduleException::STATUS_CANCELLED,
        ]);

        // Учитель уже начал
        $running = $this->room($teacher, [$alina]);
        $running->updateQuietly(['is_running' => true]);
        $this->onceAt($running, '2026-09-24 12:05');

        $this->artisan('lessons:remind')->assertSuccessful();

        Notification::assertNothingSentTo($teacher);
        Notification::assertNothingSentTo($alina);
    }

    public function test_weekly_lesson_later_today_is_not_reminded_hours_ahead(): void
    {
        $teacher = $this->teacher(false);
        $alina = $this->studentOf($teacher);
        $this->weeklyAt($this->room($teacher, [$alina]), [4], '20:55'); // чт, сегодня вечером

        $this->artisan('lessons:remind')->assertSuccessful();

        Notification::assertNothingSentTo($teacher);
        Notification::assertNothingSentTo($alina);
    }

    public function test_admins_are_not_reminded(): void
    {
        $teacher = $this->teacher(false);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'admin-one']);
        $room = $this->room($teacher, []);
        $room->participants()->attach($admin->id);
        $this->onceAt($room, '2026-09-24 12:10');

        $this->artisan('lessons:remind')->assertSuccessful();

        Notification::assertSentToTimes($teacher, LessonStartingSoon::class, 1);
        Notification::assertNothingSentTo($admin);
    }

    public function test_reminder_is_not_repeated_after_cache_is_cleared_on_deploy(): void
    {
        $teacher = $this->teacher(false);
        $room = $this->room($teacher, []);
        $this->onceAt($room, '2026-09-24 12:10');

        // Напоминание уже лежит в кабинете учителя, а деплой очистил кеш команды
        $teacher->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => LessonStartingSoon::class,
            'data' => (new LessonStartingSoon($room, now()->setTime(12, 10)))->toDatabase($teacher),
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->artisan('lessons:remind')->assertSuccessful();

        Notification::assertNothingSentTo($teacher);
    }

    public function test_moved_lesson_is_reminded_at_new_time(): void
    {
        $teacher = $this->teacher(false);
        $alina = $this->studentOf($teacher);
        $room = $this->room($teacher, [$alina]);
        $weekly = $this->weeklyAt($room, [4], '18:00'); // обычно чт 18:00
        RoomScheduleException::create([
            'room_id' => $room->id, 'room_schedule_id' => $weekly->id,
            'original_date' => '2026-09-24', 'original_starts_at' => '2026-09-24 18:00:00',
            'status' => RoomScheduleException::STATUS_MOVED, 'starts_at' => '2026-09-24 12:12:00',
        ]);

        $this->artisan('lessons:remind')->assertSuccessful();

        Notification::assertSentToTimes($alina, LessonStartingSoon::class, 1);
        Notification::assertSentToTimes($teacher, LessonStartingSoon::class, 1);
    }
}
