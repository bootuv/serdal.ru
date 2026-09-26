<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Task;
use App\Livewire\Cabinet\Teacher\Tasks;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Room;
use App\Models\User;
use App\Notifications\HomeworkGraded;
use App\Notifications\HomeworkRevisionRequested;
use App\Notifications\HomeworkSubmitted;
use App\Notifications\NewHomework;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Экран задания учителя (/cabinet/teacher/tasks/{homework}) и ссылки на него. */
class TeacherTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('s3');

        $this->teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
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

    private function homework(string $title, array $students, array $attrs = [], ?User $teacher = null): Homework
    {
        $h = Homework::create(array_merge([
            'teacher_id' => ($teacher ?? $this->teacher)->id,
            'title' => $title,
            'is_visible' => true,
            'max_score' => 10,
        ], $attrs));
        $h->students()->attach(collect($students)->pluck('id'));

        return $h;
    }

    private function submit(Homework $h, User $student, array $attrs = []): HomeworkSubmission
    {
        return HomeworkSubmission::create(array_merge([
            'homework_id' => $h->id,
            'student_id' => $student->id,
            'content' => '<p>Ответ</p>',
            'submitted_at' => now()->subHour(),
            'status' => HomeworkSubmission::STATUS_SUBMITTED,
        ], $attrs));
    }

    public function test_access_only_own_task(): void
    {
        $h = $this->homework('Тест по Present Perfect', []);
        $foreign = $this->homework('Чужое задание', [], [], $this->user(User::ROLE_TUTOR));

        $this->get(route('cabinet.teacher.task', $h))->assertRedirect();
        $this->actingAs($this->user(User::ROLE_STUDENT))->get(route('cabinet.teacher.task', $h))->assertRedirect(route('cabinet.student.home'));
        $this->actingAs($this->teacher)->get(route('cabinet.teacher.task', $foreign))->assertNotFound();
        $this->actingAs($this->teacher)->get(route('cabinet.teacher.task', $h))->assertOk()->assertSee('<title>Тест по Present Perfect — ', false);
    }

    public function test_students_with_statuses(): void
    {
        $late = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $revision = $this->user(User::ROLE_STUDENT, 'Борис Ким');
        $graded = $this->user(User::ROLE_STUDENT, 'Вера Орлова');
        $missing = $this->user(User::ROLE_STUDENT, 'Глеб Петров');

        $h = $this->homework('Задачи на проценты', [$late, $revision, $graded, $missing], [
            'deadline' => now()->subDays(2)->setTime(20, 0),
            'description' => '<p>Решите задачи 1–10.</p>',
            'attachments' => ['homework-attachments/1/abc123_1700000000.pdf'],
            'file_names' => ['homework-attachments/1/abc123_1700000000.pdf' => 'Сборник задач.pdf'],
        ]);
        $s1 = $this->submit($h, $late, ['submitted_at' => now()->subDay()]);
        $this->submit($h, $revision, ['status' => HomeworkSubmission::STATUS_REVISION_REQUESTED, 'feedback' => '<p>Исправьте</p>', 'submitted_at' => now()->subDays(3)]);
        $s3 = $this->submit($h, $graded, ['status' => HomeworkSubmission::STATUS_GRADED, 'grade' => 8, 'submitted_at' => now()->subDays(3)]);

        $this->actingAs($this->teacher)
            ->get(route('cabinet.teacher.task', $h))
            ->assertOk()
            ->assertSee('4 ученика')
            ->assertSee('срок был')
            ->assertSee('сдали 3 из 4')
            ->assertSeeInOrder(['Алина Смирнова', 'Позже срока', 'Борис Ким', 'На доработке', 'Глеб Петров', 'Просрочено', 'Вера Орлова', 'Оценка 8/10'])
            ->assertSee(route('cabinet.teacher.review', $s1), false)
            ->assertSee(route('cabinet.teacher.review', $s3), false)
            ->assertSee('Проверить')
            ->assertSee('Решите задачи 1–10.')
            ->assertSee('Сборник задач.pdf')
            ->assertSee(route('cabinet.teacher.task-new', ['edit' => $h->id]), false);
    }

    public function test_no_deadline_and_draft(): void
    {
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $ivan = $this->user(User::ROLE_STUDENT, 'Иван Петров');
        $h = $this->homework('Эссе', [$alina, $ivan]);
        $this->submit($h, $ivan);

        Livewire::actingAs($this->teacher)
            ->test(Task::class, ['homework' => $h])
            ->assertSee('без срока')
            ->assertSeeInOrder(['Иван Петров', 'На проверке', 'Алина Смирнова', 'Не сдано']);

        $draft = $this->homework('Черновик', [$alina], ['is_visible' => false]);

        Livewire::actingAs($this->teacher)
            ->test(Task::class, ['homework' => $draft])
            ->assertSee('черновик — ученики пока не видят')
            ->assertSee('пока не видит задание')
            ->assertDontSee('Проверить');
    }

    public function test_delete_with_confirmation(): void
    {
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $h = $this->homework('Слова к разделу 4', [$alina]);
        $s = $this->submit($h, $alina);

        Livewire::actingAs($this->teacher)
            ->test(Task::class, ['homework' => $h])
            ->assertDontSee('Удалить задание?')
            ->set('confirmDelete', true)
            ->assertSee('Удалить задание?')
            ->call('delete')
            ->assertRedirect(route('cabinet.teacher.tasks'));

        $this->assertModelMissing($h);
        $this->assertModelMissing($s);
    }

    public function test_foreign_task_cannot_be_opened_or_deleted(): void
    {
        $foreign = $this->homework('Чужое задание', [], [], $this->user(User::ROLE_TUTOR));

        Livewire::actingAs($this->teacher)
            ->test(Task::class, ['homework' => $foreign])
            ->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_tasks_list_leads_to_task_screen_and_filters(): void
    {
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $ivan = $this->user(User::ROLE_STUDENT, 'Иван Петров');
        $room = Room::create(['user_id' => $this->teacher->id, 'name' => 'Английский B1', 'meeting_id' => 'm-' . uniqid(), 'moderator_pw' => 'mod', 'attendee_pw' => 'att']);
        $other = Room::create(['user_id' => $this->teacher->id, 'name' => 'Математика', 'meeting_id' => 'm-' . uniqid(), 'moderator_pw' => 'mod', 'attendee_pw' => 'att']);

        $group = $this->homework('Вариант ЕГЭ № 12', [$alina, $ivan], ['room_id' => $room->id]);
        $this->homework('Логарифмы: 20 задач', [$ivan], ['room_id' => $other->id]);

        Livewire::actingAs($this->teacher)
            ->test(Tasks::class)
            ->set('tab', 'all')
            ->assertSee(route('cabinet.teacher.task', $group), false)
            ->assertSee('Все занятия')
            ->set('roomId', (string) $room->id)
            ->assertSee('Вариант ЕГЭ № 12')
            ->assertDontSee('Логарифмы')
            ->set('roomId', '')
            ->set('search', 'логариф')
            ->assertSee('Логарифмы: 20 задач')
            ->assertDontSee('Вариант ЕГЭ № 12')
            ->set('search', 'нет такого')
            ->assertSee('Ничего не нашлось');
    }

    public function test_tasks_list_shows_more_by_pages(): void
    {
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        foreach (range(1, 32) as $i) {
            $this->homework('Задание ' . $i, [$alina]);
        }

        Livewire::actingAs($this->teacher)
            ->test(Tasks::class)
            ->set('tab', 'all')
            ->assertSee('Показать ещё 2')
            ->call('showMore')
            ->assertDontSee('Показать ещё');

        Livewire::actingAs($this->teacher)
            ->test(Tasks::class)
            ->set('tab', 'issued')
            ->assertSee('Показать ещё 2');
    }

    public function test_notifications_lead_to_new_cabinet(): void
    {
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $h = $this->homework('Эссе', [$alina]);
        $s = $this->submit($h, $alina);
        $url = fn ($n, $user) => $n->toDatabase($user)['actions'][0]['url'];

        $this->assertSame(route('cabinet.teacher.review', $s), $url(new HomeworkSubmitted($h, $alina, $s), $this->teacher));
        $this->assertSame(route('cabinet.teacher.review', $s), $url(new HomeworkSubmitted($h, $alina), $this->teacher));
        $this->assertSame(route('cabinet.student.task', $h), $url(new NewHomework($h), $alina));
        $this->assertSame(route('cabinet.student.task', $h), $url(new HomeworkGraded($h, 8), $alina));
        $this->assertSame(route('cabinet.student.task', $h), $url(new HomeworkRevisionRequested($h, '<p>Исправьте</p>'), $alina));
    }
}
