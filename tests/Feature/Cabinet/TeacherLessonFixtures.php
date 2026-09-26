<?php

namespace Tests\Feature\Cabinet;

use App\Models\MeetingSession;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\Tariff;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Support\Carbon;

/** Общие данные для тестов занятий учителя (Сегодня, Расписание, Занятие). Время зафиксировано: чт, 24 сентября 2026, 12:00. */
trait TeacherLessonFixtures
{
    protected function fixNow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00'));
        SubscriptionService::flushCanStartCache();
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    /** Учитель с активной подпиской (занятия можно начинать). */
    protected function teacher(bool $subscribed = true): User
    {
        $teacher = $this->user(User::ROLE_TUTOR);

        if ($subscribed) {
            $this->seed(TariffSeeder::class);
            SubscriptionService::activate($teacher, Tariff::where('slug', 'master')->first(), unlimited: true, price: 0);
            SubscriptionService::flushCanStartCache();
        }

        return $teacher;
    }

    protected function studentOf(User $teacher, string $name = 'Алина Смирнова'): User
    {
        $student = $this->user(User::ROLE_STUDENT, ['name' => $name]);
        $teacher->students()->attach($student->id);

        return $student;
    }

    protected function room(User $teacher, array $students = [], array $attributes = []): Room
    {
        $room = Room::create($attributes + [
            'user_id' => $teacher->id,
            'name' => 'Английский язык',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
        $room->participants()->attach(collect($students)->pluck('id')->all());
        $room->updateQuietly(['type' => count($students) > 1 ? 'group' : 'individual']);

        return $room->fresh();
    }

    protected function onceAt(Room $room, string $at, int $duration = 60): RoomSchedule
    {
        return $room->schedules()->create([
            'type' => 'once',
            'scheduled_at' => Carbon::parse($at),
            'start_date' => Carbon::parse($at)->toDateString(),
            'duration_minutes' => $duration,
            'is_active' => true,
        ]);
    }

    protected function weeklyAt(Room $room, array $days, string $time, int $duration = 60): RoomSchedule
    {
        return $room->schedules()->create([
            'type' => 'recurring',
            'recurrence_type' => 'weekly',
            'recurrence_days' => $days,
            'recurrence_time' => $time,
            'start_date' => '2026-09-01',
            'duration_minutes' => $duration,
            'is_active' => true,
        ]);
    }

    protected function completedSession(Room $room, string $start, int $minutes, array $attended, array $extra = []): MeetingSession
    {
        $participants = $room->participants->map(fn (User $u) => [
            'user_id' => $u->id,
            'name' => $u->name,
            'price' => 1500,
            'attended' => in_array($u->id, $attended, true),
        ])->all();

        return MeetingSession::create($extra + [
            'user_id' => $room->user_id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'started_at' => Carbon::parse($start),
            'ended_at' => Carbon::parse($start)->addMinutes($minutes),
            'status' => 'completed',
            'participant_count' => count($attended) + 1,
            'pricing_snapshot' => ['payment_type' => 'per_lesson', 'participants' => $participants],
        ]);
    }
}
