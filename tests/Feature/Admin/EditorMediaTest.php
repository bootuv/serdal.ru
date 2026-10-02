<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\NewsItem;
use App\Models\Announcement;
use App\Models\BlogPost;
use App\Models\EditorMedia;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\BlogService;
use App\Services\EditorMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Файлы из редактора новостей и блога не копятся на хранилище: удаление кнопкой, при сохранении без них, брошенные. */
class EditorMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('s3');
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'a' . uniqid(), 'is_active' => true, 'is_blocked' => false]);
    }

    /** Картинка, загруженная через экран новости: адрес и путь на хранилище. */
    private function upload($page): array
    {
        $url = $page->set('image', UploadedFile::fake()->image('photo.jpg', 800, 600))->instance()->storeImage();
        $path = app(EditorMediaService::class)->pathFromUrl($url);
        Storage::disk('s3')->assertExists($path);

        return [$url, $path];
    }

    public function test_remove_button_deletes_unsaved_file_at_once(): void
    {
        $page = Livewire::actingAs($this->admin)->test(NewsItem::class, ['announcement' => 'new']);
        [$url, $path] = $this->upload($page);

        $page->call('discardMedia', $url);
        Storage::disk('s3')->assertMissing($path);
        $this->assertSame(0, EditorMedia::count());
    }

    public function test_file_removed_from_saved_text_is_deleted_on_save(): void
    {
        $page = Livewire::actingAs($this->admin)->test(NewsItem::class, ['announcement' => 'new']);
        [$url, $path] = $this->upload($page);
        [$keep, $keepPath] = $this->upload($page);

        $page->set('title', 'С картинками')->set('body', '<p>Текст</p><img src="' . $url . '"><img src="' . $keep . '">')->call('autosave');
        $news = Announcement::sole();
        $this->assertSame(2, EditorMedia::where('owner_id', $news->id)->count());

        // Кнопка «Удалить» у сохраненной картинки не удаляет файл сразу: текст на сайте еще ссылается на него
        $page->call('discardMedia', $url);
        Storage::disk('s3')->assertExists($path);

        // Сохранили без нее — файл удален, вторая картинка на месте
        $page->set('body', '<p>Текст</p><img src="' . $keep . '">')->call('autosave');
        Storage::disk('s3')->assertMissing($path);
        Storage::disk('s3')->assertExists($keepPath);

        // Новость удалили — и ее файлы
        app(AnnouncementService::class)->delete($news->fresh());
        Storage::disk('s3')->assertMissing($keepPath);
        $this->assertSame(0, EditorMedia::count());
    }

    public function test_abandoned_uploads_are_cleaned_after_a_day_and_shared_files_are_kept(): void
    {
        $page = Livewire::actingAs($this->admin)->test(NewsItem::class, ['announcement' => 'new']);
        [$abandoned, $abandonedPath] = $this->upload($page);
        [$fresh, $freshPath] = $this->upload($page);
        EditorMedia::where('url', $abandoned)->update(['created_at' => now()->subHours(25)]);

        $this->artisan('media:cleanup')->assertSuccessful();
        Storage::disk('s3')->assertMissing($abandonedPath);
        Storage::disk('s3')->assertExists($freshPath);

        // Картинку скопировали в статью блога — из новости ее убрали, но файл нужен статье
        $blog = app(BlogService::class);
        $news = app(AnnouncementService::class)->save(null, ['title' => 'Новость', 'body' => '<img src="' . $fresh . '">'], $this->admin);
        $post = $blog->save(null, ['title' => 'Статья', 'body' => '<p>Текст</p>', 'cover_url' => $fresh], $this->admin);
        app(AnnouncementService::class)->save($news, ['title' => 'Новость', 'body' => '<p>Без картинки</p>'], $this->admin);
        Storage::disk('s3')->assertExists($freshPath);

        // Обложку статьи сменили — теперь файл не нужен никому
        $blog->save($post, ['title' => 'Статья', 'body' => '<p>Текст</p>', 'cover_url' => '']);
        Storage::disk('s3')->assertMissing($freshPath);
    }

    public function test_cannot_delete_someone_elses_upload(): void
    {
        $page = Livewire::actingAs($this->admin)->test(NewsItem::class, ['announcement' => 'new']);
        [$url, $path] = $this->upload($page);

        $other = User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'b' . uniqid(), 'is_active' => true, 'is_blocked' => false]);
        Livewire::actingAs($other)->test(NewsItem::class, ['announcement' => 'new'])->call('discardMedia', $url);
        Storage::disk('s3')->assertExists($path);
    }
}
