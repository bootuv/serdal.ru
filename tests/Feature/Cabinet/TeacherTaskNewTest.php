<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\TaskNew;
use App\Livewire\Cabinet\Teacher\Tasks;
use App\Models\Homework;
use App\Models\Room;
use App\Models\User;
use App\Notifications\NewHomework;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Новое задание и изменение задания в кабинете учителя (/cabinet/teacher/tasks/new). */
class TeacherTaskNewTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $alina;

    private User $ivan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('s3');

        $this->teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $this->alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $this->ivan = $this->user(User::ROLE_STUDENT, 'Иван Петров');
        $this->teacher->students()->attach([$this->alina->id, $this->ivan->id]);
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

    private function room(array $participants): Room
    {
        $room = Room::create([
            'user_id' => $this->teacher->id,
            'name' => 'ЕГЭ-2027',
            'meeting_id' => 'm-' . uniqid(),
            'moderator_pw' => 'mod',
            'attendee_pw' => 'att',
        ]);
        $room->participants()->attach(collect($participants)->pluck('id'));

        return $room;
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.teacher.task-new'))->assertRedirect();

        $this->actingAs($this->alina)->get(route('cabinet.teacher.task-new'))->assertRedirect(route('cabinet.student.home'));

        $foreign = Homework::create(['teacher_id' => $this->user(User::ROLE_TUTOR)->id, 'title' => 'Чужое', 'is_visible' => true]);
        $this->actingAs($this->teacher)->get(route('cabinet.teacher.task-new', ['edit' => $foreign->id]))->assertNotFound();

        $this->actingAs($this->teacher)
            ->get(route('cabinet.teacher.task-new'))
            ->assertOk()
            ->assertSee('Новое задание')
            ->assertSee('Алина Смирнова')
            ->assertSee('Сохранить черновик');
    }

    public function test_teacher_issues_task_with_files_and_students_are_notified(): void
    {
        Livewire::actingAs($this->teacher)
            ->test(TaskNew::class)
            ->set('title', 'Эссе «My last holiday»')
            ->set('description', '<p onclick="alert(1)">Напишите эссе, 150–200 слов.</p><p>Используйте &lt;Past Simple&gt;.</p>')
            ->call('toggleStudent', $this->alina->id)
            ->assertSee('Получат: Алина Смирнова')
            ->set('date', now()->addDays(2)->format('Y-m-d'))
            ->set('time', '18:30')
            ->set('maxScore', 20)
            ->set('picked', [UploadedFile::fake()->create('Слова.pdf', 200, 'application/pdf')])
            ->assertHasNoErrors()
            ->assertSee('Слова.pdf')
            ->call('publish')
            ->assertHasNoErrors()
            ->assertRedirect(route('cabinet.teacher.tasks'));

        $h = Homework::firstOrFail();
        $this->assertSame($this->teacher->id, $h->teacher_id);
        $this->assertSame(Homework::TYPE_HOMEWORK, $h->type);
        $this->assertTrue($h->is_visible);
        $this->assertSame(20, $h->max_score);
        $this->assertSame('<p>Напишите эссе, 150–200 слов.</p><p>Используйте &lt;Past Simple&gt;.</p>', $h->description);
        $this->assertSame(now()->addDays(2)->format('Y-m-d') . ' 18:30', $h->deadline->format('Y-m-d H:i'));
        $this->assertSame([$this->alina->id], $h->students()->pluck('users.id')->all());
        $this->assertCount(1, $h->attachments);
        $this->assertStringStartsWith('homework-attachments/' . $this->teacher->id . '/', $h->attachments[0]);
        Storage::disk('s3')->assertExists($h->attachments[0]);

        Notification::assertSentTo($this->alina, NewHomework::class);
        Notification::assertNotSentTo($this->ivan, NewHomework::class);

        // Сообщение после сохранения показываем на списке заданий
        session()->flash('toast', 'Задание выдано');
        Livewire::actingAs($this->teacher)->test(Tasks::class)->assertDispatched('toast', message: 'Задание выдано');
    }

    public function test_draft_is_hidden_and_not_notified_until_published(): void
    {
        Livewire::actingAs($this->teacher)
            ->test(TaskNew::class)
            ->set('title', 'Слова к разделу 4')
            ->call('toggleStudent', $this->alina->id)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $h = Homework::firstOrFail();
        $this->assertFalse($h->is_visible);
        $this->assertNull($h->deadline);
        Notification::assertNothingSent();

        Livewire::withQueryParams(['edit' => $h->id])
            ->actingAs($this->teacher)
            ->test(TaskNew::class)
            ->assertSet('title', 'Слова к разделу 4')
            ->assertSee('Выдать задание')
            ->call('publish')
            ->assertHasNoErrors();

        $this->assertTrue($h->fresh()->is_visible);
        Notification::assertSentTo($this->alina, NewHomework::class);
    }

    public function test_room_fills_students(): void
    {
        $guest = $this->user(User::ROLE_STUDENT, 'Олег Васильев'); // участник занятия, но не в списке учеников учителя
        $room = $this->room([$this->ivan, $guest]);

        Livewire::actingAs($this->teacher)
            ->test(TaskNew::class)
            ->set('roomId', (string) $room->id)
            ->assertSet('studentIds', [$this->ivan->id, $guest->id])
            ->assertSee('Олег Васильев')
            ->set('title', 'Вариант ЕГЭ № 12')
            ->call('publish')
            ->assertHasNoErrors();

        $h = Homework::firstOrFail();
        $this->assertSame($room->id, $h->room_id);
        $this->assertEqualsCanonicalizing([$this->ivan->id, $guest->id], $h->students()->pluck('users.id')->all());
    }

    public function test_validation(): void
    {
        $stranger = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($this->teacher)
            ->test(TaskNew::class)
            ->call('publish')
            ->assertHasErrors(['title' => 'required', 'studentIds' => 'required'])
            ->set('title', 'Тест')
            ->set('studentIds', [$stranger->id])
            ->call('publish')
            ->assertHasErrors('studentIds.0')
            ->set('studentIds', [$this->alina->id])
            ->set('date', now()->subDay()->format('Y-m-d'))
            ->call('publish')
            ->assertHasErrors('date')
            ->set('date', '')
            ->set('maxScore', 0)
            ->call('publish')
            ->assertHasErrors('maxScore')
            ->set('maxScore', 10)
            ->set('picked', [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->assertHasErrors('picked.0')
            ->assertSet('files', []);

        $this->assertSame(0, Homework::count());
        Notification::assertNothingSent();
    }

    public function test_edit_keeps_rich_description_removes_files_and_notifies_new_students(): void
    {
        Storage::disk('s3')->put('homework-attachments/1/a.pdf', 'x');
        Storage::disk('s3')->put('homework-attachments/1/b.pdf', 'x');
        $h = Homework::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Логарифмы',
            'description' => '<p><strong>Решите</strong> 20 задач</p><ul><li>быстро</li></ul>',
            'is_visible' => true,
            'max_score' => 10,
            'attachments' => ['homework-attachments/1/a.pdf', 'homework-attachments/1/b.pdf'],
        ]);
        $h->students()->attach($this->alina->id);

        Livewire::withQueryParams(['edit' => $h->id])
            ->actingAs($this->teacher)
            ->test(TaskNew::class)
            ->assertSee('Изменить задание')
            ->assertDontSee('Сохранить черновик')
            ->assertSet('description', '<p><strong>Решите</strong> 20 задач</p><ul><li>быстро</li></ul>')
            ->set('title', 'Логарифмы: 20 задач')
            ->call('toggleStudent', $this->ivan->id)
            ->call('removeKept', 1)
            ->call('publish')
            ->assertHasNoErrors();

        $h->refresh();
        $this->assertSame('Логарифмы: 20 задач', $h->title);
        $this->assertSame('<p><strong>Решите</strong> 20 задач</p><ul><li>быстро</li></ul>', $h->description);
        $this->assertSame(['homework-attachments/1/a.pdf'], $h->attachments);
        Storage::disk('s3')->assertMissing('homework-attachments/1/b.pdf');
        Notification::assertSentTo($this->ivan, NewHomework::class);
        Notification::assertNotSentTo($this->alina, NewHomework::class);
    }

    public function test_delete(): void
    {
        $h = Homework::create(['teacher_id' => $this->teacher->id, 'title' => 'Удалить меня', 'is_visible' => true]);

        Livewire::withQueryParams(['edit' => $h->id])
            ->actingAs($this->teacher)
            ->test(TaskNew::class)
            ->set('confirmDelete', true)
            ->assertSee('Удалить задание?')
            ->call('delete')
            ->assertRedirect(route('cabinet.teacher.tasks'));

        $this->assertNull(Homework::find($h->id));
    }
}
