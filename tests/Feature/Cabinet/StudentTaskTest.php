<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Task;
use App\Models\Homework;
use App\Models\HomeworkActivity;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Notifications\HomeworkSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Задание ученика в новом кабинете (/cabinet/student/tasks/{homework}). */
class StudentTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('s3');

        $this->teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $this->student = $this->user(User::ROLE_STUDENT);
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

    private function homework(array $attrs = [], ?User $student = null): Homework
    {
        $homework = Homework::create(array_merge([
            'teacher_id' => $this->teacher->id,
            'title' => 'Эссе «My last holiday»',
            'description' => '<p>Напишите эссе о каникулах.</p><ul><li>Past Simple</li></ul><script>alert(1)</script>',
            'is_visible' => true,
            'deadline' => now()->addDay()->setTime(20, 0),
        ], $attrs));
        $homework->students()->attach(($student ?? $this->student)->id);

        return $homework;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cabinet.student.task', $this->homework()))->assertRedirect();
    }

    public function test_teacher_cannot_open_student_task(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('cabinet.student.task', $this->homework()))
            ->assertForbidden();
    }

    public function test_student_cannot_open_foreign_or_hidden_task(): void
    {
        $foreign = $this->homework([], $this->user(User::ROLE_STUDENT));
        $hidden = $this->homework(['is_visible' => false]);

        $this->actingAs($this->student)->get(route('cabinet.student.task', $foreign))->assertNotFound();
        $this->actingAs($this->student)->get(route('cabinet.student.task', $hidden))->assertNotFound();
    }

    public function test_student_sees_task_with_safe_description_and_teacher_files(): void
    {
        $homework = $this->homework(['attachments' => ['homework-attachments/1/sample.pdf']]);

        $this->actingAs($this->student)
            ->get(route('cabinet.student.task', $homework))
            ->assertOk()
            ->assertSee('<title>Эссе «My last holiday» — ', false)
            ->assertSee('сдать до завтра в 20:00')
            ->assertSee('Напишите эссе о каникулах.')
            ->assertSee('<ul class="flex list-disc', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Файл 1')
            ->assertSee('sample.pdf', false)
            ->assertSee('Отправить на проверку');
    }

    public function test_student_submits_answer_with_files(): void
    {
        $homework = $this->homework();

        Livewire::actingAs($this->student)
            ->test(Task::class, ['homework' => $homework])
            ->set('answer', "Last summer I went to Kazan.\n\nIt was <great>.")
            ->set('picked', [UploadedFile::fake()->create('Тетрадь.pdf', 120, 'application/pdf')])
            ->assertHasNoErrors()
            ->set('picked', [UploadedFile::fake()->image('photo.jpg', 2400, 1600)])
            ->assertSee('Тетрадь.pdf')
            ->assertSee('photo.jpg')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Работа отправлена учителю')
            ->assertSee('Готово, учитель проверит')
            ->assertSee('Отправлено')
            ->assertDontSee('Отправить на проверку');

        $submission = HomeworkSubmission::where('homework_id', $homework->id)->where('student_id', $this->student->id)->firstOrFail();
        $this->assertSame(HomeworkSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertNotNull($submission->submitted_at);
        $this->assertSame('<p>Last summer I went to Kazan.</p><p>It was &lt;great&gt;.</p>', $submission->content);
        $this->assertCount(2, $submission->attachments);
        foreach ($submission->attachments as $path) {
            $this->assertStringStartsWith('homework-submissions/' . $this->student->id . '/', $path);
            Storage::disk('s3')->assertExists($path);
        }

        $this->assertTrue(HomeworkActivity::where('submission_id', $submission->id)->where('type', HomeworkActivity::TYPE_SUBMITTED)->exists());
        Notification::assertSentTo($this->teacher, HomeworkSubmitted::class);
    }

    public function test_wrong_file_type_and_empty_answer_are_rejected(): void
    {
        $homework = $this->homework();

        Livewire::actingAs($this->student)
            ->test(Task::class, ['homework' => $homework])
            ->set('picked', [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->assertHasErrors('picked.0')
            ->assertSet('files', [])
            ->call('submit')
            ->assertHasErrors('answer');

        $this->assertFalse(HomeworkSubmission::where('homework_id', $homework->id)->exists());
        Notification::assertNothingSent();
    }

    public function test_student_resubmits_after_revision(): void
    {
        $homework = $this->homework();
        Storage::disk('s3')->put('homework-submissions/old/keep.jpg', 'x');
        Storage::disk('s3')->put('homework-submissions/old/drop.pdf', 'x');
        $submission = HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'student_id' => $this->student->id,
            'content' => '<p>Первый вариант</p>',
            'attachments' => ['homework-submissions/old/keep.jpg', 'homework-submissions/old/drop.pdf'],
            'annotated_files' => ['homework-submissions/old/keep.jpg'],
            'submitted_at' => now()->subDay(),
            'status' => HomeworkSubmission::STATUS_SUBMITTED,
        ]);
        $submission->update(['status' => HomeworkSubmission::STATUS_REVISION_REQUESTED, 'feedback' => '<p>Добавьте вывод</p>']);

        Livewire::actingAs($this->student)
            ->test(Task::class, ['homework' => $homework])
            ->assertSee('На доработке')
            ->assertSee('Добавьте вывод')
            ->assertSee('С пометками учителя')
            ->assertSet('answer', 'Первый вариант')
            ->set('answer', "Первый вариант\nС выводом")
            ->call('removeKept', 1)
            ->call('submit')
            ->assertHasNoErrors();

        $submission->refresh();
        $this->assertSame(HomeworkSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame(['homework-submissions/old/keep.jpg'], $submission->attachments);
        $this->assertSame('<p>Первый вариант<br>' . "\n" . 'С выводом</p>', $submission->content);
        Storage::disk('s3')->assertMissing('homework-submissions/old/drop.pdf');
        $this->assertTrue(HomeworkActivity::where('submission_id', $submission->id)->where('type', HomeworkActivity::TYPE_RESUBMITTED)->exists());
        Notification::assertSentTo($this->teacher, HomeworkSubmitted::class);
    }

    public function test_answer_cannot_be_changed_while_on_review_or_after_grading(): void
    {
        $homework = $this->homework(['max_score' => 10]);
        $submission = HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'student_id' => $this->student->id,
            'content' => '<p>Мой ответ</p>',
            'submitted_at' => now()->subDay(),
            'status' => HomeworkSubmission::STATUS_SUBMITTED,
        ]);

        Livewire::actingAs($this->student)
            ->test(Task::class, ['homework' => $homework])
            ->assertSee('На проверке')
            ->assertDontSee('Отправить на проверку')
            ->set('answer', 'Подмена')
            ->call('submit');

        $this->assertSame('<p>Мой ответ</p>', $submission->fresh()->content);
        Notification::assertNothingSent();

        $submission->update(['grade' => 7, 'feedback' => '<p>Хорошо</p>', 'status' => HomeworkSubmission::STATUS_GRADED]);

        $this->actingAs($this->student)
            ->get(route('cabinet.student.task', $homework))
            ->assertOk()
            ->assertSee('Оценка 7/10')
            ->assertSee('Работа проверена')
            ->assertSee('Хорошо')
            ->assertSee('Мой ответ')
            ->assertDontSee('Отправить на проверку');
    }
}
