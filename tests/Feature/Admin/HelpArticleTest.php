<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\HelpArticle as HelpArticleScreen;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → статья базы знаний (/cabinet/admin/help/articles/{id|new}). */
class HelpArticleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('s3');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'first_name' => 'Анна',
            'last_name' => 'Куликова',
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    private function category(array $attrs = []): HelpCategory
    {
        return HelpCategory::create(array_merge(['audience' => 'student', 'name' => 'Занятия', 'slug' => 'zanyatiya', 'icon' => 'video'], $attrs));
    }

    public function test_access(): void
    {
        $url = route('cabinet.admin.help-article', ['article' => 'new']);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user(User::ROLE_TUTOR))->get($url)->assertRedirect(route('cabinet.teacher.today'));
        $this->actingAs($this->user(User::ROLE_STUDENT))->get($url)->assertRedirect(route('cabinet.student.home'));

        $admin = $this->user(User::ROLE_ADMIN);
        $this->category();
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Новая статья')->assertSee('Опубликована')->assertSee('Картинка');
        $this->actingAs($admin)->get(route('cabinet.admin.help-article', ['article' => 999]))->assertNotFound();
        $this->actingAs($admin)->get(route('cabinet.admin.help-article', ['article' => 'abc']))->assertNotFound();
    }

    public function test_create_article_generates_unique_slug_and_cleans_html(): void
    {
        $c = $this->category();
        HelpArticle::create(['help_category_id' => $c->id, 'title' => 'Как войти в класс', 'slug' => 'kak-voiti-v-klass']);
        $admin = $this->user(User::ROLE_ADMIN);

        Livewire::actingAs($admin)->test(HelpArticleScreen::class, ['article' => 'new'])
            ->assertSet('categoryId', (string) $c->id)
            ->call('save')
            ->assertHasErrors(['title' => 'required'])
            ->set('title', 'Как войти в класс')
            ->set('excerpt', 'Где найти кнопку')
            ->set('content', '<p>Нажмите <b>«Войти в класс»</b></p><img src="https://cdn.serdal.ru/help-articles/a.png" alt=""><script>alert(1)</script>')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $article = HelpArticle::latest('id')->first();
        $this->assertSame('Как войти в класс', $article->title);
        $this->assertNotSame('kak-voiti-v-klass', $article->slug);
        $this->assertStringStartsWith('kak-voiti-v-klass', $article->slug);
        $this->assertStringContainsString('<img src="https://cdn.serdal.ru/help-articles/a.png"', $article->content);
        $this->assertStringNotContainsString('script', $article->content);
        $this->assertTrue($article->is_published);
        $this->assertSame('Статья создана', session('toast'));
    }

    public function test_sanitizer_keeps_article_images(): void
    {
        $html = RichText::clean('<p>Текст</p><img src="/storage/help-articles/b.png" alt="Кнопка"><figure><img src="https://cdn.serdal.ru/x.jpg"></figure>');

        $this->assertStringContainsString('<img src="/storage/help-articles/b.png" alt="Кнопка"', $html);
        $this->assertStringContainsString('<img src="https://cdn.serdal.ru/x.jpg"', $html);
    }

    public function test_edit_article_facts_publish_and_keep_slug(): void
    {
        $c = $this->category();
        $a = HelpArticle::create(['help_category_id' => $c->id, 'title' => 'Как войти в класс', 'views_count' => 2480, 'is_published' => true]);
        $slug = $a->slug;

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(HelpArticleScreen::class, ['article' => (string) $a->id])
            ->assertSee('Для учеников · Занятия · 2 480 просмотров')
            ->assertSee('Как на сайте')
            ->set('title', 'Как войти в класс с телефона')
            ->assertSee('есть несохранённые изменения')
            ->call('togglePublished')
            ->call('save')
            ->assertSet('saved', true)
            ->assertSee('Сохранено')
            ->assertSee('черновик — на сайте не видна');

        $a->refresh();
        $this->assertSame('Как войти в класс с телефона', $a->title);
        $this->assertFalse($a->is_published);
        // Слаг не меняется при правке заголовка — ссылки на статью не ломаются
        $this->assertSame($slug, $a->slug);
    }

    public function test_video_file_and_link(): void
    {
        $c = $this->category();
        $a = HelpArticle::create(['help_category_id' => $c->id, 'title' => 'Видео']);
        $admin = $this->user(User::ROLE_ADMIN);

        $test = Livewire::actingAs($admin)->test(HelpArticleScreen::class, ['article' => (string) $a->id])
            ->set('video', UploadedFile::fake()->create('lesson.mp4', 2048, 'video/mp4'))
            ->assertHasNoErrors()
            ->call('save');
        $file = $a->fresh()->video_file;
        $this->assertNotNull($file);
        Storage::disk('s3')->assertExists($file);

        // Слишком большой или не видео
        $test->set('video', UploadedFile::fake()->create('big.mp4', 102401, 'video/mp4'))->assertHasErrors(['video' => 'max']);
        $test->set('video', UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))->assertHasErrors(['video' => 'mimetypes']);
        $test->set('video', null);

        // Ссылка вместо файла: файл удаляется
        $test->set('videoSource', 'link')
            ->set('videoUrl', 'https://example.com/video')
            ->call('save')
            ->assertHasErrors(['videoUrl'])
            ->set('videoUrl', 'https://rutube.ru/video/abc123/')
            ->call('save')
            ->assertHasNoErrors();

        $a->refresh();
        $this->assertSame('https://rutube.ru/video/abc123/', $a->video_url);
        $this->assertNull($a->video_file);
        Storage::disk('s3')->assertMissing($file);
    }

    public function test_image_upload_returns_cdn_url(): void
    {
        $c = $this->category();
        $a = HelpArticle::create(['help_category_id' => $c->id, 'title' => 'С картинкой']);

        $test = Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(HelpArticleScreen::class, ['article' => (string) $a->id])
            ->set('image', UploadedFile::fake()->image('shot.png', 800, 600));
        $url = $test->instance()->storeImage();

        $this->assertNotNull($url);
        $this->assertStringContainsString('help-articles/', $url);
        $this->assertCount(1, Storage::disk('s3')->files('help-articles'));

        $test->set('image', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))
            ->call('storeImage')
            ->assertDispatched('toast', tone: 'danger');
    }

    public function test_delete_or_unpublish_instead(): void
    {
        Storage::disk('s3')->put('help-videos/v.mp4', 'x');
        $c = $this->category();
        $a = HelpArticle::create(['help_category_id' => $c->id, 'title' => 'Удаляемая', 'video_file' => 'help-videos/v.mp4', 'is_published' => true]);
        $admin = $this->user(User::ROLE_ADMIN);

        Livewire::actingAs($admin)->test(HelpArticleScreen::class, ['article' => (string) $a->id])
            ->call('askDelete')
            ->assertSee('Удалить статью?')
            ->assertSee('Снимите с публикации')
            ->call('unpublishInstead')
            ->assertDispatched('toast', message: 'Статья снята с публикации');
        $this->assertFalse($a->fresh()->is_published);

        Livewire::actingAs($admin)->test(HelpArticleScreen::class, ['article' => (string) $a->id])
            ->call('askDelete')
            ->call('delete')
            ->assertRedirect(route('cabinet.admin.help'));
        $this->assertDatabaseMissing('help_articles', ['id' => $a->id]);
        Storage::disk('s3')->assertMissing('help-videos/v.mp4');
    }
}
