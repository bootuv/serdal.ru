<?php

namespace App\Console\Commands;

use App\Jobs\ConvertAvatarToWebp;
use App\Models\User;
use Illuminate\Console\Command;

/** Перевести старые фото профилей в WebP (задачи в очередь). Запускается при деплое; переведённые пропускает. */
class ConvertAvatarsToWebp extends Command
{
    protected $signature = 'avatars:webp';

    protected $description = 'Перевести загруженные раньше фото профилей в WebP с копиями для списков и превью';

    public function handle(): int
    {
        // Без WebP в GD перевод невозможен (новые фото тогда тоже сохраняются в исходном формате)
        if (! function_exists('imagewebp')) {
            $this->warn('PHP собран без поддержки WebP (GD): фото не переводим. Нужен пакет php8.4-gd с libwebp.');

            return self::SUCCESS;
        }

        $count = 0;
        User::whereNotNull('avatar')
            ->where('avatar', '!=', '')
            ->where('avatar', 'not like', '%.webp')
            ->select(['id', 'avatar'])
            ->chunkById(200, function ($users) use (&$count) {
                foreach ($users as $user) {
                    ConvertAvatarToWebp::dispatch($user);
                    $count++;
                }
            });

        $this->info("Фото в очереди на перевод: {$count}");

        return self::SUCCESS;
    }
}
