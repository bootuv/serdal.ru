<?php

namespace Tests\Feature;

use App\Livewire\Cabinet\Teacher\Student;
use App\Models\Room;
use App\Models\User;
use App\Notifications\TeacherAssignedLesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Окно «Занятия ученика» в карточке ученика кабинета учителя
 * (App\Livewire\Cabinet\Teacher\Student, логика — TeacherStudentsService::syncRooms).
 * Удаление ученика из списка — Tests\Feature\Cabinet\TeacherStudentTest::test_remove_from_list.
 */
class StudentAssignRoomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function makeTutor(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    protected function makeStudent(User $teacher): User
    {
        $student = User::factory()->create([
            'role' => 'student',
            'username' => 'student' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
        ]);

        $teacher->students()->attach($student->id);

        return $student;
    }

    protected function makeRoom(User $teacher, string $name): Room
    {
        return Room::create([
            'user_id' => $teacher->id,
            'name' => $name,
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'is_running' => false,
        ]);
    }

    public function test_student_without_rooms_can_be_assigned_to_several_rooms(): void
    {
        Notification::fake();

        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $first = $this->makeRoom($teacher, 'ЕГЭ-2027 группа-1');
        $second = $this->makeRoom($teacher, 'Литература');

        Livewire::actingAs($teacher)
            ->test(Student::class, ['student' => $student])
            ->assertSee('Пока ни одного занятия')
            ->call('openModal', 'assign')
            ->assertSet('roomIds', [])
            ->set('roomIds', [(string) $first->id, (string) $second->id])
            ->call('saveRooms')
            ->assertDispatched('toast', message: 'Занятия ученика сохранены')
            ->assertSee('ЕГЭ-2027 группа-1, Литература');

        $this->assertTrue($first->participants()->whereKey($student->id)->exists());
        $this->assertTrue($second->participants()->whereKey($student->id)->exists());
        $this->assertSame('individual', $first->fresh()->type);
        $this->assertSame('individual', $second->fresh()->type);

        Notification::assertSentToTimes($student, TeacherAssignedLesson::class, 2);
    }

    public function test_unchecking_room_removes_student_from_it(): void
    {
        Notification::fake();

        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $other = $this->makeStudent($teacher);
        $keep = $this->makeRoom($teacher, 'Остаётся');
        $drop = $this->makeRoom($teacher, 'Снимается');
        $keep->participants()->attach($student->id);
        $drop->participants()->attach([$student->id, $other->id]);
        $drop->updateQuietly(['type' => 'group']);

        Livewire::actingAs($teacher)
            ->test(Student::class, ['student' => $student])
            ->assertSee('Снимается')
            ->call('openModal', 'assign')
            ->set('roomIds', [(string) $keep->id])
            ->call('saveRooms')
            ->assertDispatched('toast', message: 'Занятия ученика сохранены');

        $this->assertTrue($keep->participants()->whereKey($student->id)->exists());
        $this->assertFalse($drop->participants()->whereKey($student->id)->exists());
        $this->assertTrue($drop->participants()->whereKey($other->id)->exists());
        $this->assertSame('individual', $drop->fresh()->type);

        Notification::assertNothingSent();
    }

    public function test_room_becomes_group_when_second_student_is_assigned(): void
    {
        Notification::fake();

        $teacher = $this->makeTutor();
        $first = $this->makeStudent($teacher);
        $second = $this->makeStudent($teacher);
        $room = $this->makeRoom($teacher, 'Литература');
        $room->participants()->attach($first->id);

        Livewire::actingAs($teacher)
            ->test(Student::class, ['student' => $second])
            ->call('openModal', 'assign')
            ->set('roomIds', [(string) $room->id])
            ->call('saveRooms');

        $this->assertSame(2, $room->participants()->count());
        $this->assertSame('group', $room->fresh()->type);
    }

    public function test_room_of_another_teacher_cannot_be_assigned(): void
    {
        Notification::fake();

        $teacher = $this->makeTutor();
        $other = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $foreignRoom = $this->makeRoom($other, 'Чужое занятие');
        $this->makeRoom($teacher, 'Своё занятие');

        Livewire::actingAs($teacher)
            ->test(Student::class, ['student' => $student])
            ->call('openModal', 'assign')
            ->assertSee('Своё занятие')
            ->assertDontSee('Чужое занятие')
            ->set('roomIds', [(string) $foreignRoom->id])
            ->call('saveRooms')
            ->assertDispatched('toast', message: 'Изменений нет');

        $this->assertFalse($foreignRoom->participants()->whereKey($student->id)->exists());
        Notification::assertNothingSent();
    }

    public function test_assigned_rooms_are_prechecked_in_the_modal(): void
    {
        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $assigned = $this->makeRoom($teacher, 'Индивидуально');
        $this->makeRoom($teacher, 'Другое занятие');
        $assigned->participants()->attach($student->id);

        Livewire::actingAs($teacher)
            ->test(Student::class, ['student' => $student])
            ->assertSee('Индивидуально')
            ->assertDontSee('Пока ни одного занятия')
            ->call('openModal', 'assign')
            ->assertSet('roomIds', [$assigned->id])
            ->assertSee('Другое занятие');
    }
}
