<?php

namespace App\Console\Commands;

use App\Services\EditorMediaService;
use Illuminate\Console\Command;

/** Удалить с хранилища картинки и видео из редактора, которые так и не попали ни в один сохраненный текст за сутки. */
class CleanupEditorMedia extends Command
{
    protected $signature = 'media:cleanup';

    protected $description = 'Удалить брошенные загрузки редактора новостей и блога';

    public function handle(EditorMediaService $media): int
    {
        $this->info('Удалено файлов: ' . $media->cleanup());

        return self::SUCCESS;
    }
}
