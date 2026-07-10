<?php

namespace App\Console\Commands;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedHomeworkFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'homework:cleanup-orphaned-files
        {--force : Actually delete files (without this flag the command only reports what would be deleted)}
        {--chunk=500 : How many DB rows to load per chunk when collecting referenced paths}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete orphaned homework files from the CDN (s3) that are no longer referenced by any homework or submission';

    /**
     * Top-level S3 prefixes that hold homework-related files. Nothing outside
     * these prefixes is ever touched.
     */
    private const PREFIXES = [
        'homework-attachments',
        'homework-submissions',
        'feedback-attachments',
    ];

    public function handle(): int
    {
        $disk = Storage::disk('s3');
        $dryRun = ! $this->option('force');

        if ($dryRun) {
            $this->warn('DRY-RUN: файлы удалены не будут. Добавьте --force для реального удаления.');
        }

        // 1. Собираем все пути, на которые есть ссылки в БД.
        $this->info('Собираю используемые пути из базы...');
        $referenced = $this->collectReferencedPaths((int) $this->option('chunk'));
        $this->info('Найдено используемых файлов в БД: ' . number_format($referenced->count()));

        // 2. Перебираем файлы в целевых префиксах на S3 и удаляем осиротевшие.
        $orphanCount = 0;
        $deletedCount = 0;
        $totalScanned = 0;
        $freedBytes = 0;

        foreach (self::PREFIXES as $prefix) {
            $files = $disk->allFiles($prefix);
            $totalScanned += count($files);
            $this->line("Префикс {$prefix}/: файлов на CDN — " . number_format(count($files)));

            foreach ($files as $path) {
                if ($referenced->has($path)) {
                    continue;
                }

                $orphanCount++;

                $size = 0;
                try {
                    $size = $disk->size($path);
                } catch (\Throwable $e) {
                    // размер не критичен — игнорируем
                }
                $freedBytes += $size;

                if ($dryRun) {
                    $this->line('  [orphan] ' . $path . ' (' . $this->formatBytes($size) . ')');
                    continue;
                }

                if ($disk->delete($path)) {
                    $deletedCount++;
                    $this->line('  [deleted] ' . $path);
                } else {
                    $this->error('  [failed] ' . $path);
                }
            }
        }

        $this->newLine();
        $this->info('Просмотрено файлов на CDN: ' . number_format($totalScanned));
        $this->info('Осиротевших файлов: ' . number_format($orphanCount));

        if ($dryRun) {
            $this->info('Было бы освобождено: ' . $this->formatBytes($freedBytes));
            $this->warn('Это dry-run. Запустите с --force, чтобы удалить.');
        } else {
            $this->info('Удалено файлов: ' . number_format($deletedCount));
            $this->info('Освобождено: ' . $this->formatBytes($freedBytes));
        }

        return self::SUCCESS;
    }

    /**
     * Собрать множество всех путей файлов, на которые есть ссылки в БД.
     *
     * @return \Illuminate\Support\Collection<string, true>
     */
    private function collectReferencedPaths(int $chunk): \Illuminate\Support\Collection
    {
        $paths = collect();

        $add = function ($value) use ($paths) {
            foreach ((array) $value as $path) {
                if (is_string($path) && $path !== '') {
                    $paths->put($path, true);
                }
            }
        };

        Homework::query()
            ->select(['id', 'attachments'])
            ->chunkById($chunk, function ($homeworks) use ($add) {
                foreach ($homeworks as $homework) {
                    $add($homework->attachments);
                }
            });

        HomeworkSubmission::query()
            ->select(['id', 'attachments', 'annotated_files', 'annotated_images', 'feedback_attachments'])
            ->chunkById($chunk, function ($submissions) use ($add) {
                foreach ($submissions as $submission) {
                    $add($submission->attachments);
                    $add($submission->annotated_files);
                    $add($submission->annotated_images);
                    $add($submission->feedback_attachments);
                }
            });

        return $paths;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
