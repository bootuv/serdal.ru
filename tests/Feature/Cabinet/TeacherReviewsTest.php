<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Reviews;
use App\Models\Review;
use App\Models\User;
use App\Notifications\TeacherReportedReview;
use App\Services\TeacherReviewsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Отзывы учеников в новом кабинете учителя (/cabinet/teacher/reviews). */
class TeacherReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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

    private function review(User $teacher, User $student, int $rating, string $text, array $attrs = []): Review
    {
        return Review::create($attrs + [
            'teacher_id' => $teacher->id,
            'user_id' => $student->id,
            'rating' => $rating,
            'text' => $text,
        ]);
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.teacher.reviews'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.teacher.reviews'))
            ->assertRedirect(route('cabinet.student.home'));
    }

    public function test_empty_state(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.teacher.reviews'))
            ->assertOk()
            ->assertSee('Пока нет отзывов')
            ->assertSee('Попросить учеников об отзыве');
    }

    public function test_new_and_all_reviews_with_summary(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $pavel = $this->user(User::ROLE_STUDENT, 'Павел Ким');
        $this->review($teacher, $alina, 5, 'Перестала бояться говорить');
        $this->review($teacher, $pavel, 4, 'Разобрали вторую часть', ['teacher_read_at' => now()->subDay()]);
        $this->review($teacher, $pavel, 1, 'Скрытый модератором', ['is_rejected' => true]);
        $this->review($this->user(User::ROLE_TUTOR), $alina, 5, 'Отзыв другому учителю');

        $this->assertSame(1, app(TeacherReviewsService::class)->unreadCount($teacher));

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.reviews'))
            ->assertOk()
            ->assertSee('Видны на вашей странице')
            ->assertSee('Новые')
            ->assertSee('Перестала бояться говорить')
            ->assertSee('Все отзывы')
            ->assertSee('Разобрали вторую часть')
            ->assertSee('Ваша оценка')
            ->assertSee('4,5')
            ->assertSee('2 отзыва')
            ->assertDontSee('Скрытый модератором')
            ->assertDontSee('Отзыв другому учителю');
    }

    public function test_reading_marks_review_as_read(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $review = $this->review($teacher, $this->user(User::ROLE_STUDENT, 'Алина Смирнова'), 5, 'Длинный отзыв');
        $foreign = $this->review($this->user(User::ROLE_TUTOR), $this->user(User::ROLE_STUDENT), 5, 'Чужой');

        Livewire::actingAs($teacher)->test(Reviews::class)
            ->call('read', $review->id)
            ->assertSet('openId', $review->id)
            ->assertSee('Поделиться')
            ->call('share', $review->id)
            ->assertSee('Поделиться отзывом')
            ->assertSee(route('reviews.share-card', $review), false)
            ->assertSee('Скачать картинку');

        $this->assertNotNull($review->fresh()->teacher_read_at);

        Livewire::actingAs($teacher)->test(Reviews::class)
            ->call('read', $foreign->id)
            ->assertNotFound();
        $this->assertNull($foreign->fresh()->teacher_read_at);
    }

    public function test_report_notifies_admins(): void
    {
        Notification::fake();
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->user(User::ROLE_TUTOR);
        $review = $this->review($teacher, $this->user(User::ROLE_STUDENT, 'Иван Петров'), 2, 'Переносили три раза');

        Livewire::actingAs($teacher)->test(Reviews::class)
            ->call('askReport', $review->id)
            ->assertSee('Пожаловаться на отзыв')
            ->assertSee('Что не так с отзывом')
            ->assertSee('Это не мой ученик')
            // Без причины не отправляется
            ->call('sendReport')
            ->assertHasErrors(['reportReason' => 'required'])
            ->assertSee('Выберите причину')
            // «Другое» — нужно коротко описать
            ->set('reportReason', 'other')
            ->assertSee('Опишите коротко')
            ->call('sendReport')
            ->assertHasErrors(['reportNote' => 'required_if'])
            ->set('reportNote', 'Ученик перепутал меня с другим учителем')
            ->call('sendReport')
            ->assertHasNoErrors()
            ->assertSet('reportId', null)
            ->assertDispatched('toast', message: 'Жалоба отправлена')
            ->assertSee('Жалоба на проверке');

        $review->refresh();
        $this->assertTrue((bool) $review->is_reported);
        $this->assertSame('other', $review->report_reason);
        $this->assertSame('Ученик перепутал меня с другим учителем', $review->report_note);
        $this->assertNotNull($review->reported_at);
        Notification::assertSentTo($admin, TeacherReportedReview::class, function (TeacherReportedReview $n) use ($admin) {
            $body = $n->toDatabase($admin)['body'];

            return str_contains($body, 'Причина: Другое') && str_contains($body, 'перепутал меня');
        });
    }

    public function test_report_with_reason_without_note(): void
    {
        Notification::fake();
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->user(User::ROLE_TUTOR);
        $review = $this->review($teacher, $this->user(User::ROLE_STUDENT, 'Иван Петров'), 1, 'Незнакомый ученик');

        Livewire::actingAs($teacher)->test(Reviews::class)
            ->call('askReport', $review->id)
            ->set('reportReason', 'stranger')
            ->call('sendReport')
            ->assertHasNoErrors();

        $this->assertSame('stranger', $review->fresh()->report_reason);
        $this->assertSame('Это не мой ученик', $review->fresh()->report_reason_label);
        $this->assertNull($review->fresh()->report_note);
        Notification::assertSentTo($admin, TeacherReportedReview::class,
            fn (TeacherReportedReview $n) => str_contains($n->toDatabase($admin)['body'], 'Причина: Это не мой ученик'));

        // Админ видит причину в списке отзывов
        $this->actingAs($admin)->get(route('cabinet.admin.reviews'))
            ->assertOk()
            ->assertSee('Это не мой ученик');
    }

    public function test_share_marks_review_as_read(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $desktop = $this->review($teacher, $this->user(User::ROLE_STUDENT, 'Алина Смирнова'), 5, 'Спасибо за занятия');
        $phone = $this->review($teacher, $this->user(User::ROLE_STUDENT, 'Павел Ким'), 5, 'Всё понятно');

        Livewire::actingAs($teacher)->test(Reviews::class)
            ->assertSee('Скопировать текст')
            ->call('share', $desktop->id)
            ->assertSee('Поделиться отзывом')
            ->call('shared', $phone->id);

        $this->assertNotNull($desktop->fresh()->teacher_read_at);
        $this->assertNotNull($phone->fresh()->teacher_read_at);
    }

    public function test_search_by_student_name(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $pavel = $this->user(User::ROLE_STUDENT, 'Павел Ким');
        $this->review($teacher, $pavel, 5, 'Отзыв Павла', ['teacher_read_at' => now()]);
        foreach (range(1, 11) as $i) {
            $this->review($teacher, $this->user(User::ROLE_STUDENT, 'Ученица ' . $i), 5, 'Отзыв номер ' . $i, ['teacher_read_at' => now()]);
        }

        Livewire::actingAs($teacher)->test(Reviews::class)
            ->assertSee('Поиск по имени ученика')
            ->set('search', 'Павел')
            ->assertSee('Отзыв Павла')
            ->assertDontSee('Отзыв номер 3')
            ->set('search', 'Никого нет')
            ->assertSee('Ничего не нашлось')
            ->assertSee('Сбросить поиск');
    }
}
