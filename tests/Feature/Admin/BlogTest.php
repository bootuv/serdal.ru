<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCabinetRole;
use App\Livewire\Cabinet\Admin\Blog;
use App\Livewire\Cabinet\Admin\BlogArticle;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogService;
use App\Services\SitemapService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Блог: админка «Блог» (/cabinet/admin/blog) и страницы сайта /blog. */
class BlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'username' => $role . uniqid(), 'is_active' => true, 'is_blocked' => false, 'is_profile_completed' => true]);
    }

    private function article(array $attrs = []): BlogPost
    {
        return app(BlogService::class)->save(null, array_merge([
            'title' => 'Как подготовиться к ОГЭ по математике',
            'body' => '<h2>План</h2><p>Начните с <strong>диагностики</strong>.</p><aside class="callout">Совет</aside>',
            'published_at' => now()->subHour(),
        ], $attrs));
    }

    public function test_access(): void
    {
        $this->get('/cabinet/admin/blog')->assertRedirect(route('login'));
        $tutor = $this->user(User::ROLE_TUTOR);
        $this->actingAs($tutor)->get('/cabinet/admin/blog')->assertRedirect(EnsureCabinetRole::homeFor($tutor));

        $this->actingAs($this->user(User::ROLE_ADMIN))->get('/cabinet/admin/blog')->assertOk()->assertSee('Написать статью');
        $this->get('/cabinet/admin/blog/new')->assertOk()->assertSee('Новая статья')->assertSee('Для поиска');
    }

    public function test_write_draft_then_publish(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => 'new'])
            ->call('saveDraft')->assertHasErrors(['title' => 'required'])
            ->set('title', 'Дистанционные занятия: с чего начать')
            ->set('body', '<p>Текст</p><script>alert(1)</script>')
            ->set('excerpt', 'Короткое описание')
            ->call('saveDraft')
            ->assertRedirect();

        $post = BlogPost::sole();
        $this->assertNull($post->published_at);
        $this->assertSame('distantsionnye-zanyatiya-s-chego-nachat', $post->slug);
        $this->assertStringNotContainsString('script', (string) $post->body);

        // Черновик на сайте не виден, кроме администратора
        auth()->logout();
        $this->get('/blog/' . $post->slug)->assertNotFound();
        $this->get('/blog')->assertOk()->assertSee('Скоро здесь появятся первые статьи');

        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => (string) $post->id])
            ->assertSet('slug', $post->slug)
            ->call('submit')
            ->assertRedirect();
        $this->assertTrue($post->fresh()->isPublished());

        auth()->logout();
        $this->get('/blog')->assertOk()->assertSee('Дистанционные занятия: с чего начать')->assertSee('Короткое описание');
    }

    public function test_schedule(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => 'new'])
            ->set('title', 'Позже')
            ->set('when', 'later')
            ->set('publishAt', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('submit')->assertHasErrors('publishAt')
            ->set('publishAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('submit')
            ->assertRedirect();

        $post = BlogPost::sole();
        $this->assertTrue($post->isScheduled());
        auth()->logout();
        $this->get('/blog/' . $post->slug)->assertNotFound();

        $this->travel(2)->days();
        $this->get('/blog/' . $post->slug)->assertOk();

        Livewire::actingAs($admin)->test(Blog::class)->assertSee('Позже');
    }

    public function test_article_page_has_seo_and_is_in_sitemap(): void
    {
        $post = $this->article(['excerpt' => 'Пошаговый план подготовки', 'cover_url' => 'https://cdn.test/blog/cover.webp']);
        $this->article(['title' => 'Вторая статья']);

        $this->get('/blog/' . $post->slug)->assertOk()
            ->assertSee('<title>Как подготовиться к ОГЭ по математике — блог Serdal</title>', false)
            ->assertSee('<meta name="description" content="Пошаговый план подготовки">', false)
            ->assertSee('<meta property="og:image" content="https://cdn.test/blog/cover.webp">', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('<aside class="callout">Совет</aside>', false)
            ->assertSee('Читайте также')
            ->assertSee('Вторая статья');
        $this->assertSame(1, $post->fresh()->views_count);

        $locs = collect(app(SitemapService::class)->urls())->pluck('loc');
        $this->assertTrue($locs->contains(fn ($l) => str_ends_with($l, '/blog')));
        $this->assertTrue($locs->contains(fn ($l) => str_ends_with($l, '/blog/' . $post->slug)));

        $this->get('/llms.txt')->assertOk()->assertSee('## Блог')->assertSee('/blog/' . $post->slug);
        $this->get('/')->assertSee(route('blog.index'));
    }

    public function test_slug_is_unique_and_can_be_set(): void
    {
        $first = $this->article();
        $second = $this->article();
        $this->assertSame($first->slug . '-2', $second->slug);

        $custom = app(BlogService::class)->save($second, ['title' => $second->title, 'slug' => 'Подготовка к ОГЭ', 'published_at' => $second->published_at]);
        $this->assertSame('podgotovka-k-oge', $custom->slug);
    }

    public function test_unpublish_and_delete(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $post = $this->article();

        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => (string) $post->id])
            ->call('unpublish');
        $this->assertNull($post->fresh()->published_at);

        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => (string) $post->id])
            ->call('askDelete')->call('delete')
            ->assertRedirect(route('cabinet.admin.blog'));
        $this->assertNull(BlogPost::find($post->id));
    }
}
