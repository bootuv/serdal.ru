<?php

namespace App\Console\Commands;

use App\Services\HelpContentImporter;
use Illuminate\Console\Command;

/** Базу знаний — из database/help/*.md. Запускается при каждом деплое (deploy.sh). */
class SyncHelpArticles extends Command
{
    protected $signature = 'help:sync';

    protected $description = 'Обновить статьи базы знаний из database/help (правки из админки не затираются)';

    public function handle(HelpContentImporter $importer): int
    {
        $s = $importer->sync(database_path('help'));

        $this->info("Разделов создано: {$s['categories']}. Статей: новых {$s['created']}, обновлено {$s['updated']}, снято с публикации {$s['unpublished']}.");
        if ($s['edited'] || $s['skipped']) {
            $this->warn("Не тронуты: изменены в админке — {$s['edited']}, написаны в админке с тем же заголовком — {$s['skipped']}.");
        }

        return self::SUCCESS;
    }
}
