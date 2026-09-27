<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Review;
use App\Livewire\ImageAnnotator;
use App\Models\Homework;
use App\Models\HomeworkActivity;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Notifications\HomeworkGraded;
use App\Notifications\HomeworkRevisionRequested;
use App\Services\HomeworkSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Проверка работы в кабинете учителя (/cabinet/teacher/tasks/review/{submission}). */
class TeacherReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Homework $homework;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('s3');

        $this->teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $this->student = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $this->homework = Homework::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Тест по Present Perfect',
            'description' => '<p>Поставьте глаголы в Present Perfect.</p>',
            'is_visible' => true,
            'max_score' => 10,
            'deadline' => now()->addDay(),
        ]);
        $this->homework->students()->attach($this->student->id);
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

    private function submission(array $attrs = [], ?Homework $homework = null, ?User $student = null): HomeworkSubmission
    {
        return HomeworkSubmission::create(array_merge([
            'homework_id' => ($homework ?? $this->homework)->id,
            'student_id' => ($student ?? $this->student)->id,
            'content' => '<p>Мария, добрый вечер! Отправляю тест.</p><script>alert(1)</script>',
            'attachments' => ['homework-submissions/1/page1.jpg', 'homework-submissions/1/page2.png', 'homework-submissions/1/notes.pdf'],
            'submitted_at' => now()->subDays(2),
            'status' => HomeworkSubmission::STATUS_SUBMITTED,
        ], $attrs));
    }

    public function test_access(): void
    {
        $s = $this->submission();

        $this->get(route('cabinet.teacher.review', $s))->assertRedirect();
        $this->actingAs($this->student)->get(route('cabinet.teacher.review', $s))->assertRedirect(route('cabinet.student.home'));
        $this->actingAs($this->user(User::ROLE_TUTOR))->get(route('cabinet.teacher.review', $s))->assertNotFound();

        $draft = $this->submission(['submitted_at' => null, 'status' => HomeworkSubmission::STATUS_PENDING], null, $this->user(User::ROLE_STUDENT));
        $this->actingAs($this->teacher)->get(route('cabinet.teacher.review', $draft))->assertNotFound();
    }

    public function test_page_shows_answer_task_and_queue(): void
    {
        $s = $this->submission();
        $other = $this->submission(['submitted_at' => now()->subHour()], null, $this->user(User::ROLE_STUDENT, 'Иван Петров'));
        $this->homework->students()->attach($other->student_id);

        $this->actingAs($this->teacher)
            ->get(route('cabinet.teacher.review', $s))
            ->assertOk()
            ->assertSee('<title>Тест по Present Perfect — ', false)
            ->assertSee('Алина Смирнова · сдано вовремя')
            ->assertSee('ждёт 2 дня')
            ->assertSee('Работа 1 из 2')
            ->assertSee(route('cabinet.teacher.review', $other), false)
            ->assertSee('Мария, добрый вечер! Отправляю тест.')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Фото 1')
            ->assertSee('Фото 2')
            ->assertSee('Файл 3')
            ->assertSee('Поставьте глаголы в Present Perfect.')
            ->assertSee('от 1 до 10 баллов')
            ->assertSee('Принять и оценить')
            ->assertSee('Вернуть на доработку');
    }

    public function test_accept_with_points_on_task_scale(): void
    {
        $s = $this->submission();

        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s])
            ->call('accept')
            ->assertHasErrors(['grade' => 'required'])
            ->set('grade', 11)
            ->call('accept')
            ->assertHasErrors(['grade' => 'max'])
            ->call('pickGrade', 8)
            ->set('comment', "Хорошая работа!\nОбратите внимание на 3-е.")
            ->set('picked', [UploadedFile::fake()->create('разбор.pdf', 50, 'application/pdf')])
            ->call('accept')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Работа принята')
            ->assertSee('Работа принята')
            ->assertSee('Оценка 8/10')
            ->assertSee('Изменить оценку');

        $s->refresh();
        $this->assertSame(8, (int) $s->grade);
        $this->assertSame(HomeworkSubmission::STATUS_GRADED, $s->status);
        $this->assertSame("<p>Хорошая работа!<br>\nОбратите внимание на 3-е.</p>", $s->feedback);
        $this->assertCount(1, $s->feedback_attachments);
        $this->assertStringStartsWith('feedback-attachments/' . $this->teacher->id . '/', $s->feedback_attachments[0]);
        $this->assertSame(10, $this->homework->fresh()->max_score);
        $this->assertTrue(HomeworkActivity::where('submission_id', $s->id)->where('type', HomeworkActivity::TYPE_GRADED)->exists());
        Notification::assertSentTo($this->student, HomeworkGraded::class);
    }

    public function test_change_grade_keeps_rich_comment(): void
    {
        $s = $this->submission(['grade' => 6, 'feedback' => '<p><strong>Неплохо</strong></p>', 'status' => HomeworkSubmission::STATUS_GRADED]);

        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s])
            ->assertDontSee('Вернуть на доработку')
            ->call('edit')
            ->assertSet('grade', 6)
            ->assertSet('comment', 'Неплохо')
            ->assertSee('Сохранить оценку')
            ->assertDontSee('Вернуть на доработку')
            ->call('pickGrade', 7)
            ->call('accept')
            ->assertHasNoErrors();

        $s->refresh();
        $this->assertSame(7, (int) $s->grade);
        $this->assertSame('<p><strong>Неплохо</strong></p>', $s->feedback);
    }

    public function test_give_back_requires_comment(): void
    {
        $s = $this->submission(['feedback_attachments' => ['homework-submissions/1/page1.jpg']]);

        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s])
            ->call('giveBack')
            ->assertHasErrors(['comment' => 'required']);

        $this->assertSame(HomeworkSubmission::STATUS_SUBMITTED, $s->fresh()->status);
        Notification::assertNothingSent();

        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s])
            ->set('comment', 'Исправьте 3-е и 7-е')
            ->call('giveBack')
            ->assertHasNoErrors()
            ->assertSee('Вернули на доработку')
            ->assertSee('На доработке')
            ->assertSee('Исправьте 3-е и 7-е');

        $s->refresh();
        $this->assertSame(HomeworkSubmission::STATUS_REVISION_REQUESTED, $s->status);
        $this->assertSame('<p>Исправьте 3-е и 7-е</p>', $s->feedback);
        $this->assertSame(['homework-submissions/1/page1.jpg'], $s->feedback_attachments); // фото с пометками остаются
        Notification::assertSentTo($this->student, HomeworkRevisionRequested::class);

        // На доработке оценить нельзя
        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s])
            ->assertDontSee('Принять и оценить')
            ->set('grade', 5)
            ->call('accept');
        $this->assertNull($s->fresh()->grade);
    }

    public function test_several_photos_open_from_list_and_return_to_it(): void
    {
        $s = $this->submission();

        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s])
            ->call('annotate', 1)
            ->assertSet('annotating', 'homework-submissions/1/page2.png')
            ->assertSee('Все фото')
            ->call('showPhotos')
            ->assertSet('annotating', null)
            ->assertSet('photoList', true)
            ->assertSee('Открыть фото 1 для пометок')
            ->call('annotate', 0)
            ->assertSet('photoList', false)
            ->assertSet('annotating', 'homework-submissions/1/page1.jpg')
            // Сохранили пометки — снова список фото
            ->dispatch('imageAnnotated', path: 'homework-submissions/1/page1.jpg')
            ->assertSet('photoList', true)
            ->call('closeAnnotator')
            ->assertSet('photoList', false);

        // Одно фото — без списка и без «Все фото»
        $s->update(['attachments' => ['homework-submissions/1/page1.jpg']]);
        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s->fresh()])
            ->call('annotate', 0)
            ->assertDontSee('Все фото')
            ->call('showPhotos')
            ->assertSet('photoList', false);
    }

    public function test_annotating_photo_keeps_original_and_saves_marks_separately(): void
    {
        $s = $this->submission(['file_names' => ['homework-submissions/1/page1.jpg' => 'Тетрадь, стр. 1.jpg']]);
        Storage::disk('s3')->put('homework-submissions/1/page1.jpg', 'original');

        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s])
            ->call('annotate', 0)
            ->assertSet('annotating', 'homework-submissions/1/page1.jpg')
            ->assertSee('Пометки · Тетрадь, стр. 1.jpg')
            ->assertSee('увидит пометки, когда вы проверите работу')
            ->assertSee('Сохранить пометки')
            ->call('annotate', 5) // не фото этой работы
            ->assertSet('annotating', null);

        // Холст ImageAnnotator (встроенный) сохраняет пометки отдельным файлом, оригинал цел
        Livewire::actingAs($this->teacher)
            ->test(ImageAnnotator::class, ['imagePath' => 'homework-submissions/1/page1.jpg', 'submissionId' => $s->id])
            ->assertSee('Карандаш')
            ->call('saveAnnotatedImage', 'data:image/png;base64,' . base64_encode('annotated'))
            ->assertDispatched('imageAnnotated', path: 'homework-submissions/1/page1.jpg');

        $s->refresh();
        $marked = $s->annotations['homework-submissions/1/page1.jpg'];
        $this->assertStringStartsWith('feedback-attachments/' . $this->teacher->id . '/', $marked);
        $this->assertStringEndsWith('_marks.png', $marked);
        $this->assertSame('original', Storage::disk('s3')->get('homework-submissions/1/page1.jpg'));
        $this->assertSame('annotated', Storage::disk('s3')->get($marked));
        $this->assertSame(['homework-submissions/1/page1.jpg'], $s->annotated_files);
        $this->assertSame([$marked], $s->feedback_attachments);
        $this->assertSame('Тетрадь, стр. 1 с пометками.png', $s->file_names[$marked]);
        $this->assertTrue(HomeworkActivity::where('submission_id', $s->id)->where('type', HomeworkActivity::TYPE_ANNOTATED)->exists());

        // Учитель сразу видит фото с пометками; повторно открытое фото — с прежними пометками
        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s])
            ->call('annotate', 0)
            ->dispatch('imageAnnotated', path: 'homework-submissions/1/page1.jpg')
            ->assertSet('annotating', null)
            ->assertDispatched('toast', message: 'Пометки сохранены')
            ->assertSee('Есть пометки')
            ->assertSee(basename($marked));

        Livewire::actingAs($this->teacher)
            ->test(ImageAnnotator::class, ['imagePath' => 'homework-submissions/1/page1.jpg', 'submissionId' => $s->id])
            ->assertSee(basename($marked))
            ->call('saveAnnotatedImage', 'data:image/png;base64,' . base64_encode('annotated twice'));

        // Повторные пометки — в тот же файл
        $s->refresh();
        $this->assertSame([$marked], $s->feedback_attachments);
        $this->assertSame('annotated twice', Storage::disk('s3')->get($marked));
        $this->assertSame('original', Storage::disk('s3')->get('homework-submissions/1/page1.jpg'));
    }

    public function test_marks_note_after_check(): void
    {
        $s = $this->submission(['grade' => 8, 'status' => HomeworkSubmission::STATUS_GRADED]);

        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s])
            ->call('annotate', 0)
            ->assertSee('увидит пометки сразу после сохранения')
            ->assertDontSee('когда вы проверите работу');
    }

    public function test_feedback_files_keep_original_names(): void
    {
        $s = $this->submission();

        Livewire::actingAs($this->teacher)
            ->test(Review::class, ['submission' => $s])
            ->set('comment', 'Посмотрите разбор')
            ->set('picked', [UploadedFile::fake()->create('Разбор ошибок.pdf', 50, 'application/pdf')])
            ->call('giveBack')
            ->assertHasNoErrors();

        $s->refresh();
        $this->assertCount(1, $s->feedback_attachments);
        $this->assertSame('Разбор ошибок.pdf', $s->file_names[$s->feedback_attachments[0]]);

        // Ученик видит исходное имя
        $this->actingAs($this->student)
            ->get(route('cabinet.student.task', $this->homework))
            ->assertSee('Разбор ошибок.pdf');
    }

    public function test_foreign_teacher_cannot_annotate(): void
    {
        $s = $this->submission();
        Storage::disk('s3')->put('homework-submissions/1/page1.jpg', 'original');

        Livewire::actingAs($this->user(User::ROLE_TUTOR))
            ->test(ImageAnnotator::class)
            ->call('openAnnotator', 'homework-submissions/1/page1.jpg', $s->id)
            ->assertSet('showModal', false)
            ->call('saveAnnotatedImage', 'data:image/png;base64,' . base64_encode('hacked'));

        $this->assertSame('original', Storage::disk('s3')->get('homework-submissions/1/page1.jpg'));
        $this->assertNull($s->fresh()->annotated_files);
    }

    public function test_service_grade_can_change_max_score_like_old_cabinet(): void
    {
        $s = $this->submission();

        app(HomeworkSubmissionService::class)->grade($s->load('homework', 'student'), 18, '<p>Отлично</p>', 20);

        $this->assertSame(20, $this->homework->fresh()->max_score);
        $this->assertSame(18, (int) $s->fresh()->grade);
        Notification::assertSentTo($this->student, HomeworkGraded::class);
    }
}
