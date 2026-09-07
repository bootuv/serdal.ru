<?php

namespace Tests\Feature;

use App\Models\Homework;
use App\Models\Room;
use App\Models\User;
use App\Notifications\NewHomework;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RoomHomeworkSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeRoom(User $teacher): Room
    {
        return Room::create([
            'user_id' => $teacher->id,
            'name' => 'ЕГЭ-2027 группа-2',
            'meeting_id' => 'm-' . uniqid(),
            'moderator_pw' => 'mod',
            'attendee_pw' => 'att',
        ]);
    }

    /** @test */
    public function new_room_participants_are_attached_to_existing_room_homeworks()
    {
        Notification::fake();

        $teacher = User::factory()->create(['role' => 'tutor', 'username' => 'teacher_' . uniqid()]);
        $existing = User::factory()->create(['role' => 'student', 'username' => 'student_' . uniqid()]);
        $newcomer = User::factory()->create(['role' => 'student', 'username' => 'student_' . uniqid()]);

        $room = $this->makeRoom($teacher);
        $room->participants()->attach($existing->id);

        $homework = Homework::create([
            'teacher_id' => $teacher->id,
            'room_id' => $room->id,
            'title' => 'ДЗ №1',
            'is_visible' => true,
            'deadline' => now()->addWeek(),
        ]);
        $homework->students()->attach($existing->id);

        $hidden = Homework::create([
            'teacher_id' => $teacher->id,
            'room_id' => $room->id,
            'title' => 'Скрытое',
            'is_visible' => false,
        ]);

        $overdue = Homework::create([
            'teacher_id' => $teacher->id,
            'room_id' => $room->id,
            'title' => 'Просроченное',
            'is_visible' => true,
            'deadline' => now()->subDay(),
        ]);

        $unrelated = Homework::create([
            'teacher_id' => $teacher->id,
            'room_id' => null,
            'title' => 'Без занятия',
        ]);

        $room->participants()->attach($newcomer->id);
        $room->attachParticipantsToHomeworks([$newcomer->id, $existing->id]);

        // Новичок назначен на все задания занятия, включая скрытые и просроченные
        $this->assertTrue($homework->students()->where('users.id', $newcomer->id)->exists());
        $this->assertTrue($hidden->students()->where('users.id', $newcomer->id)->exists());
        $this->assertTrue($overdue->students()->where('users.id', $newcomer->id)->exists());
        $this->assertFalse($unrelated->students()->where('users.id', $newcomer->id)->exists());

        // Существующий ученик не дублируется
        $this->assertSame(1, $homework->students()->where('users.id', $existing->id)->count());

        // Уведомление только о видимом и не просроченном задании, только новичку
        Notification::assertSentTo($newcomer, NewHomework::class, fn ($n) => $n->homework->is($homework));
        Notification::assertNotSentTo($newcomer, NewHomework::class, fn ($n) => $n->homework->is($hidden));
        Notification::assertNotSentTo($newcomer, NewHomework::class, fn ($n) => $n->homework->is($overdue));
        Notification::assertNotSentTo($existing, NewHomework::class);

        // Ученик видит задание в своём кабинете
        $this->actingAs($newcomer);
        $visibleIds = \App\Filament\Student\Resources\HomeworkResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($homework->id, $visibleIds);
        $this->assertNotContains($hidden->id, $visibleIds);
    }

    /** @test */
    public function backfill_migration_attaches_missing_room_participants()
    {
        $teacher = User::factory()->create(['role' => 'tutor', 'username' => 'teacher_' . uniqid()]);
        $student = User::factory()->create(['role' => 'student', 'username' => 'student_' . uniqid()]);

        $room = $this->makeRoom($teacher);
        $homework = Homework::create([
            'teacher_id' => $teacher->id,
            'room_id' => $room->id,
            'title' => 'ДЗ',
        ]);
        $room->participants()->attach($student->id);

        $this->assertFalse($homework->students()->where('users.id', $student->id)->exists());

        $migration = require database_path('migrations/2026_09_07_120000_backfill_homework_students_from_room_participants.php');
        $migration->up();
        $migration->up(); // идемпотентно

        $this->assertSame(1, $homework->students()->where('users.id', $student->id)->count());
    }
}
