<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TariffSeeder::class);
    }

    protected function makeTutor(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'seo-tutor',
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    protected function makeArticle(): HelpArticle
    {
        $category = HelpCategory::create([
            'name' => 'Первые шаги',
            'audience' => HelpCategory::AUDIENCE_TUTOR,
            'is_published' => true,
            'sort_order' => 1,
        ]);

        return HelpArticle::create([
            'help_category_id' => $category->id,
            'title' => 'Как создать комнату',
            'excerpt' => 'Короткая инструкция по созданию комнаты.',
            'content' => '<p>Откройте раздел «Комнаты» и нажмите «Создать».</p>',
            'is_published' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_robots_allows_public_pages_and_points_to_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *', false)
            ->assertSee('Disallow: /admin', false)
            ->assertSee('User-agent: GPTBot', false)
            ->assertSee('User-agent: ClaudeBot', false)
            ->assertSee('Sitemap: http://localhost/sitemap.xml', false);
    }

    public function test_sitemap_lists_static_pages_tutors_and_help_articles(): void
    {
        $tutor = $this->makeTutor();
        $article = $this->makeArticle();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>http://localhost/</loc>', false)
            ->assertSee('<loc>http://localhost/tariffs</loc>', false)
            ->assertSee('<loc>http://localhost/' . $tutor->username . '</loc>', false)
            ->assertSee('<loc>http://localhost/help/tutors</loc>', false)
            ->assertSee('<loc>' . $article->url . '</loc>', false)
            ->assertDontSee('/admin', false);
    }

    public function test_llms_txt_describes_site_and_links_full_version(): void
    {
        $this->makeTutor();
        $this->makeArticle();

        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('# Serdal', false)
            ->assertSee('## Тарифы для преподавателей', false)
            ->assertSee('Как создать комнату', false)
            ->assertSee('http://localhost/llms-full.txt', false);

        $this->get('/llms-full.txt')
            ->assertOk()
            ->assertSee('## Статьи центра помощи', false)
            ->assertSee('Откройте раздел «Комнаты»', false);
    }

    public function test_home_page_has_seo_head_and_structured_data(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/">', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('"@type":"WebSite"', false);
    }

    public function test_tutor_page_has_profile_structured_data(): void
    {
        $tutor = $this->makeTutor();

        $this->get('/' . $tutor->username)
            ->assertOk()
            ->assertSee('<h1 class="h3 tutor-name">', false)
            ->assertSee('"@type":"ProfilePage"', false)
            ->assertSee('"@type":"Person"', false)
            ->assertSee('<meta property="og:type" content="profile">', false)
            ->assertSee('<link rel="canonical" href="http://localhost/' . $tutor->username . '">', false);
    }

    public function test_tariffs_page_has_offer_structured_data(): void
    {
        $this->get('/tariffs')
            ->assertOk()
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('"priceCurrency":"RUB"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_admin_settings_override_titles_verification_and_head_code(): void
    {
        \App\Models\Setting::updateOrCreate(['key' => 'seo_site_name'], ['value' => 'Моя школа']);
        \App\Models\Setting::updateOrCreate(['key' => 'seo_home_title'], ['value' => 'Репетиторы моей школы']);
        \App\Models\Setting::updateOrCreate(['key' => 'seo_home_description'], ['value' => 'Описание главной из админки']);
        \App\Models\Setting::updateOrCreate(['key' => 'seo_yandex_verification'], ['value' => 'ya-123']);
        \App\Models\Setting::updateOrCreate(['key' => 'seo_google_verification'], ['value' => 'goo-456']);
        \App\Models\Setting::updateOrCreate(['key' => 'seo_head_extra'], ['value' => '<!-- metrika-counter -->']);
        \App\Models\Setting::updateOrCreate(['key' => 'seo_social_links'], ['value' => "https://t.me/serdal\nне ссылка"]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Репетиторы моей школы</title>', false)
            ->assertSee('content="Описание главной из админки"', false)
            ->assertSee('<meta property="og:site_name" content="Моя школа">', false)
            ->assertSee('<meta name="yandex-verification" content="ya-123">', false)
            ->assertSee('<meta name="google-site-verification" content="goo-456">', false)
            ->assertSee('<!-- metrika-counter -->', false)
            ->assertSee('"sameAs":["https://t.me/serdal"]', false)
            ->assertSee('"name":"Моя школа"', false);
    }

    public function test_disabling_indexing_closes_site_for_robots(): void
    {
        \App\Models\Setting::updateOrCreate(['key' => 'seo_indexing_enabled'], ['value' => '0']);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: *\nDisallow: /", false)
            ->assertDontSee('Sitemap:', false);

        $this->get('/tariffs')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_disabling_ai_crawlers_blocks_training_bots(): void
    {
        \App\Models\Setting::updateOrCreate(['key' => 'seo_ai_crawlers_enabled'], ['value' => '0']);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: GPTBot\nDisallow: /", false)
            ->assertSee("User-agent: ClaudeBot\nDisallow: /", false)
            ->assertSee('Sitemap: http://localhost/sitemap.xml', false)
            ->assertDontSee('llms.txt', false);
    }

    public function test_uploaded_og_image_and_touch_icon_are_used(): void
    {
        config(['filesystems.disks.s3.url' => 'https://cdn.test']);
        \App\Models\Setting::updateOrCreate(['key' => 'seo_og_image'], ['value' => 'seo/share.png']);
        \App\Models\Setting::updateOrCreate(['key' => 'seo_apple_touch_icon'], ['value' => 'seo/touch.png']);

        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:image" content="https://cdn.test/seo/share.png">', $html);
        $this->assertStringContainsString('<link rel="apple-touch-icon" href="https://cdn.test/seo/touch.png">', $html);
        // Favicon в админке не настраивается — всегда файлы проекта
        $this->assertStringContainsString('favicon.svg', $html);
        $this->assertStringContainsString('favicon.ico', $html);
    }

    public function test_admin_settings_page_shows_seo_tab(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'username' => 'seo-admin',
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('Заголовки и описания')
            ->assertSee('Картинка для соцсетей (og:image)')
            ->assertSee('Разрешить ИИ-краулеры');
    }

    public function test_help_article_has_article_structured_data(): void
    {
        $article = $this->makeArticle();

        $this->get($article->url)
            ->assertOk()
            ->assertSee('"@type":"TechArticle"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }
}
