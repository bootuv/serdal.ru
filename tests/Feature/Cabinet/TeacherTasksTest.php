<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Tasks;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Задания учителя в новом кабинете (/cabinet/teacher/tasks). */
class TeacherTasksTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();

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

    public function test_access(): void
    {
        $this->get(route('cabinet.teacher.tasks'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.teacher.tasks'))
            ->assertForbidden();
    }

    public function test_review_queue_oldest_first_with_one_check_button(): void
    {
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $ivan = $this->user(User::ROLE_STUDENT, 'Иван Петров');

        $test = $this->homework('Тест по Present Perfect', [$alina], ['deadline' => now()->subDays(3)]);
        $late = $this->homework('Задачи на проценты', [$ivan], ['deadline' => now()->subDays(2)]);
        $fresh = $this->homework('Вариант ЕГЭ № 12', [$alina, $ivan]);

        $this->submit($test, $alina, ['submitted_at' => now()->subDays(4)]);
        $this->submit($late, $ivan, ['submitted_at' => now()->subDay()]);
        $this->submit($fresh, $alina, ['submitted_at' => now()->subMinutes(5)]);
        $this->submit($fresh, $ivan, ['status' => HomeworkSubmission::STATUS_GRADED, 'grade' => 8]); // уже проверено

        // Чужое задание не показываем
        $other = $this->user(User::ROLE_TUTOR);
        $foreign = $this->homework('Чужое задание', [$alina], [], $other);
        $this->submit($foreign, $alina);

        $this->actingAs($this->teacher)
            ->get(route('cabinet.teacher.tasks'))
            ->assertOk()
            ->assertSee('<title>Задания — ', false)
            ->assertSee('Выдать задание')
            ->assertSeeInOrder(['Тест по Present Perfect', 'ждёт 4 дня', 'Проверить', 'Задачи на проценты', 'Позже срока', 'Вариант ЕГЭ № 12'])
            ->assertDontSee('Чужое задание');
    }

    public function test_waiting_block_shows_revision_and_pending(): void
    {
        $pavel = $this->user(User::ROLE_STUDENT, 'Павел Ким');
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');

        $ineq = $this->homework('Неравенства с модулем', [$pavel]);
        $this->submit($ineq, $pavel, ['status' => HomeworkSubmission::STATUS_REVISION_REQUESTED, 'feedback' => '<p>Исправьте</p>']);
        $this->homework('Эссе «My last holiday»', [$alina], ['deadline' => now()->addDay()->setTime(20, 0)]);
        $this->homework('Черновик для Алины', [$alina], ['is_visible' => false]);

        Livewire::actingAs($this->teacher)
            ->test(Tasks::class)
            ->assertSee('Ждём от учеников')
            ->assertSeeInOrder(['Неравенства с модулем', 'На доработке', 'Эссе «My last holiday»', 'до завтра, 20:00'])
            ->assertDontSee('Черновик для Алины')
            ->assertSee('Пока пусто — сданные работы учеников появятся здесь.');
    }

    public function test_issued_and_all_tabs(): void
    {
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $ivan = $this->user(User::ROLE_STUDENT, 'Иван Петров');
        $room = Room::create(['user_id' => $this->teacher->id, 'name' => 'ЕГЭ-2027', 'type' => 'group', 'meeting_id' => 'm-' . uniqid(), 'moderator_pw' => 'mod', 'attendee_pw' => 'att']);
        $room->participants()->attach([$alina->id, $ivan->id]);
        $room->updateQuietly(['type' => 'group']); // тип занятия по числу участников (RoomObserver)

        $group = $this->homework('Вариант ЕГЭ № 12', [$alina, $ivan], ['room_id' => $room->id, 'deadline' => now()->addDays(10)]);
        $this->submit($group, $alina);
        $this->homework('Логарифмы: 20 задач', [$ivan], ['deadline' => now()->subDays(2)]);
        $this->homework('Слова к разделу 4', [$alina], ['is_visible' => false]);
        $done = $this->homework('Квадратные уравнения', [$ivan]);
        $this->submit($done, $ivan, ['status' => HomeworkSubmission::STATUS_GRADED, 'grade' => 7]);

        $component = Livewire::actingAs($this->teacher)->test(Tasks::class)
            ->set('tab', 'issued')
            ->assertSee('Группа «ЕГЭ-2027»')
            ->assertSee('1 из 2')
            ->assertSee('Просрочено')
            ->assertSee('срок был')
            ->assertSee('Черновик')
            ->assertSee('пока не видно ученику')
            ->assertDontSee('Квадратные уравнения');

        $component->set('tab', 'all')
            ->assertSee('Квадратные уравнения')
            ->assertSee('Оценка 7/10')
            ->set('studentId', (string) $alina->id)
            ->assertSee('Вариант ЕГЭ № 12')
            ->assertDontSee('Квадратные уравнения')
            ->assertDontSee('Логарифмы');
    }

    public function test_empty_state(): void
    {
        Livewire::actingAs($this->teacher)
            ->test(Tasks::class)
            ->set('tab', 'issued')
            ->assertSee('Заданий пока нет');
    }
}
