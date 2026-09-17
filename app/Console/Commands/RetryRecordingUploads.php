<?php

namespace App\Console\Commands;

use App\Jobs\UploadRecordingToStorage;
use App\Models\Recording;
use App\Models\Setting;
use App\Services\RecordingStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryRecordingUploads extends Command
{
    protected $signature = 'recordings:retry-uploads {--days=30 : Не трогать записи старше N дней}';

    protected $description = 'Повторно ставит в очередь выгрузку в S3 для записей, зависших в статусе «Загрузка», и чистит хвосты убитых выгрузок';

    public function handle(RecordingStorageService $storageService): int
    {
        if (!$storageService->isConfigured()) {
            $this->warn('S3 не настроен');

            return self::SUCCESS;
        }

        $dispatched = 0;

        if (Setting::where('key', 'recording_auto_upload')->value('value') === '1') {
            // Видео готово на BBB, но в S3 не попало: задача упала, воркер убит, деплой и т.п.
            // Свежие записи не трогаем — их выгрузка, скорее всего, ещё идёт.
            $stuck = Recording::with('room.user')
                ->whereNull('s3_url')
                ->where('url', 'like', '%/playback/video/%')
                ->where('record_id', 'not like', '%-placeholder-%')
                ->where('created_at', '>', now()->subDays((int) $this->option('days')))
                ->where('updated_at', '<', now()->subMinutes(30))
                ->get();

            foreach ($stuck as $recording) {
                $teacher = $recording->room?->user;

                if (!$teacher) {
                    Log::warning('S3 Recording: Retry skipped, room owner not found', [
                        'recording_id' => $recording->id,
                    ]);
                    continue;
                }

                // Задача ShouldBeUnique: если выгрузка этой записи уже в очереди, дубль не создастся
                UploadRecordingToStorage::dispatch($recording, $teacher);
                $dispatched++;
            }
        }

        $tempFiles = $storageService->cleanupStaleTempFiles();

        try {
            $multipart = $storageService->abortStaleMultipartUploads();
        } catch (\Throwable $e) {
            $multipart = 0;
            Log::warning('S3 Recording: Multipart cleanup failed', ['message' => $e->getMessage()]);
        }

        if ($dispatched || $tempFiles || $multipart) {
            Log::info('S3 Recording: Retry run', [
                'dispatched' => $dispatched,
                'temp_files_deleted' => $tempFiles,
                'multipart_aborted' => $multipart,
            ]);
        }

        $this->info("Поставлено в очередь: {$dispatched}, удалено временных файлов: {$tempFiles}, отменено multipart-загрузок: {$multipart}");

        return self::SUCCESS;
    }
}
