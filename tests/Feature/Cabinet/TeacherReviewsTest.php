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
            ->assertForbidden();
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
            ->call('sendReport')
            ->assertSet('reportId', null)
            ->assertDispatched('toast', message: 'Жалоба отправлена')
            ->assertSee('Жалоба на проверке');

        $this->assertTrue((bool) $review->fresh()->is_reported);
        Notification::assertSentTo($admin, TeacherReportedReview::class);
    }
}
