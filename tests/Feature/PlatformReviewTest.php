<?php

namespace Tests\Feature;

use App\Livewire\Cabinet\Admin\Reviews as AdminReviews;
use App\Livewire\Cabinet\Teacher\PlatformReview;
use App\Models\MeetingSession;
use App\Models\Review;
use App\Models\User;
use App\Notifications\PlatformReviewSubmitted;
use App\Services\AdminInboxService;
use App\Services\PlatformReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Cabinet\TeacherLessonFixtures;
use Tests\TestCase;

/** Отзывы учителей о платформе: приглашение, форма, проверка админом, публикация на /reviews. */
class PlatformReviewTest extends TestCase
{
    use RefreshDatabase, TeacherLessonFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->fixNow(); // 2026-09-24 12:00
    }

    /** Учитель на платформе с $days дней, провёл $lessons занятий. */
    private function seasonedTeacher(int $lessons = 5, int $days = 20): User
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['first_name' => 'Мария', 'last_name' => 'Соколова']);
        $teacher->forceFill(['created_at' => now()->subDays($days)])->saveQuietly();
        $room = $this->room($teacher, [$this->user(User::ROLE_STUDENT)]);
        for ($i = 0; $i < $lessons; $i++) {
            MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id,
                'status' => 'completed', 'started_at' => now()->subDays($i + 1), 'ended_at' => now()->subDays($i + 1)->addHour(), 'participant_count' => 2]);
        }

        return $teacher->fresh();
    }

    public function test_prompt_appears_after_lessons_and_can_be_dismissed(): void
    {
        $service = app(PlatformReviewService::class);
        $this->assertFalse($service->shouldPrompt($this->seasonedTeacher(lessons: 3)), 'мало занятий');
        $this->assertFalse($service->shouldPrompt($this->seasonedTeacher(days: 5)), 'на платформе меньше двух недель');

        $teacher = $this->seasonedTeacher();
        $this->assertTrue($service->shouldPrompt($teacher));

        Livewire::actingAs($teacher)->test(PlatformReview::class, ['prompt' => true])
            ->assertSee('Как вам Serdal?')
            ->call('dismiss')
            ->assertDontSee('Как вам Serdal?');
        $this->assertFalse($service->shouldPrompt($teacher->fresh()), 'закрыл — больше не спрашиваем');

        // В профиле оставить отзыв можно всегда: звезда сразу открывает форму с этой оценкой
        Livewire::actingAs($teacher->fresh())->test(PlatformReview::class)
            ->assertSee('Отзыв о Serdal')->assertSee('Оцените от 1 до 5')
            ->set('promptRating', 4)
            ->assertSet('open', true)->assertSet('rating', 4)->assertSet('promptRating', 0);
    }

    public function test_review_goes_to_moderation_then_to_public_page(): void
    {
        Notification::fake();
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->seasonedTeacher();

        Livewire::actingAs($teacher)->test(PlatformReview::class, ['prompt' => true])
            ->call('openForm')
            ->set('text', 'Коротко')
            ->call('save')->assertHasErrors('text')
            ->set('rating', 5)
            ->set('text', 'Всё расписание и задания в одном месте, ученики не теряются.')
            ->call('save')->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Спасибо! Отзыв появится на сайте после проверки')
            ->assertDontSee('Как вам Serdal?');

        $review = Review::platform()->sole();
        $this->assertNull($review->teacher_id);
        $this->assertNull($review->approved_at);
        Notification::assertSentTo($admin, PlatformReviewSubmitted::class);
        $this->assertSame(1, app(AdminInboxService::class)->counts()['reviews']);

        // До проверки на сайте нет
        $this->get(route('reviews'))->assertOk()->assertDontSee('ученики не теряются');

        // Админ: вкладка «О платформе», другие вкладки — только отзывы об учителях
        Livewire::actingAs($admin)->test(AdminReviews::class)->set('tab', 'all')->assertDontSee('ученики не теряются');
        Livewire::actingAs($admin)->test(AdminReviews::class)->set('tab', 'platform')
            ->assertSee('Соколова Мария → Serdal')->assertSee('Ждёт проверки')
            ->call('open', $review->id)->assertSee('Опубликовать')
            ->call('approve');

        $this->assertNotNull($review->fresh()->approved_at);
        $this->get(route('reviews'))->assertOk()->assertSee('ученики не теряются')->assertSee('Учитель');
        $this->get(route('reviews', ['role' => 'tutor']))->assertOk()->assertSee('ученики не теряются');
        $this->get(route('reviews', ['role' => 'student']))->assertOk()->assertDontSee('ученики не теряются');
        $this->assertStringContainsString('ученики не теряются', $this->getJson(route('reviews.load-more', ['offset' => 0, 'role' => 'tutor']))->assertOk()->json('html'));
        $this->assertStringNotContainsString('ученики не теряются', $this->getJson(route('reviews.load-more', ['offset' => 0, 'role' => 'student']))->json('html'));

        // Учитель видит статус; изменённый отзыв снова уходит на проверку
        Livewire::actingAs($teacher)->test(PlatformReview::class)->assertSee('На сайте')
            ->call('openForm')->assertSet('text', 'Всё расписание и задания в одном месте, ученики не теряются.')
            ->set('text', 'Всё расписание и задания в одном месте. Добавьте, пожалуйста, мобильное приложение.')
            ->call('save')->assertSee('На проверке');
        $this->assertNull($review->fresh()->approved_at);
        $this->get(route('reviews'))->assertDontSee('мобильное приложение');
    }

    public function test_private_review_is_seen_only_by_team(): void
    {
        Notification::fake();
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->seasonedTeacher();

        Livewire::actingAs($teacher)->test(PlatformReview::class)
            ->call('openForm')->set('rating', 3)->set('text', 'Иногда долго грузятся записи занятий.')->set('showOnSite', false)
            ->call('save')->assertDispatched('toast', message: 'Спасибо за отзыв!')
            ->assertSee('Виден только команде Serdal');

        $this->assertSame(0, app(AdminInboxService::class)->platformReviewsPending());
        Livewire::actingAs($admin)->test(AdminReviews::class)->set('tab', 'platform')
            ->assertSee('Только для команды')
            ->call('open', Review::platform()->value('id'))->assertDontSee('Опубликовать');
        $this->get(route('reviews'))->assertDontSee('долго грузятся');
    }
}
