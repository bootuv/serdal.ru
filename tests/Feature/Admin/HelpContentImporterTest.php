<?php

namespace Tests\Feature\Admin;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Services\HelpContentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** База знаний из database/help/*.md: `php artisan help:sync` при каждом деплое. */
class HelpContentImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->dir = sys_get_temp_dir() . '/help-sync-' . uniqid();
        File::ensureDirectoryExists($this->dir . '/tutors');
        File::ensureDirectoryExists($this->dir . '/students');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private const TUTORS = <<<'MD'
# Начало работы
Иконка: home
Описание: Профиль, цены и первые ученики

## Как заполнить профиль
Кратко: Что написать о себе, чтобы ученики вас выбрали.

1. Откройте **Профиль**.
2. Загрузите фото.

### Если фото не загружается
Проверьте формат: JPG или PNG.

## Как указать цены
Кратко: Цена за занятие или за месяц.

Откройте Профиль → вкладка «Цены». <script>alert(1)</script>
MD;

    private function sync(): array
    {
        return app(HelpContentImporter::class)->sync($this->dir);
    }

    private function stats(array $overrides): array
    {
        return array_merge(['categories' => 0, 'created' => 0, 'updated' => 0, 'edited' => 0, 'skipped' => 0, 'unpublished' => 0], $overrides);
    }

    public function test_creates_categories_and_published_articles(): void
    {
        File::put($this->dir . '/tutors/01-nachalo.md', self::TUTORS);
        File::put($this->dir . '/students/01-nachalo.md', "# Начало работы\nИконка: неизвестная\n\n## Как войти\nКратко: Вход по почте.\n\nОткройте страницу входа.\n");

        $this->assertSame($this->stats(['categories' => 2, 'created' => 3]), $this->sync());

        $tutors = HelpCategory::where('audience', HelpCategory::AUDIENCE_TUTOR)->sole();
        $this->assertSame(['Начало работы', 'home', 'Профиль, цены и первые ученики', true], [$tutors->name, $tutors->icon, $tutors->description, $tutors->is_published]);
        $this->assertSame('folder', HelpCategory::where('audience', HelpCategory::AUDIENCE_STUDENT)->sole()->icon);

        $profile = HelpArticle::where('title', 'Как заполнить профиль')->sole();
        $this->assertTrue($profile->is_published);
        $this->assertSame('tutors/01-nachalo.md::Как заполнить профиль', $profile->source);
        $this->assertSame('Что написать о себе, чтобы ученики вас выбрали.', $profile->excerpt);
        $this->assertStringContainsString('<ol>', $profile->content);
        $this->assertStringContainsString('<strong>Профиль</strong>', $profile->content);
        $this->assertStringContainsString('<h3>Если фото не загружается</h3>', $profile->content);
        $this->assertStringNotContainsString('Кратко:', $profile->content);

        $prices = HelpArticle::where('title', 'Как указать цены')->sole();
        $this->assertStringNotContainsString('<script', $prices->content);
        $this->assertGreaterThan($profile->sort_order, $prices->sort_order);

        $this->get($profile->url)->assertOk()->assertSee('Если фото не загружается');

        // Повторный запуск без изменений — ничего не меняет
        $this->assertSame($this->stats([]), $this->sync());
    }

    public function test_changed_file_updates_article_but_keeps_admin_edits_and_video(): void
    {
        File::put($this->dir . '/tutors/01-nachalo.md', self::TUTORS);
        $this->sync();

        // Цены правили в админке, к профилю добавили видео
        HelpArticle::where('title', 'Как указать цены')->sole()->update(['content' => '<p>Свой текст</p>']);
        HelpArticle::where('title', 'Как заполнить профиль')->sole()->update(['video_url' => 'https://rutube.ru/video/abc/']);

        File::put($this->dir . '/tutors/01-nachalo.md', str_replace(['Загрузите фото.', 'вкладка «Цены»'], ['Загрузите фото лица.', 'вкладка «Цены на занятия»'], self::TUTORS));

        $this->assertSame($this->stats(['updated' => 1, 'edited' => 1]), $this->sync());

        $profile = HelpArticle::where('title', 'Как заполнить профиль')->sole();
        $this->assertStringContainsString('Загрузите фото лица.', $profile->content);
        $this->assertSame('https://rutube.ru/video/abc/', $profile->video_url);
        $this->assertSame('<p>Свой текст</p>', HelpArticle::where('title', 'Как указать цены')->sole()->content);
    }

    public function test_removed_article_is_unpublished_unless_edited(): void
    {
        File::put($this->dir . '/tutors/01-nachalo.md', self::TUTORS);
        $this->sync();
        HelpArticle::where('title', 'Как указать цены')->sole()->update(['excerpt' => 'Правка из админки']);

        // Обе статьи убрали из файла, вместо «Как заполнить профиль» — переименованная
        File::put($this->dir . '/tutors/01-nachalo.md', "# Начало работы\nИконка: home\n\n## Как оформить профиль\nКратко: Фото и описание.\n\nТекст.\n");

        $this->assertSame($this->stats(['created' => 1, 'unpublished' => 1]), $this->sync());
        $this->assertFalse(HelpArticle::where('title', 'Как заполнить профиль')->sole()->is_published);
        $this->assertTrue(HelpArticle::where('title', 'Как указать цены')->sole()->is_published, 'Правленную в админке не трогаем');
        $this->assertTrue(HelpArticle::where('title', 'Как оформить профиль')->sole()->is_published);
    }

    public function test_articles_written_in_admin_are_kept(): void
    {
        $category = HelpCategory::create(['audience' => HelpCategory::AUDIENCE_TUTOR, 'name' => 'Начало работы', 'icon' => 'star', 'is_published' => true]);
        HelpArticle::create(['help_category_id' => $category->id, 'title' => 'Как заполнить профиль', 'content' => '<p>Свой текст</p>', 'is_published' => true]);
        File::put($this->dir . '/tutors/01-nachalo.md', self::TUTORS);

        $this->assertSame($this->stats(['created' => 1, 'skipped' => 1]), $this->sync());

        $this->assertSame('star', $category->fresh()->icon);
        $this->assertSame('<p>Свой текст</p>', HelpArticle::where('title', 'Как заполнить профиль')->sole()->content);
        $this->assertSame($category->id, HelpArticle::where('title', 'Как указать цены')->sole()->help_category_id);
    }

    public function test_command_syncs_repository_articles(): void
    {
        $this->artisan('help:sync')->assertSuccessful();

        $this->assertGreaterThan(50, HelpArticle::where('is_published', true)->count());
        $this->assertSame(0, HelpArticle::whereNull('source')->count());
    }
}
