<?php

namespace Tests\Feature;

use App\Filament\App\Resources\StudentResource\Pages\ListStudents;
use App\Models\Room;
use App\Models\User;
use App\Notifications\TeacherAssignedLesson;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class StudentAssignRoomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('app'));
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

    public function test_student_without_rooms_sees_assign_link_and_can_be_assigned_to_several_rooms(): void
    {
        Notification::fake();

        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $first = $this->makeRoom($teacher, 'ЕГЭ-2027 группа-1');
        $second = $this->makeRoom($teacher, 'Литература');

        Livewire::actingAs($teacher)
            ->test(ListStudents::class)
            ->assertSee('Назначить занятие')
            ->callTableAction('assign_room', $student, data: ['room_ids' => [$first->id, $second->id]])
            ->assertHasNoTableActionErrors()
            ->assertSee('ЕГЭ-2027 группа-1')
            ->assertSee('Литература');

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
            ->test(ListStudents::class)
            ->assertSee('Снимается')
            ->mountTableAction('assign_room', $student)
            // setTableActionData сливает массив с предзаполненным поэлементно, поэтому задаём поле напрямую
            ->set('mountedTableActionsData.0.room_ids', [$keep->id])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

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
            ->test(ListStudents::class)
            ->callTableAction('assign_room', $second, data: ['room_ids' => [$room->id]])
            ->assertHasNoTableActionErrors();

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
            ->test(ListStudents::class)
            ->callTableAction('assign_room', $student, data: ['room_ids' => [$foreignRoom->id]]);

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
            ->test(ListStudents::class)
            ->assertSee('Индивидуально')
            ->assertDontSee('Назначить занятие')
            // Окно открывает сам тег занятия, а не вся ячейка
            ->assertSeeHtml("wire:click.stop.prevent=\"mountTableAction('assign_room', '{$student->id}')\"")
            ->mountTableAction('assign_room', $student)
            ->assertTableActionDataSet(['room_ids' => [$assigned->id]]);
    }

    public function test_delete_from_list_action_removes_student_from_teacher_and_rooms(): void
    {
        Notification::fake();

        $teacher = $this->makeTutor();
        $student = $this->makeStudent($teacher);
        $room = $this->makeRoom($teacher, 'Химия ЕГЭ');
        $room->participants()->attach($student->id);

        Livewire::actingAs($teacher)
            ->test(ListStudents::class)
            ->assertTableActionExists('delete_from_list')
            ->callTableAction('delete_from_list', $student)
            ->assertHasNoTableActionErrors()
            ->assertDontSee($student->name);

        $this->assertFalse($teacher->students()->whereKey($student->id)->exists());
        $this->assertFalse($room->participants()->whereKey($student->id)->exists());

        Notification::assertSentTo($student, \App\Notifications\TeacherRemoved::class);
    }
}
