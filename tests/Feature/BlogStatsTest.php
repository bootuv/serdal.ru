<?php

namespace Tests\Feature;

use App\Livewire\Cabinet\Admin\BlogStats;
use App\Livewire\Cabinet\Teacher\BlogStats as TeacherBlogStats;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogService;
use App\Services\BlogStatsService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Статистика блога: учет просмотров по дням и источникам, отчеты админа и учителя. */
class BlogStatsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        Notification::fake();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'username' => $role . uniqid(), 'is_active' => true, 'is_blocked' => false, 'is_profile_completed' => true]);
    }

    private function article(?User $author, string $title = 'Статья'): BlogPost
    {
        return app(BlogService::class)->save(null, ['title' => $title, 'body' => '<p>Текст</p>', 'author_id' => $author?->id, 'published_at' => now()->subDay()]);
    }

    public function test_sources_are_recognized(): void
    {
        $this->assertSame('search', BlogStatsService::source('https://yandex.ru/search/?text=огэ'));
        $this->assertSame('search', BlogStatsService::source('https://www.google.com/'));
        $this->assertSame('social', BlogStatsService::source('https://t.me/serdal'));
        $this->assertSame('social', BlogStatsService::source('https://m.vk.com/wall-1'));
        $this->assertSame('site', BlogStatsService::source(config('app.url') . '/blog'));
        $this->assertSame('other', BlogStatsService::source('https://example.org/links'));
        $this->assertSame('direct', BlogStatsService::source(null));
        $this->assertTrue(BlogStatsService::isBot('Mozilla/5.0 (compatible; YandexBot/3.0)'));
        $this->assertFalse(BlogStatsService::isBot(self::BROWSER));
    }

    public function test_view_is_counted_once_per_session_without_bots_and_author(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $post = $this->article($teacher);

        $this->withHeaders(['User-Agent' => self::BROWSER, 'Referer' => 'https://yandex.ru/search/'])->get($post->url)->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER])->get($post->url)->assertOk(); // та же сессия
        $this->assertSame(1, $post->fresh()->views_count);
        $row = DB::table('blog_post_stats')->where('blog_post_id', $post->id)->first();
        $this->assertSame([1, 1, 0], [(int) $row->views, (int) $row->search, (int) $row->direct]);

        // Новая сессия из соцсети — в ту же строку дня
        $this->flushSession();
        $this->withHeaders(['User-Agent' => self::BROWSER, 'Referer' => 'https://t.me/serdal'])->get($post->url);
        $row = DB::table('blog_post_stats')->where('blog_post_id', $post->id)->first();
        $this->assertSame([2, 1], [(int) $row->views, (int) $row->social]);

        // Робот и сам автор — не считаются
        $this->flushSession();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->get($post->url);
        $this->flushSession();
        $this->actingAs($teacher)->withHeaders(['User-Agent' => self::BROWSER])->get($post->url);
        $this->assertSame(2, $post->fresh()->views_count);
    }

    public function test_teacher_sees_only_own_articles_and_admin_sees_all(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $mine = $this->article($teacher, 'Моя статья');
        $foreign = $this->article($this->user(User::ROLE_TUTOR), 'Чужая статья');
        $stats = app(BlogStatsService::class);
        foreach ([[$mine, 'https://yandex.ru/'], [$mine, null], [$foreign, 'https://vk.com/']] as [$post, $ref]) {
            $stats->record($post, $ref);
        }

        // Доступ: учитель — к своей статистике, админская — только админу
        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($teacher)->get('/cabinet/teacher/blog/stats')->assertOk();
        $this->actingAs($teacher)->get('/cabinet/teacher/blog')->assertSee(route('cabinet.teacher.blog-stats'), false);
        $this->actingAs($teacher)->get('/cabinet/admin/blog/stats')->assertRedirect();
        $this->actingAs($admin)->get('/cabinet/admin/blog/stats')->assertOk();

        $report = $stats->report(30, $teacher);
        $this->assertSame(2, $report['views']);
        $this->assertSame(2, end($report['days'])['value']);
        $this->assertSame(['Моя статья'], $report['posts']->pluck('title')->all());

        Livewire::actingAs($teacher)->test(TeacherBlogStats::class)
            ->assertSee('Статистика статей')->assertSee('Моя статья')->assertDontSee('Чужая статья')->assertSee('Поиск')
            ->call('showPost', $mine->id)->assertSet('post', $mine->id)->assertSee('Открыть на сайте');
        Livewire::actingAs($teacher)->test(TeacherBlogStats::class)->call('showPost', $foreign->id)->assertNotFound();

        Livewire::actingAs($admin)->test(BlogStats::class)
            ->assertSee('Моя статья')->assertSee('Чужая статья')->assertSee('Авторы')->assertSee('Соцсети и мессенджеры')
            ->set('days', 7)->assertSee('За 7 дней');

    }

    public function test_guest_view_of_team_article_is_counted(): void
    {
        $post = $this->article(null, 'Статья команды');
        $this->withHeaders(['User-Agent' => self::BROWSER])->get($post->url)->assertOk();
        $this->assertSame(1, $post->fresh()->views_count);
        $this->assertSame(1, (int) DB::table('blog_post_stats')->where('blog_post_id', $post->id)->value('views'));
    }
}
