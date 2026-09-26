<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Tasks;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Задания ученика в новом кабинете (/cabinet/student/tasks). */
class StudentTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
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

    private function homework(User $teacher, User $student, array $attrs = []): Homework
    {
        $homework = Homework::create(array_merge([
            'teacher_id' => $teacher->id,
            'title' => 'Задание ' . uniqid(),
            'is_visible' => true,
        ], $attrs));
        $homework->students()->attach($student->id);

        return $homework;
    }

    private function submission(Homework $homework, User $student, array $attrs = []): HomeworkSubmission
    {
        return HomeworkSubmission::create(array_merge([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'content' => '<p>Ответ</p>',
            'submitted_at' => now()->subDay(),
            'status' => HomeworkSubmission::STATUS_SUBMITTED,
        ], $attrs));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cabinet.student.tasks'))->assertRedirect();
    }

    public function test_teacher_cannot_open_student_tasks(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.student.tasks'))
            ->assertRedirect(route('cabinet.teacher.today'));
    }

    public function test_empty_state(): void
    {
        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.student.tasks'))
            ->assertOk()
            ->assertSee('Заданий пока нет')
            ->assertDontSee('Всё сдано');
    }

    public function test_actual_tab_focuses_on_nearest_deadline(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $student = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Английский язык',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);

        $later = $this->homework($teacher, $student, ['title' => 'Задачи 14–20', 'deadline' => now()->addDays(5)]);
        $this->homework($teacher, $student, ['title' => 'Эссе «My last holiday»', 'deadline' => now()->addDay()->setTime(20, 0), 'room_id' => $room->id]);
        $revision = $this->homework($teacher, $student, ['title' => 'Словарный диктант', 'deadline' => now()->addDays(3)]);
        $this->submission($revision, $student, [
            'status' => HomeworkSubmission::STATUS_REVISION_REQUESTED,
            'feedback' => '<p>Потерян знак при переносе</p>',
        ]);
        $this->homework($teacher, $student, ['title' => 'Скрытое задание', 'is_visible' => false]);
        $this->homework($teacher, $this->user(User::ROLE_STUDENT), ['title' => 'Чужое задание']);
        $graded = $this->homework($teacher, $student, ['title' => 'Проверенная работа']);
        $this->submission($graded, $student, ['grade' => 7, 'status' => HomeworkSubmission::STATUS_GRADED]);

        $this->actingAs($student)
            ->get(route('cabinet.student.tasks'))
            ->assertOk()
            ->assertSee('Нужно сдать 3 задания')
            ->assertSeeInOrder(['Ближайший срок', 'сдать до завтра в 20:00', 'Эссе «My last holiday»', 'Английский язык', 'Сдать работу', 'Ещё нужно сдать', 'Словарный диктант', 'Потерян знак при переносе', 'На доработке', 'Задачи 14–20'])
            ->assertSee(route('cabinet.student.task', $later), false)
            ->assertSee('Успеваемость')
            ->assertDontSee('Скрытое задание')
            ->assertDontSee('Чужое задание')
            ->assertDontSee('Проверенная работа');
    }

    public function test_done_and_all_tabs(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);

        $review = $this->homework($teacher, $student, ['title' => 'Тест по Present Perfect']);
        $this->submission($review, $student);
        $graded = $this->homework($teacher, $student, ['title' => 'Задачи на дроби', 'max_score' => 10]);
        $this->submission($graded, $student, ['grade' => 7, 'status' => HomeworkSubmission::STATUS_GRADED]);
        $this->homework($teacher, $student, ['title' => 'Несданное']);

        $component = Livewire::actingAs($student)->test(Tasks::class)->set('tab', 'done');
        $component->assertSee('1 проверено · 1 ждёт проверки')->assertDontSee('Несданное');
        $this->assertInOrder($component->html(), ['Сданные работы', 'Тест по Present Perfect', 'На проверке', 'Задачи на дроби', 'Оценка 7/10']);

        $component->set('tab', 'all')->assertSee('3 задания');
        $this->assertInOrder($component->html(), ['Нужно сдать', 'Несданное', 'Сданные', 'Тест по Present Perfect', 'Задачи на дроби']);
    }

    private function assertInOrder(string $html, array $needles): void
    {
        $pos = 0;
        foreach ($needles as $needle) {
            $found = mb_strpos($html, $needle, $pos);
            $this->assertNotFalse($found, "Не найдено по порядку: {$needle}");
            $pos = $found + mb_strlen($needle);
        }
    }

    public function test_performance_switches_between_teachers(): void
    {
        $english = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $math = $this->user(User::ROLE_TUTOR, 'Иван Орлов');
        $student = $this->user(User::ROLE_STUDENT);
        $english->students()->attach($student->id);
        $math->students()->attach($student->id);

        $hw = $this->homework($math, $student, ['max_score' => 10]);
        $this->submission($hw, $student, ['grade' => 8, 'status' => HomeworkSubmission::STATUS_GRADED]);

        Livewire::actingAs($student)
            ->test(Tasks::class)
            ->assertSet('perfTeacherId', $math->id) // по алфавиту первым идёт Иван Орлов
            ->assertSee('Иван Орлов · за всё время')
            ->assertSee('80%')
            ->call('$set', 'perfTeacherId', (string) $english->id)
            ->assertSee('Мария Соколова · за всё время')
            ->assertSee('оценок пока нет');
    }

    public function test_filter_by_teacher(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $maria = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $ivan = $this->user(User::ROLE_TUTOR, 'Иван Орлов');
        $this->homework($maria, $student, ['title' => 'Эссе «My last holiday»']);
        $this->homework($ivan, $student, ['title' => 'Задачи 14–20 из сборника']);
        $done = $this->homework($ivan, $student, ['title' => 'Задачи на дроби']);
        $this->submission($done, $student);

        Livewire::actingAs($student)
            ->test(Tasks::class)
            ->assertSee('Все учителя')
            ->assertSee('Эссе «My last holiday»')
            ->assertSee('Задачи 14–20 из сборника')
            ->set('teacherId', (string) $ivan->id)
            ->assertDontSee('Эссе «My last holiday»')
            ->assertSee('Задачи 14–20 из сборника')
            ->set('tab', 'done')
            ->assertSee('Задачи на дроби')
            ->set('teacherId', (string) $maria->id)
            ->assertDontSee('Задачи на дроби');

        // Один учитель — фильтр не нужен
        $single = $this->user(User::ROLE_STUDENT);
        $this->homework($maria, $single);
        Livewire::actingAs($single)->test(Tasks::class)->assertDontSee('Все учителя');
    }
}
