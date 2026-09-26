<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Students;
use App\Mail\StudentInvitation;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Notifications\NewTeacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Ученики учителя в новом кабинете (/cabinet/teacher/students). */
class TeacherStudentsTest extends TestCase
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

    private function studentOf(User $teacher, array $attrs = []): User
    {
        $student = $this->user(User::ROLE_STUDENT, $attrs);
        $teacher->students()->attach($student->id);

        return $student;
    }

    private function room(User $teacher, string $name, array $students = [], array $attrs = []): Room
    {
        $room = Room::create(array_merge([
            'user_id' => $teacher->id,
            'name' => $name,
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'type' => count($students) > 1 ? 'group' : 'individual',
        ], $attrs));
        $room->participants()->attach(collect($students)->pluck('id')->all());
        $room->updateQuietly(['type' => count($students) > 1 ? 'group' : 'individual']);

        return $room;
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

    public function test_guest_is_redirected_and_student_is_forbidden(): void
    {
        $this->get(route('cabinet.teacher.students'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.teacher.students'))
            ->assertRedirect(route('cabinet.student.home'));
    }

    public function test_teacher_without_students_sees_empty_state(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.teacher.students'))
            ->assertOk()
            ->assertSee('Учеников пока нет')
            ->assertSee('Найти на Serdal');
    }

    public function test_teacher_sees_only_own_students_with_lessons_and_debts(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $alina = $this->studentOf($teacher, ['name' => 'Алина Смирнова']);
        $pavel = $this->studentOf($teacher, ['name' => 'Павел Ким']);
        $this->studentOf($teacher, ['name' => 'Вера Кузнецова']);
        $other = $this->studentOf($this->user(User::ROLE_TUTOR), ['name' => 'Чужой Ученик']);

        $this->room($teacher, 'Английский язык', [$alina], ['next_start' => now()->addDay()->setTime(16, 0), 'duration' => 60]);
        $this->record($teacher, $pavel, ['due_date' => now()->subDays(2)]);
        $this->record($teacher, $alina);

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.students'))
            ->assertOk()
            ->assertSee('Алина Смирнова')
            ->assertSee('Павел Ким')
            ->assertSee('Английский язык')
            ->assertSee('Завтра в 16:00')
            ->assertSee('Ждут оплаты')
            ->assertSee('Просрочено')
            ->assertSee('Не назначено')
            ->assertDontSee('Чужой Ученик')
            ->assertDontSee($other->email);
    }

    public function test_filters_and_search(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $alina = $this->studentOf($teacher, ['name' => 'Алина Смирнова']);
        $this->studentOf($teacher, ['name' => 'Вера Кузнецова', 'phone' => '+79161234567']);
        $this->room($teacher, 'Английский язык', [$alina]);
        $this->record($teacher, $alina);

        Livewire::actingAs($teacher)->test(Students::class)
            ->set('filter', 'debt')
            ->assertSee('Алина Смирнова')
            ->assertDontSee('Вера Кузнецова')
            ->set('filter', 'none')
            ->assertSee('Вера Кузнецова')
            ->set('filter', 'all')
            ->set('search', '1234567')
            ->assertSee('Вера Кузнецова')
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('name')->all() === ['Вера Кузнецова']);
    }

    public function test_groups_tab_lists_group_lessons(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $a = $this->studentOf($teacher, ['name' => 'Иван Петров', 'first_name' => 'Иван']);
        $b = $this->studentOf($teacher, ['name' => 'Софья Новикова', 'first_name' => 'Софья']);
        $this->room($teacher, 'ЕГЭ-2027', [$a, $b]);

        Livewire::actingAs($teacher)->test(Students::class)
            ->set('tab', 'groups')
            ->assertSee('ЕГЭ-2027')
            ->assertSee('Иван, Софья');
    }

    public function test_mark_paid_and_undo_from_focus_block(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $pavel = $this->studentOf($teacher, ['name' => 'Павел Ким']);
        $record = $this->record($teacher, $pavel, ['due_date' => now()->subDay()]);

        $component = Livewire::actingAs($teacher)->test(Students::class)
            ->call('markPaid', $pavel->id)
            ->assertDispatched('toast')
            ->assertSee('Оплачено')
            ->assertSee('Отменить');

        $this->assertSame(PaymentRecord::STATUS_PAID, $record->fresh()->status);

        $component->call('undoPaid', $pavel->id);
        $this->assertSame(PaymentRecord::STATUS_UNPAID, $record->fresh()->status);
    }

    public function test_several_debts_open_selection_window(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $alina = $this->studentOf($teacher, ['name' => 'Алина Смирнова']);
        $first = $this->record($teacher, $alina);
        $second = $this->record($teacher, $alina, ['due_date' => now()->addDays(5)]);

        Livewire::actingAs($teacher)->test(Students::class)
            ->call('markPaid', $alina->id)
            ->assertSet('markStudentId', $alina->id)
            ->assertSee('оплатить до', false)
            ->set('markSelected', [$first->id])
            ->call('confirmMarkPaid');

        $this->assertSame(PaymentRecord::STATUS_PAID, $first->fresh()->status);
        $this->assertSame(PaymentRecord::STATUS_UNPAID, $second->fresh()->status);
    }

    public function test_cannot_touch_other_teachers_student(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $otherTeacher = $this->user(User::ROLE_TUTOR);
        $stranger = $this->studentOf($otherTeacher);
        $record = $this->record($otherTeacher, $stranger, ['due_date' => now()->subDay()]);

        Livewire::actingAs($teacher)->test(Students::class)->call('markPaid', $stranger->id);
        $this->assertSame(PaymentRecord::STATUS_UNPAID, $record->fresh()->status);

        Livewire::actingAs($teacher)->test(Students::class)->call('openExtend', $stranger->id)->assertStatus(404);
    }

    public function test_extend_due_date(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $pavel = $this->studentOf($teacher, ['name' => 'Павел Ким']);
        $record = $this->record($teacher, $pavel, ['due_date' => now()->subDays(2)]);

        Livewire::actingAs($teacher)->test(Students::class)
            ->call('openExtend', $pavel->id)
            ->assertSee('Продлить срок оплаты')
            ->assertSee('считаем от сегодня')
            ->set('extendDays', 7)
            ->call('extend')
            ->assertDispatched('toast');

        $this->assertTrue($record->fresh()->due_date->isSameDay(today()->addDays(7)));
    }

    public function test_invite_by_email_and_link(): void
    {
        Mail::fake();
        $teacher = $this->user(User::ROLE_TUTOR);

        Livewire::actingAs($teacher)->test(Students::class)
            ->call('openInvite', 'link')
            ->assertSee('Ссылка-приглашение')
            ->assertSee('register/invite', false)
            ->call('sendInvite')
            ->assertHasErrors(['inviteEmail' => 'required'])
            ->set('inviteEmail', 'new.student@example.com')
            ->call('sendInvite')
            ->assertHasNoErrors()
            ->assertSet('inviteOpen', false)
            ->assertDispatched('toast');

        Mail::assertSent(StudentInvitation::class, fn (StudentInvitation $m) => $m->hasTo('new.student@example.com')
            && str_contains($m->link, 'teacher=' . $teacher->id));
    }

    public function test_find_and_add_registered_student(): void
    {
        Notification::fake();
        $teacher = $this->user(User::ROLE_TUTOR);
        $olga = $this->user(User::ROLE_STUDENT, ['name' => 'Ольга Белова', 'email' => 'o.belova@example.com']);

        Livewire::actingAs($teacher)->test(Students::class)
            ->call('openInvite', 'find')
            ->set('findQuery', 'Ольга')
            ->assertSee('o.belova@example.com')
            ->set('pickId', $olga->id)
            ->call('addStudent')
            ->assertSet('inviteOpen', false)
            ->assertDispatched('toast');

        $this->assertTrue($teacher->students()->whereKey($olga->id)->exists());
        Notification::assertSentTo($olga, NewTeacher::class);
    }

    public function test_invite_query_opens_invite_window(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);

        Livewire::actingAs($teacher)->withQueryParams(['invite' => 1])->test(Students::class)
            ->assertSet('inviteOpen', true)
            ->assertSee('Ссылка-приглашение');

        // Без параметра окно закрыто
        $this->actingAs($teacher)->get(route('cabinet.teacher.students'))->assertOk()->assertDontSee('Ссылка-приглашение');
    }

    public function test_mark_student_is_locked_and_must_be_own(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->studentOf($teacher);
        $this->record($teacher, $student);
        $this->record($teacher, $student, ['due_date' => now()->addDays(5)]);

        $stranger = $this->user(User::ROLE_STUDENT);

        $c = Livewire::actingAs($teacher)->test(Students::class)
            ->call('markPaid', $student->id)
            ->assertSet('markStudentId', $student->id);

        // Подменить ученика в окне с клиента нельзя
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        $c->set('markStudentId', $stranger->id);
    }

    public function test_mark_paid_for_foreign_student_is_404(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $stranger = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($teacher)->test(Students::class)
            ->call('markPaid', $stranger->id)
            ->assertStatus(404);
    }

    public function test_student_without_username_opens_by_id(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->studentOf($teacher, ['name' => 'Ученик Без Логина', 'username' => '']);

        $url = \App\Services\TeacherStudentsService::studentUrl($student);
        $this->assertStringEndsWith('/cabinet/teacher/students/' . $student->id, $url);

        Livewire::actingAs($teacher)->test(Students::class)->assertSee($url, false);

        $this->actingAs($teacher)->get($url)->assertOk()->assertSee('Ученик Без Логина');

        // Чужой ученик по id не открывается
        $stranger = $this->user(User::ROLE_STUDENT);
        $this->actingAs($teacher)->get(route('cabinet.teacher.student', ['student' => $stranger->id]))->assertNotFound();
    }

    public function test_remind_is_limited_to_once_a_day(): void
    {
        Notification::fake();
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->studentOf($teacher);
        $record = $this->record($teacher, $student, ['due_date' => now()->subDays(2)]);

        $c = Livewire::actingAs($teacher)->test(Students::class)
            ->assertSee('Напомнить')
            ->call('remind', $student->id)
            ->assertDispatched('toast', message: 'Напомнили');

        Notification::assertSentToTimes($student, \App\Notifications\PaymentReminder::class, 1);
        $this->assertNotNull($record->fresh()->reminded_at);

        $c->call('remind', $student->id)
            ->assertDispatched('toast', message: 'Уже напоминали за последние сутки — можно будет завтра');
        Notification::assertSentToTimes($student, \App\Notifications\PaymentReminder::class, 1);

        $this->travel(25)->hours();
        $c->call('remind', $student->id)->assertDispatched('toast', message: 'Напомнили');
        Notification::assertSentToTimes($student, \App\Notifications\PaymentReminder::class, 2);
    }
}
