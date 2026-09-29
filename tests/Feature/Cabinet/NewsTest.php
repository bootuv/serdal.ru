<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\News;
use App\Livewire\Cabinet\NewsBanner;
use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Новости от администрации в кабинетах учителя и ученика: список, чтение, счётчик, карточка важной на главной. */
class NewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        Notification::fake();
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'username' => $role . uniqid(), 'is_active' => true, 'is_blocked' => false, 'is_profile_completed' => true], $attrs));
    }

    private function news(array $attrs = []): Announcement
    {
        return Announcement::create($attrs + ['title' => 'Новость', 'audience' => Announcement::AUDIENCE_TEACHERS, 'published_at' => now(), 'notified_at' => now()]);
    }

    public function test_teacher_sees_only_own_audience_and_reads(): void
    {
        $tutor = $this->user(User::ROLE_TUTOR);
        $forTeachers = $this->news(['title' => 'Для учителей', 'body' => '<p>Подробности внутри</p>']);
        $this->news(['title' => 'Для всех', 'audience' => Announcement::AUDIENCE_ALL]);
        $this->news(['title' => 'Только ученикам', 'audience' => Announcement::AUDIENCE_STUDENTS]);
        $this->news(['title' => 'Черновик', 'published_at' => null]);
        $this->news(['title' => 'Завтра', 'published_at' => now()->addDay()]);

        $this->actingAs($tutor)->get(route('cabinet.teacher.news'))->assertOk()
            ->assertSee('Для учителей')->assertSee('Для всех')
            ->assertDontSee('Только ученикам')->assertDontSee('Черновик')->assertDontSee('Завтра')
            ->assertSee('2 непрочитанные');
        $this->assertSame(2, app(AnnouncementService::class)->unreadCount($tutor));

        $this->get(route('cabinet.teacher.news-item', $forTeachers))->assertOk()->assertSee('Подробности внутри');
        $this->assertSame(1, app(AnnouncementService::class)->unreadCount($tutor));

        Livewire::actingAs($tutor)->test(News::class)->call('readAll');
        $this->assertSame(0, app(AnnouncementService::class)->unreadCount($tutor));
    }

    public function test_student_sees_student_news(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $news = $this->news(['title' => 'Каникулы', 'audience' => Announcement::AUDIENCE_STUDENTS]);
        $this->news(['title' => 'Для учителей']);

        $this->actingAs($student)->get(route('cabinet.student.news'))->assertOk()->assertSee('Каникулы')->assertDontSee('Для учителей');
        $this->get(route('cabinet.student.news-item', $news))->assertOk()->assertSee('Каникулы');
        $this->get('/cabinet/teacher/news')->assertRedirect(route('cabinet.student.home'));
    }

    public function test_foreign_or_removed_news_leads_back_to_list(): void
    {
        $tutor = $this->user(User::ROLE_TUTOR);
        $studentsOnly = $this->news(['audience' => Announcement::AUDIENCE_STUDENTS]);

        $this->actingAs($tutor)->get(route('cabinet.teacher.news-item', $studentsOnly))->assertRedirect(route('cabinet.teacher.news'));
        $this->get('/cabinet/teacher/news/999')->assertRedirect(route('cabinet.teacher.news'));
    }

    public function test_new_user_does_not_get_old_news_as_unread(): void
    {
        $this->news(['title' => 'Старая', 'published_at' => now()->subMonth()]);
        $this->news(['title' => 'Закреплённая', 'published_at' => now()->subMonth(), 'is_pinned' => true]);
        $tutor = $this->user(User::ROLE_TUTOR);

        $service = app(AnnouncementService::class);
        $this->assertSame(['Закреплённая'], $service->unreadFor($tutor)->pluck('title')->all());
        $this->assertSame(['Закреплённая', 'Старая'], $service->visibleFor($tutor)->pluck('title')->all());
    }

    public function test_important_banner_until_read_or_dismissed(): void
    {
        $tutor = $this->user(User::ROLE_TUTOR);
        $this->news(['title' => 'Обычная']);
        $important = $this->news(['title' => 'Смена реквизитов', 'is_important' => true, 'body' => '<p>С 1 ноября новые реквизиты</p>']);

        Livewire::actingAs($tutor)->test(NewsBanner::class)
            ->assertSee('Смена реквизитов')->assertSee('С 1 ноября новые реквизиты')->assertDontSee('Обычная')
            ->call('dismiss', $important->id)
            ->assertDontSee('Смена реквизитов');

        $student = $this->user(User::ROLE_STUDENT);
        Livewire::actingAs($student)->test(NewsBanner::class)->assertDontSee('Смена реквизитов');
    }

    public function test_menu_item_with_counter(): void
    {
        $tutor = $this->user(User::ROLE_TUTOR);
        $this->news(['title' => 'Первая']);

        $this->actingAs($tutor)->get(route('cabinet.teacher.news'))->assertOk()
            ->assertSee('data-tour="nav-news"', false);

        $this->assertSame(1, \App\Livewire\Cabinet\Notifications::navCounts($tutor)['news']);
    }
}
