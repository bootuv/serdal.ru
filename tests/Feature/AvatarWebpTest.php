<?php

namespace Tests\Feature;

use App\Jobs\ConvertAvatarToWebp;
use App\Models\User;
use App\Services\HelpCenterService;
use App\Services\TeacherProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Фото профилей и картинки справки — в WebP; для превью ссылок — копия в JPG. */
class AvatarWebpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    private function tutor(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'role' => User::ROLE_TUTOR,
            'username' => 'webp-tutor',
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    public function test_uploaded_photo_is_saved_as_webp_with_list_and_preview_copies(): void
    {
        $tutor = $this->tutor();

        app(TeacherProfileService::class)->update($tutor, ['avatar' => UploadedFile::fake()->image('me.jpg', 1200, 900)]);

        $tutor->refresh();
        $this->assertStringEndsWith('.webp', $tutor->avatar);
        $this->assertStringStartsWith('avatars/' . $tutor->id . '/', $tutor->avatar);

        $base = substr($tutor->avatar, 0, -5);
        foreach ([$tutor->avatar, $base . '-256.webp', $base . '.jpg'] as $path) {
            Storage::disk('s3')->assertExists($path);
        }
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('s3')->get($tutor->avatar))['mime']);
        $this->assertSame('image/jpeg', getimagesizefromstring(Storage::disk('s3')->get($base . '.jpg'))['mime']);
        [$width, $height] = getimagesizefromstring(Storage::disk('s3')->get($base . '-256.webp'));
        $this->assertSame([256, 192], [$width, $height]);

        $this->assertStringEndsWith('-256.webp', $tutor->avatarThumbUrl);
        $this->assertStringEndsWith($base . '.jpg', $tutor->avatarJpgUrl);

        // Новое фото — старое удаляется вместе с копиями
        $old = $tutor->avatar;
        app(TeacherProfileService::class)->update($tutor, ['avatar' => UploadedFile::fake()->image('new.png', 300, 300)]);
        foreach ([$old, $base . '-256.webp', $base . '.jpg'] as $path) {
            Storage::disk('s3')->assertMissing($path);
        }
    }

    public function test_public_pages_use_small_copy_in_lists_and_jpg_for_link_preview(): void
    {
        $tutor = $this->tutor();
        app(TeacherProfileService::class)->update($tutor, ['avatar' => UploadedFile::fake()->image('me.jpg', 800, 800)]);
        $tutor->refresh();
        $base = substr($tutor->avatar, 0, -5);

        $this->get('/')->assertSee($base . '-256.webp', false);

        $this->get('/webp-tutor')
            ->assertSee('<meta property="og:image" content="' . Storage::disk('s3')->url($base . '.jpg') . '">', false)
            ->assertSee('src="' . Storage::disk('s3')->url($tutor->avatar) . '"', false);
    }

    public function test_old_photos_are_converted_once_without_touching_profile_date(): void
    {
        Storage::disk('s3')->put('avatars/7/old.jpg', UploadedFile::fake()->image('old.jpg', 700, 700)->get());
        $legacy = $this->tutor(['avatar' => 'avatars/7/old.jpg']);
        $this->tutor(['username' => 'already-webp', 'avatar' => 'avatars/8/done.webp']);
        $updatedAt = $legacy->fresh()->updated_at;

        Queue::fake();
        $this->artisan('avatars:webp')->assertSuccessful();
        Queue::assertPushed(ConvertAvatarToWebp::class, 1);
        Queue::assertPushed(ConvertAvatarToWebp::class, fn ($job) => $job->user->is($legacy));

        $this->travel(1)->hours();
        (new ConvertAvatarToWebp($legacy->fresh()))->handle(app(\App\Services\AvatarService::class));

        $legacy->refresh();
        $this->assertStringStartsWith('avatars/7/', $legacy->avatar);
        $this->assertStringEndsWith('.webp', $legacy->avatar);
        Storage::disk('s3')->assertMissing('avatars/7/old.jpg');
        Storage::disk('s3')->assertExists(substr($legacy->avatar, 0, -5) . '.jpg');
        $this->assertEquals($updatedAt, $legacy->updated_at);

        // Старое фото до перевода: копий нет — отдаём основной файл
        $notConverted = new User(['avatar' => 'avatars/9/photo.png']);
        $this->assertStringEndsWith('avatars/9/photo.png', $notConverted->avatarThumbUrl);
        $this->assertStringEndsWith('avatars/9/photo.png', $notConverted->avatarJpgUrl);
    }

    public function test_help_images_are_saved_as_webp_and_gifs_stay_animated(): void
    {
        $service = app(HelpCenterService::class);

        $url = $service->storeImage(UploadedFile::fake()->image('shot.png', 2400, 1200));
        $this->assertStringEndsWith('.webp', $url);
        $path = Storage::disk('s3')->files('help-articles')[0];
        [$width] = getimagesizefromstring(Storage::disk('s3')->get($path));
        $this->assertSame(1600, $width);

        $gif = $service->storeImage(UploadedFile::fake()->image('anim.gif', 100, 100));
        $this->assertStringEndsWith('.gif', $gif);
    }
}
