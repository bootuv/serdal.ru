<?php

namespace Tests\Feature\Admin;

use App\Jobs\ConvertVideo;
use App\Livewire\Cabinet\Admin\NewsItem;
use App\Models\Announcement;
use App\Models\User;
use App\Services\MediaService;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Видео и GIF в тексте новости: сжатие в очереди (MediaService), запрет публикации, пока видео не готово. */
class NewsVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('s3');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'admin' . uniqid(), 'is_active' => true, 'is_blocked' => false]);
    }

    /** Настоящий короткий ролик или GIF от ffmpeg (без ffmpeg тест пропускается). */
    private function sample(string $ext): \Illuminate\Http\Testing\File
    {
        if (! app(MediaService::class)->available()) {
            $this->markTestSkipped('ffmpeg не установлен');
        }
        $path = sys_get_temp_dir() . '/serdal-sample-' . uniqid() . '.' . $ext;
        $args = $ext === 'gif'
            ? ['-f', 'lavfi', '-i', 'testsrc=size=640x480:rate=10:duration=1']
            : ['-f', 'lavfi', '-i', 'testsrc=size=1920x1080:rate=25:duration=2', '-f', 'lavfi', '-i', 'sine=duration=2', '-shortest', '-pix_fmt', 'yuv420p'];
        Process::run(array_merge(['ffmpeg', '-y', '-loglevel', 'error'], $args, [$path]))->throw();

        $file = UploadedFile::fake()->createWithContent('clip.' . $ext, file_get_contents($path));
        @unlink($path);

        return $file;
    }

    public function test_video_is_compressed_in_queue_and_blocks_publishing_until_ready(): void
    {
        Queue::fake();
        $page = Livewire::actingAs($this->admin())->test(NewsItem::class, ['announcement' => 'new'])
            ->set('video', $this->sample('mp4'));
        $result = $page->instance()->storeVideo();

        $this->assertStringEndsWith('.mp4', $result['src']);
        $this->assertStringEndsWith('.jpg', $result['poster']);
        $this->assertFalse($result['loop']);
        Queue::assertPushed(ConvertVideo::class);
        $this->assertSame(['status' => 'pending', 'progress' => 0], $page->instance()->mediaStatus($result['src']));
        // Размер ролика известен сразу — редактор рисует заглушку в его пропорциях
        $this->assertSame([1280, 720], [$result['width'], $result['height']]);

        // Пока видео сжимается — не публикуем
        $body = '<p>Смотрите</p><video src="' . $result['src'] . '" poster="' . $result['poster'] . '" controls></video>';
        $page->set('title', 'Видео')->set('body', $body)->call('submit')->assertHasErrors('body');
        $this->assertSame(0, Announcement::count());

        // Задача отработала — MP4 до 1280 по длинной стороне и обложка на CDN, публиковать можно
        $job = Queue::pushed(ConvertVideo::class)->first();
        $job->handle(app(MediaService::class));
        $video = 'news/' . basename($result['src']);
        Storage::disk('s3')->assertExists([$video, 'news/' . basename($result['poster'])]);
        $this->assertSame('ready', $page->instance()->mediaStatus($result['src'])['status']);
        $this->assertFileDoesNotExist($job->source);

        $local = tempnam(sys_get_temp_dir(), 'v');
        file_put_contents($local, Storage::disk('s3')->get($video));
        $size = trim(Process::run(['ffprobe', '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height', '-of', 'csv=p=0', $local])->output());
        $this->assertSame('1280,720', $size);
        @unlink($local);

        $page->call('submit')->assertHasNoErrors()->call('publish');
        $this->assertStringContainsString('<video src="' . $result['src'] . '"', Announcement::first()->body);
    }

    public function test_gif_becomes_looping_video(): void
    {
        Queue::fake();
        $page = Livewire::actingAs($this->admin())->test(NewsItem::class, ['announcement' => 'new'])
            ->set('image', $this->sample('gif'));
        $result = $page->instance()->storeImage();

        $this->assertIsArray($result);
        $this->assertTrue($result['loop']);
        Queue::assertPushed(ConvertVideo::class, fn ($job) => $job->loop);
    }

    public function test_failed_video_blocks_publishing(): void
    {
        $media = app(MediaService::class);
        $media->markFailed('news/broken.mp4', '/nonexistent');

        Livewire::actingAs($this->admin())->test(NewsItem::class, ['announcement' => 'new'])
            ->set('title', 'Видео')->set('body', '<video src="https://cdn.example/news/broken.mp4" controls></video>')
            ->call('submit')->assertHasErrors('body');
    }

    public function test_video_survives_cleaning(): void
    {
        $gif = '<video src="https://cdn.example/news/a.mp4" autoplay loop muted playsinline></video>';
        $this->assertStringContainsString('autoplay', RichText::clean($gif));
        // Новость только из видео — не пустая
        $this->assertNotNull(RichText::clean($gif));
        $this->assertNotNull(RichText::html('<video src="https://cdn.example/news/a.mp4" controls></video>'));
    }

    public function test_video_is_shown_in_our_player_and_gif_stays_looping(): void
    {
        $html = RichText::players(RichText::html('<p>Смотрите</p><video src="https://cdn.example/news/a.mp4" poster="https://cdn.example/news/a.jpg" controls preload="metadata"></video>'
            . '<video src="https://cdn.example/news/b.mp4" autoplay loop muted playsinline></video>'));

        $this->assertStringContainsString('x-data="videoPlayer"', (string) $html);
        $this->assertStringContainsString('src="https://cdn.example/news/a.mp4"', (string) $html);
        $this->assertStringContainsString('poster="https://cdn.example/news/a.jpg"', (string) $html);
        $this->assertStringContainsString('aria-label="Смотреть видео"', (string) $html);
        $this->assertSame(1, substr_count((string) $html, 'x-data="videoPlayer"'));
        $this->assertStringContainsString('<video src="https://cdn.example/news/b.mp4" autoplay loop muted playsinline></video>', (string) $html);
    }

    public function test_video_removed_while_compressing_is_never_uploaded(): void
    {
        Queue::fake();
        $page = Livewire::actingAs($this->admin())->test(NewsItem::class, ['announcement' => 'new'])
            ->set('video', $this->sample('mp4'));
        $result = $page->instance()->storeVideo();

        // Удалили кнопкой, пока ролик в очереди, — задача ничего не выкладывает и убирает исходник
        $page->call('discardMedia', $result['src']);
        $job = Queue::pushed(ConvertVideo::class)->first();
        $job->handle(app(MediaService::class));

        Storage::disk('s3')->assertMissing(['news/' . basename($result['src']), 'news/' . basename($result['poster'])]);
        $this->assertFileDoesNotExist($job->source);
        $this->assertSame(0, \App\Models\EditorMedia::count());
    }
}
