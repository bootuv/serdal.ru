<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCabinetRole;
use App\Livewire\Cabinet\Admin\News;
use App\Livewire\Cabinet\Admin\NewsItem;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Services\AnnouncementService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Новости (/cabinet/admin/news): черновики, публикация сразу и по времени, рассылка уведомлений. */
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

    public function test_access(): void
    {
        $this->get('/cabinet/admin/news')->assertRedirect(route('login'));
        $tutor = $this->user(User::ROLE_TUTOR);
        $this->actingAs($tutor)->get('/cabinet/admin/news')->assertRedirect(EnsureCabinetRole::homeFor($tutor));
        $this->actingAs($this->user(User::ROLE_ADMIN))->get('/cabinet/admin/news')->assertOk()
            ->assertSee('Написать новость')
            ->assertSee('Пока ничего не опубликовано');
        $this->get('/cabinet/admin/news/new')->assertOk()->assertSee('Настройки новости')->assertSee('Сохранить как черновик');
    }

    public function test_draft_is_not_visible_and_not_sent(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->user(User::ROLE_TUTOR);

        Livewire::actingAs($admin)->test(NewsItem::class, ['announcement' => 'new'])
            ->call('saveDraft')->assertHasErrors(['title' => 'required'])
            ->set('title', 'Обновление расписания')
            ->set('body', '<p>Теперь можно <b>переносить</b> занятия.</p><script>alert(1)</script>')
            ->call('saveDraft')
            ->assertRedirect();

        $a = Announcement::sole();
        $this->assertNull($a->published_at);
        $this->assertStringNotContainsString('script', (string) $a->body);
        $this->assertSame($admin->id, $a->created_by);
        Notification::assertNothingSent();

        Livewire::withQueryParams(['tab' => 'drafts'])->actingAs($admin)->test(News::class)->assertSee('Обновление расписания');
    }

    public function test_publish_now_notifies_audience_once(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $tutor = $this->user(User::ROLE_TUTOR);
        $blocked = $this->user(User::ROLE_TUTOR, ['is_blocked' => true]);
        $student = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($admin)->test(NewsItem::class, ['announcement' => 'new'])
            ->set('title', 'Плановые работы')
            ->set('important', true)
            ->set('sendMail', true)
            ->call('submit')
            ->assertSet('confirmPublish', true)
            ->assertSee('Уведомление получат 1 учитель')
            ->call('publish')
            ->assertRedirect();

        $a = Announcement::sole();
        $this->assertTrue($a->isPublished());
        $this->assertNotNull($a->notified_at);
        $this->assertTrue($a->is_important);

        Notification::assertSentTo($tutor, AnnouncementPublished::class, fn ($n, $channels) => in_array('mail', $channels, true));
        Notification::assertNotSentTo($blocked, AnnouncementPublished::class);
        Notification::assertNotSentTo($student, AnnouncementPublished::class);

        // Правка опубликованной не рассылает повторно и не меняет адресатов
        Livewire::actingAs($admin)->test(NewsItem::class, ['announcement' => (string) $a->id])
            ->assertSee('Прочитали')
            ->set('title', 'Плановые работы ночью')
            ->set('audience', Announcement::AUDIENCE_ALL)
            ->call('submit');
        $this->assertSame('Плановые работы ночью', $a->fresh()->title);
        $this->assertSame(Announcement::AUDIENCE_TEACHERS, $a->fresh()->audience);
        Notification::assertSentToTimes($tutor, AnnouncementPublished::class, 1);

        // Сняли с публикации и вернули — уведомление повторно не приходит
        $service = app(AnnouncementService::class);
        $service->unpublish($a->fresh());
        $service->save($a->fresh(), ['title' => 'Плановые работы ночью', 'published_at' => now()]);
        Notification::assertSentToTimes($tutor, AnnouncementPublished::class, 1);
    }

    public function test_students_and_all_audiences(): void
    {
        $tutor = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $admin = $this->user(User::ROLE_ADMIN);
        $service = app(AnnouncementService::class);

        $service->save(null, ['title' => 'Ученикам', 'audience' => Announcement::AUDIENCE_STUDENTS, 'published_at' => now()]);
        Notification::assertSentTo($student, AnnouncementPublished::class);
        Notification::assertNotSentTo($tutor, AnnouncementPublished::class);

        $service->save(null, ['title' => 'Всем', 'audience' => Announcement::AUDIENCE_ALL, 'published_at' => now()]);
        Notification::assertSentToTimes($student, AnnouncementPublished::class, 2);
        Notification::assertSentToTimes($tutor, AnnouncementPublished::class, 1);
        Notification::assertNotSentTo($admin, AnnouncementPublished::class);
    }

    public function test_scheduled_news_is_sent_by_command(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $tutor = $this->user(User::ROLE_TUTOR);

        Livewire::actingAs($admin)->test(NewsItem::class, ['announcement' => 'new'])
            ->set('title', 'Новые тарифы')
            ->set('when', 'later')
            ->call('submit')->assertHasErrors('publishAt')
            ->set('publishAt', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('submit')->assertHasErrors('publishAt')
            ->set('publishAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('submit')
            ->assertRedirect();

        $a = Announcement::sole();
        $this->assertTrue($a->isScheduled());
        Livewire::withQueryParams(['tab' => 'scheduled'])->actingAs($admin)->test(News::class)->assertSee('Новые тарифы')->assertSee('выйдет');

        $this->artisan('news:publish')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(25)->hours();
        $this->artisan('news:publish')->assertSuccessful();
        Notification::assertSentToTimes($tutor, AnnouncementPublished::class, 1);

        $this->artisan('news:publish')->assertSuccessful();
        Notification::assertSentToTimes($tutor, AnnouncementPublished::class, 1);
    }

    public function test_published_list_shows_read_stats_and_delete(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $tutor = $this->user(User::ROLE_TUTOR);
        $this->user(User::ROLE_TUTOR);
        $service = app(AnnouncementService::class);

        $a = $service->save(null, ['title' => 'Отзывы на странице', 'published_at' => now()]);
        $service->markRead($a, $tutor);

        Livewire::actingAs($admin)->test(News::class)->assertSee('Отзывы на странице')->assertSee('прочитали 1 из 2');

        Livewire::actingAs($admin)->test(NewsItem::class, ['announcement' => (string) $a->id])
            ->call('askDelete')->assertSee('Удалить новость?')
            ->call('delete')
            ->assertRedirect(route('cabinet.admin.news'));
        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('announcement_reads', 0);
    }

    public function test_autosave_creates_and_updates_draft_without_sending(): void
    {
        $page = Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(NewsItem::class, ['announcement' => 'new']);

        // Пустую не создаем
        $page->call('autosave');
        $this->assertSame(0, Announcement::count());

        $page->set('title', 'Запустили блог')->set('body', '<p>Текст</p>')->call('autosave')->assertSet('savedAt', now()->format('H:i'));
        $news = Announcement::sole();
        $this->assertNull($news->published_at);
        $this->assertSame($news->id, $page->get('announcementId'));

        $page->set('body', '<p>Новый текст</p>')->call('autosave');
        $this->assertSame(1, Announcement::count());
        $this->assertSame('<p>Новый текст</p>', $news->fresh()->body);
        Notification::assertNothingSent();

        // Опубликованную автосохранение не трогает
        $page->call('publish');
        $page->set('body', '<p>Правка</p>')->call('autosave');
        $this->assertSame('<p>Новый текст</p>', $news->fresh()->body);
    }

    public function test_news_marked_for_site_is_public(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $page = Livewire::actingAs($admin)->test(NewsItem::class, ['announcement' => 'new'])
            ->set('title', 'Запустили блог')->set('body', '<p>Теперь у Serdal есть блог</p>')->set('public', true)
            ->call('submit')->call('publish');
        $news = Announcement::sole();
        $this->assertTrue($news->is_public);
        $this->assertSame('zapustili-blog', $news->slug);

        // Видна всем без входа: список, страница, подвал, sitemap
        auth()->logout();
        $this->get('/news')->assertOk()->assertSee('Новости Serdal')->assertSee('Запустили блог')->assertSee('/news/zapustili-blog', false);
        $this->get('/news/zapustili-blog')->assertOk()->assertSee('Теперь у Serdal есть блог')->assertSee('NewsArticle', false);
        $this->get('/')->assertSee(route('news.index'), false);
        \Illuminate\Support\Facades\Cache::forget('seo.sitemap');
        $this->get('/sitemap.xml')->assertSee('/news/zapustili-blog', false);

        // Новость без отметки, черновик и запланированная на сайте не видны
        $service = app(AnnouncementService::class);
        $service->save(null, ['title' => 'Только в кабинете', 'body' => '<p>x</p>', 'published_at' => now()->subMinute()], $admin);
        $service->save(null, ['title' => 'Черновик на сайт', 'body' => '<p>x</p>', 'is_public' => true], $admin);
        $later = $service->save(null, ['title' => 'Завтрашняя', 'body' => '<p>x</p>', 'is_public' => true, 'published_at' => now()->addDay()], $admin);
        $this->get('/news')->assertDontSee('Только в кабинете')->assertDontSee('Черновик на сайт')->assertDontSee('Завтрашняя');
        $this->get('/news/' . $later->slug)->assertNotFound();

        // Убрали отметку — страница пропала; адрес при смене заголовка не меняется
        $service->save($news, ['title' => 'Запустили большой блог', 'body' => $news->body, 'is_public' => true, 'published_at' => $news->published_at], $admin);
        $this->assertSame('zapustili-blog', $news->fresh()->slug);
        $service->save($news->fresh(), ['title' => 'Запустили большой блог', 'body' => $news->body, 'is_public' => false, 'published_at' => $news->published_at], $admin);
        $this->get('/news/zapustili-blog')->assertNotFound();
    }

    public function test_public_news_share_preview_uses_first_image(): void
    {
        $service = app(AnnouncementService::class);
        $admin = $this->user(User::ROLE_ADMIN);
        $withImage = $service->save(null, ['title' => 'С картинкой', 'is_public' => true, 'published_at' => now()->subMinute(),
            'body' => '<p>Начало новости для превью</p><video src="https://cdn.example/news/v.mp4" poster="https://cdn.example/news/v.jpg" controls></video><img src="https://cdn.example/news/2.webp">'], $admin);
        $plain = $service->save(null, ['title' => 'Без картинки', 'body' => '<p>Текст</p>', 'is_public' => true, 'published_at' => now()->subMinute()], $admin);

        $this->get('/news/' . $withImage->slug)->assertOk()
            ->assertSee('<meta property="og:title" content="С картинкой — новости Serdal">', false)
            ->assertSee('<meta property="og:description" content="Начало новости для превью', false)
            ->assertSee('<meta property="og:image" content="https://cdn.example/news/v.jpg">', false)
            ->assertSee('<meta name="twitter:image" content="https://cdn.example/news/v.jpg">', false);

        // Без картинок — общая картинка сайта
        $this->get('/news/' . $plain->slug)->assertOk()->assertDontSee('cdn.example', false)->assertSee('property="og:image"', false);
    }
}
