<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Старое фото профиля (JPG/PNG) → WebP с копиями для списков и превью ссылок (AvatarService).
 * Ставит команда avatars:webp. Уже переведённые и пропавшие файлы пропускает.
 */
class ConvertAvatarToWebp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public User $user)
    {
    }

    public function handle(AvatarService $avatars): void
    {
        $old = $this->user->avatar;
        if (! $old || AvatarService::isWebp($old)) {
            return;
        }

        $disk = Storage::disk('s3');
        if (! $disk->exists($old)) {
            Log::warning('ConvertAvatarToWebp: файла нет', ['user' => $this->user->id, 'path' => $old]);

            return;
        }

        $new = $avatars->storeContents((string) $disk->get($old), dirname($old));

        // Без событий и без updated_at: смена формата фото — не правка профиля (дата идёт в карту сайта)
        $this->user->timestamps = false;
        $this->user->forceFill(['avatar' => $new])->saveQuietly();

        $disk->delete($old);
    }
}
