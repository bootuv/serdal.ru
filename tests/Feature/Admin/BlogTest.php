<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCabinetRole;
use App\Livewire\Cabinet\Admin\Blog;
use App\Livewire\Cabinet\Admin\BlogArticle;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogService;
use App\Services\SitemapService;
use App\Support\Seo;
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
        $this->get('/cabinet/admin/blog/new')->assertOk()->assertSee('Настройки статьи')->assertSee('Для поиска')->assertSee('Опубликовать');
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
            ->call('submit')->assertHasErrors('publishAt')->assertDispatched('blog-settings')
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
            ->assertSee('<meta property="og:image" content="' . Seo::url('/blog/' . $post->slug . '/og.jpg'), false) // картинка для соцсетей с обложкой на фоне
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

    public function test_tags_authors_and_sidebar(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->user(User::ROLE_TUTOR);
        $teacher->update(['name' => 'Мадина Евлоева']);

        // Админ пишет статью от имени учителя, с тегами
        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => 'new'])
            ->set('title', 'Пробник ЕГЭ по русскому')
            ->set('newTag', 'ЕГЭ')->call('addTag')
            ->set('newTag', 'русский язык')->call('addTag')
            ->set('newTag', 'егэ')->call('addTag')
            ->assertSet('tags', ['ЕГЭ', 'русский язык'])
            ->assertSee('Найти или добавить тег')
            ->set('authorId', (string) $teacher->id)
            ->call('submit');
        $post = BlogPost::where('title', 'Пробник ЕГЭ по русскому')->sole();
        $this->assertSame($teacher->id, $post->author_id);
        $this->assertSame(['ЕГЭ', 'русский язык'], $post->tags->pluck('name')->all());
        $other = $this->article(['title' => 'Без тегов от команды']);

        auth()->logout();
        $this->get('/blog')->assertOk()
            ->assertSee('Популярные темы')->assertSee('/blog/tag/ege', false)
            ->assertSee('Авторы')->assertSee('Мадина Евлоева')->assertSee('/blog/author/' . $teacher->username, false)
            ->assertSee('blog-card--fill', false);
        $this->get('/blog/tag/ege')->assertOk()->assertSee('#ЕГЭ')->assertSee('Пробник ЕГЭ по русскому')->assertDontSee('Без тегов от команды');
        $this->get('/blog/author/' . $teacher->username)->assertOk()->assertSee('Мадина Евлоева')->assertDontSee('Без тегов от команды');
        $this->get('/blog/tag/net')->assertNotFound();
        $this->get('/blog/' . $post->slug)->assertOk()
            ->assertSee('Автор статьи')->assertSee('Все статьи автора')
            ->assertSee('"@type":"Person"', false)
            ->assertSee('/blog/tag/russkiy-yazyk', false);

        // В карту сайта — только темы, где статей не меньше BlogService::TAG_MIN_POSTS
        $locs = collect(app(SitemapService::class)->urls())->pluck('loc');
        $this->assertFalse($locs->contains(fn ($l) => str_ends_with($l, '/blog/tag/ege')));
        $this->article(['title' => 'Вторая про ЕГЭ', 'tags' => ['ЕГЭ']]);
        $locs = collect(app(SitemapService::class)->urls())->pluck('loc');
        $this->assertTrue($locs->contains(fn ($l) => str_ends_with($l, '/blog/tag/ege')));
        $this->assertTrue($locs->contains(fn ($l) => str_ends_with($l, '/blog/author/' . $teacher->username)));

        // Крошки: Главная › Блог › тема — тема только с открытой для поиска страницей (2+ статьи), совпадает с разметкой
        $this->get('/blog/' . $post->slug)
            ->assertSeeInOrder(['Навигационная цепочка', 'Главная', 'Блог', 'ЕГЭ'])
            ->assertSee('"name":"ЕГЭ","item":"' . url('/blog/tag/ege') . '"', false)
            ->assertDontSee('"name":"русский язык","item"', false);

        // Выбор из существующих: «егэ» превращается в уже известный «ЕГЭ», новое название — новый тег
        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => 'new'])
            ->call('addTag', 'егэ')->call('addTag', 'химия')
            ->assertSet('tags', ['ЕГЭ', 'химия']);

        // Теги в админке: переименование в существующий объединяет, удаление
        $russian = \App\Models\BlogTag::where('slug', 'russkiy-yazyk')->sole();
        Livewire::actingAs($admin)->test(Blog::class)->set('tab', 'tags')
            ->assertSee('русский язык')
            ->call('editTag', $russian->id)->set('tagName', 'ЕГЭ')->call('saveTag');
        $this->assertSame(['ЕГЭ'], $post->fresh()->tags->pluck('name')->all());
        $this->assertNull(\App\Models\BlogTag::find($russian->id));
        $this->assertContains($other->cardStyle(), ['fill', 'outline', 'mint']);
    }

    public function test_tutor_public_page_shows_latest_posts(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $this->get('/' . $teacher->username)->assertOk()->assertDontSee('Статьи в блоге');

        foreach (range(1, 5) as $n) {
            $this->article(['title' => 'Статья учителя ' . $n, 'author_id' => $teacher->id, 'published_at' => now()->subDays($n)]);
        }
        $this->article(['title' => 'Черновик учителя', 'author_id' => $teacher->id, 'published_at' => null]);

        $this->get('/' . $teacher->username)->assertOk()
            ->assertSee('Статьи в блоге')
            ->assertSee('Статья учителя 1')->assertSee('Статья учителя 4')
            ->assertDontSee('Статья учителя 5')->assertDontSee('Черновик учителя')
            ->assertSee('Все статьи · 5')
            ->assertSee(route('blog.author', $teacher->username), false);
    }

    public function test_students_learn_about_their_teachers_new_post(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $teacher = $this->user(User::ROLE_TUTOR);
        $mine = $this->user(User::ROLE_STUDENT);
        $blocked = $this->user(User::ROLE_STUDENT);
        $blocked->update(['is_blocked' => true]);
        $stranger = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach([$mine->id, $blocked->id]);

        // Сразу опубликованная — ученикам учителя, кроме заблокированных; чужим — нет
        $post = $this->article(['title' => 'Новая статья', 'author_id' => $teacher->id]);
        \Illuminate\Support\Facades\Notification::assertSentTo($mine, \App\Notifications\TeacherPublishedBlogPost::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($blocked, \App\Notifications\TeacherPublishedBlogPost::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($stranger, \App\Notifications\TeacherPublishedBlogPost::class);

        // Сняли и вернули — второй раз не шлем
        app(BlogService::class)->unpublish($post);
        app(BlogService::class)->save($post->fresh(), ['title' => $post->title, 'published_at' => now()]);
        \Illuminate\Support\Facades\Notification::assertSentToTimes($mine, \App\Notifications\TeacherPublishedBlogPost::class, 1);

        // По расписанию — когда время придет (blog:notify)
        $later = $this->article(['title' => 'Позже', 'author_id' => $teacher->id, 'published_at' => now()->addHour()]);
        $this->artisan('blog:notify')->assertSuccessful();
        \Illuminate\Support\Facades\Notification::assertSentToTimes($mine, \App\Notifications\TeacherPublishedBlogPost::class, 1);
        $this->travel(2)->hours();
        $this->artisan('blog:notify')->assertSuccessful();
        \Illuminate\Support\Facades\Notification::assertSentToTimes($mine, \App\Notifications\TeacherPublishedBlogPost::class, 2);

        // Статья от команды — ученикам никто не пишет
        $this->article(['title' => 'От команды']);
        \Illuminate\Support\Facades\Notification::assertSentToTimes($mine, \App\Notifications\TeacherPublishedBlogPost::class, 2);
    }

    public function test_teacher_writes_and_admin_reviews(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\SendAdminTelegramMessage::class]);
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->user(User::ROLE_TUTOR);
        $stranger = $this->user(User::ROLE_TUTOR);

        $this->actingAs($teacher)->get('/cabinet/teacher/blog')->assertOk()->assertSee('Напишите статью для блога Serdal');

        // Учитель пишет и отправляет на проверку
        Livewire::actingAs($teacher)->test(\App\Livewire\Cabinet\Teacher\BlogArticle::class, ['post' => 'new'])
            ->assertSee('Отправить на проверку')->assertDontSee('Опубликовать сейчас')->assertDontSee('Адрес статьи')
            ->set('title', 'Как я готовлю к ОГЭ')
            ->set('body', '<p>Мой подход</p>')
            ->call('submit')
            ->assertRedirect();
        $post = BlogPost::sole();
        $this->assertSame($teacher->id, $post->author_id);
        $this->assertSame(BlogPost::REVIEW_PENDING, $post->review_status);
        $this->assertNull($post->published_at);

        // Администраторам — уведомление в кабинете и сообщение в Telegram
        \Illuminate\Support\Facades\Notification::assertSentTo($admin, \App\Notifications\BlogPostSubmitted::class,
            fn ($n) => $n->post->is($post) && ! $n->resubmitted);
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\SendAdminTelegramMessage::class,
            fn ($job) => str_contains($job->text, 'Статья на проверку') && str_contains($job->text, e('«Как я готовлю к ОГЭ»')));

        // Чужую статью другой учитель не видит; черновики учителей не в «Черновиках» админа
        $this->actingAs($stranger)->get('/cabinet/teacher/blog/' . $post->id)->assertNotFound();
        $this->actingAs($teacher)->get('/cabinet/teacher/blog')->assertSee('На проверке');
        Livewire::actingAs($admin)->test(Blog::class)->set('tab', 'review')->assertSee('Как я готовлю к ОГЭ');
        Livewire::actingAs($admin)->test(Blog::class)->set('tab', 'drafts')->assertDontSee('Как я готовлю к ОГЭ');
        $this->assertSame(1, app(\App\Services\AdminInboxService::class)->counts()['blog']);

        // Админ возвращает с комментарием
        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => (string) $post->id])
            ->assertSee('Статья учителя ждет проверки')
            ->call('askReturn')->call('returnForRework')->assertHasErrors('returnNote')
            ->set('returnNote', 'Добавьте пример задания')
            ->call('returnForRework');
        $this->assertSame(BlogPost::REVIEW_RETURNED, $post->fresh()->review_status);
        \Illuminate\Support\Facades\Notification::assertSentTo($teacher, \App\Notifications\BlogPostReviewed::class, fn ($n) => ! $n->published);

        Livewire::actingAs($teacher)->test(\App\Livewire\Cabinet\Teacher\BlogArticle::class, ['post' => (string) $post->id])
            ->assertSee('Добавьте пример задания')
            ->set('body', '<p>Мой подход и пример</p>')
            ->call('submit');
        $this->assertSame(BlogPost::REVIEW_PENDING, $post->fresh()->review_status);
        \Illuminate\Support\Facades\Notification::assertSentTo($admin, \App\Notifications\BlogPostSubmitted::class, fn ($n) => $n->resubmitted);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\SendAdminTelegramMessage::class, 2);

        // Админ публикует — учителю уведомление; править опубликованную учитель не может
        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => (string) $post->id])->call('publishNow');
        $this->assertTrue($post->fresh()->isPublished());
        \Illuminate\Support\Facades\Notification::assertSentTo($teacher, \App\Notifications\BlogPostReviewed::class, fn ($n) => $n->published);

        Livewire::actingAs($teacher)->test(\App\Livewire\Cabinet\Teacher\BlogArticle::class, ['post' => (string) $post->id])
            ->assertSee('Снять с сайта и изменить')
            ->call('submit')->assertForbidden();

        Livewire::actingAs($teacher)->test(\App\Livewire\Cabinet\Teacher\BlogArticle::class, ['post' => (string) $post->id])
            ->call('unpublish');
        $this->assertNull($post->fresh()->published_at);
        $this->assertSame(BlogPost::REVIEW_DRAFT, $post->fresh()->review_status);
    }

    public function test_autosave_drafts_but_not_published(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $c = Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => 'new'])
            ->call('autosave');
        $this->assertSame(0, BlogPost::count(), 'пустую новую статью не создаем');

        $c->set('title', 'Черновик сам')->set('body', '<p>Пишу</p>')->call('autosave');
        $post = BlogPost::sole();
        $this->assertNull($post->published_at);
        $this->assertSame('<p>Пишу</p>', $post->body);
        $c->assertSet('postId', $post->id)->assertSee('Сохранено в');

        // Без изменений — не пишем в базу
        $updated = $post->updated_at;
        $this->travel(1)->minutes();
        $c->call('autosave');
        $this->assertEquals($updated, $post->fresh()->updated_at);

        // Запланированная остается запланированной
        $at = now()->addDay()->startOfMinute();
        $post->update(['published_at' => $at]);
        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => (string) $post->id])
            ->set('body', '<p>Еще</p>')->call('autosave');
        $this->assertEquals($at, $post->fresh()->published_at);
        $this->assertSame('<p>Еще</p>', $post->fresh()->body);

        // Опубликованную сами не сохраняем — только по кнопке
        $post->update(['published_at' => now()->subHour()]);
        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => (string) $post->id])
            ->set('body', '<p>Недописано</p>')->call('autosave')
            ->assertSee('Есть несохраненные изменения');
        $this->assertSame('<p>Еще</p>', $post->fresh()->body);
    }

    public function test_publish_menu_actions(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => 'new'])
            ->assertSee('Опубликовать сейчас')->assertSee('Запланировать…')->assertSee('Сохранить как черновик')
            ->call('planLater')->assertSet('when', 'later')->assertDispatched('blog-settings')
            ->set('title', 'Сразу')
            ->call('publishNow');
        $post = BlogPost::sole();
        $this->assertTrue($post->isPublished());

        Livewire::actingAs($admin)->test(BlogArticle::class, ['post' => (string) $post->id])
            ->assertSee('Снять с публикации')->assertDontSee('Опубликовать сейчас')
            ->set('title', 'Сразу и поправлено')
            ->call('saveDraft');
        $this->assertNull($post->fresh()->published_at);
        $this->assertSame('Сразу и поправлено', $post->fresh()->title);
    }

    public function test_images_are_compressed_to_full_hd_webp(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $big = \Illuminate\Http\UploadedFile::fake()->image('photo.jpg', 4000, 3000);

        $url = app(BlogService::class)->storeImage($big);

        $this->assertStringEndsWith('.webp', $url);
        $files = \Illuminate\Support\Facades\Storage::disk('s3')->allFiles('blog');
        $this->assertCount(1, $files);
        [$w, $h] = getimagesizefromstring(\Illuminate\Support\Facades\Storage::disk('s3')->get($files[0]));
        $this->assertSame([1920, 1440], [$w, $h]);

        $gif = \Illuminate\Http\UploadedFile::fake()->image('anim.gif', 100, 100);
        $this->assertStringEndsWith('.gif', app(BlogService::class)->storeImage($gif));
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

    public function test_share_image_and_author_on_cards(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $teacher = $this->user(User::ROLE_TUTOR);
        $teacher->subjects()->attach(\App\Models\Subject::create(['name' => 'Физика'])->id);
        $post = $this->article(['author_id' => $teacher->id, 'tags' => ['ОГЭ']]);
        $this->article(['title' => 'Другая статья', 'author_id' => $teacher->id]);

        // На странице статьи — своя картинка для соцсетей, в блоке автора — его предметы
        $image = app(\App\Services\BlogShareImage::class)->url($post->fresh(['tags', 'author']));
        $this->get(route('blog.show', $post->slug))->assertOk()
            ->assertSee('<meta property="og:image" content="' . e($image) . '">', false)
            ->assertSee('blog-author-card-subjects', false)->assertSee('Физика');

        // Картинка собирается и сохраняется; черновик — только админу
        $response = $this->get(route('blog.og', $post->slug))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring($response->getContent()), 0, 2));
        $this->assertCount(1, \Illuminate\Support\Facades\Storage::disk('local')->files('blog-og'));
        // Картинка для сторис — вертикальная, в окне «Поделиться» на странице статьи
        $story = $this->get(route('blog.story', $post->slug))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame([1080, 1920], array_slice(getimagesizefromstring($story->getContent()), 0, 2));
        $this->assertCount(2, \Illuminate\Support\Facades\Storage::disk('local')->files('blog-og'));
        $this->get(route('blog.show', $post->slug))->assertSee('Поделиться статьей')
            ->assertSee(e(app(\App\Services\BlogShareImage::class)->url($post->fresh(['tags', 'author']), 'story')), false);
        $draft = $this->article(['title' => 'Черновик', 'published_at' => null]);
        $this->get(route('blog.og', $draft->slug))->assertNotFound();

        // Поменяли заголовок — новая версия, старый файл удален
        app(BlogService::class)->save($post, ['title' => 'Новый заголовок', 'body' => $post->body, 'published_at' => $post->published_at]);
        $this->assertNotSame($image, app(\App\Services\BlogShareImage::class)->url($post->fresh(['tags', 'author'])));
        $this->get(route('blog.og', $post->fresh()->slug))->assertOk();
        $this->assertCount(2, \Illuminate\Support\Facades\Storage::disk('local')->files('blog-og')); // новая OG и старая сторис
        $this->get(route('blog.story', $post->fresh()->slug))->assertOk();
        $this->assertCount(2, \Illuminate\Support\Facades\Storage::disk('local')->files('blog-og'));

        // В карточках ленты рядом с именем — фото автора (или инициалы), под именем — его предметы
        $this->get(route('blog.index'))->assertOk()->assertSee('blog-byline-photo', false)->assertSee($teacher->name)
            ->assertSeeInOrder(['blog-byline-subjects', 'Физика'], false);
    }

    public function test_slug_follows_title_until_published(): void
    {
        $service = app(BlogService::class);

        // Черновик сохраняется сам с первой буквы — адрес идет за заголовком
        $post = $service->save(null, ['title' => 'П', 'body' => '', 'published_at' => null]);
        $this->assertSame('p', $post->slug);
        $post = $service->save($post, ['title' => 'Память и экзамены', 'slug' => 'p', 'body' => '', 'published_at' => null]);
        $this->assertSame('pamyat-i-ekzameny', $post->slug);

        // Свой адрес не перезаписывается заголовком
        $post = $service->save($post, ['title' => 'Память и экзамены', 'slug' => 'svoy-adres', 'body' => '', 'published_at' => null]);
        $post = $service->save($post, ['title' => 'Память и экзамены: план', 'slug' => 'svoy-adres', 'body' => '', 'published_at' => null]);
        $this->assertSame('svoy-adres', $post->slug);

        // Опубликованная: заголовок адрес не меняет; очистили поле — адрес из заголовка, старый ведет на новый
        $post = $service->save($post, ['title' => 'Память и экзамены', 'slug' => 'svoy-adres', 'body' => '', 'published_at' => now()->subMinute()]);
        $post = $service->save($post, ['title' => 'Как запомнить материал', 'slug' => 'svoy-adres', 'body' => '', 'published_at' => $post->published_at]);
        $this->assertSame('svoy-adres', $post->slug);
        $post = $service->save($post, ['title' => 'Как запомнить материал', 'slug' => '', 'body' => '', 'published_at' => $post->published_at]);
        $this->assertSame('kak-zapomnit-material', $post->slug);
        $this->get('/blog/svoy-adres')->assertRedirect(route('blog.show', 'kak-zapomnit-material'));
    }
}
