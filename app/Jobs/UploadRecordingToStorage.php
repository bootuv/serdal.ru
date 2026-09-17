<?php

namespace App\Jobs;

use App\Models\Recording;
use App\Models\User;
use App\Services\RecordingStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

class UploadRecordingToStorage implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Выгрузка сотен мегабайт в S3 может идти десятки минут на медленном канале.
     * Значение должно быть меньше retry_after подключения redis-long (config/queue.php).
     */
    public int $timeout = 7200;

    /**
     * Блокировка уникальности снимается сама, даже если воркер убит и не успел её снять.
     */
    public int $uniqueFor = 7500;

    /**
     * Запись удалили, пока задача ждала в очереди — молча выбрасываем задачу.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Recording $recording,
        public User $teacher
    ) {
        // Отдельная очередь со своим воркером: долгая выгрузка не должна блокировать
        // уведомления и broadcast-события основной очереди.
        $this->onConnection('redis-long')->onQueue('recordings');
    }

    /**
     * Одна запись — одна задача: вебхук, синхронизация и recordings:retry-uploads
     * могут поставить выгрузку одновременно.
     */
    public function uniqueId(): string
    {
        return (string) $this->recording->id;
    }

    public function backoff(): array
    {
        return [120, 600];
    }

    public function handle(RecordingStorageService $storageService): void
    {
        if (!$storageService->isConfigured()) {
            Log::warning('S3 Recording: S3 not configured, skipping upload', [
                'recording_id' => $this->recording->id,
            ]);
            return;
        }

        // Запись могли удалить или уже выгрузить, пока задача ждала в очереди
        $this->recording->refresh();

        // Skip non-video format recordings
        $url = $this->recording->url;
        if (!$url || !str_contains($url, '/playback/video/')) {
            Log::info('S3 Recording: Skipping non-video format', [
                'recording_id' => $this->recording->id,
                'url' => $url,
            ]);
            return;
        }

        // Skip if already uploaded to S3
        if ($this->recording->s3_url) {
            Log::info('S3 Recording: Already uploaded', [
                'recording_id' => $this->recording->id,
                's3_url' => $this->recording->s3_url,
            ]);
            return;
        }

        // Build video file URL - strip ALL whitespace/newlines from URL just in case
        $cleanUrl = preg_replace('/\s+/', '', $this->recording->url);
        $videoUrl = rtrim($cleanUrl, '/') . '/video-0.m4v';

        // Generate filename: {record_id}.m4v
        $filename = $this->recording->record_id . '.m4v';

        // Upload to S3
        $s3Url = $storageService->uploadToS3($videoUrl, $this->teacher->id, $filename);

        if (!$s3Url) {
            // Бросаем исключение, чтобы очередь повторила попытку (backoff), а после
            // исчерпания попыток задача попала в failed_jobs и сработал failed().
            throw new \RuntimeException("S3 Recording: upload failed for recording {$this->recording->id}");
        }

        $this->recording->update([
            's3_url' => $s3Url,
            's3_uploaded_at' => now(),
        ]);

        Log::info('S3 Recording: Upload successful', [
            'recording_id' => $this->recording->id,
            's3_url' => $s3Url,
        ]);

        // Delete from BBB if enabled
        if (Setting::where('key', 'recording_delete_after_upload')->value('value') === '1') {
            try {
                $globalUrl = Setting::where('key', 'bbb_url')->value('value');
                $globalSecret = Setting::where('key', 'bbb_secret')->value('value');
                if ($globalUrl && $globalSecret) {
                    config([
                        'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                        'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                    ]);
                }

                Log::info('S3 Recording: Deleting from BBB', [
                    'record_id' => $this->recording->record_id,
                ]);

                $deleteResponse = Bigbluebutton::deleteRecordings(['recordID' => $this->recording->record_id]);
                Log::info('S3 Recording: BBB delete response', ['response' => $deleteResponse]);
            } catch (\Exception $e) {
                Log::error('S3 Recording: Failed to delete from BBB', [
                    'record_id' => $this->recording->record_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Все попытки исчерпаны (в т.ч. по таймауту). Запись остаётся на BBB,
     * её подберёт recordings:retry-uploads.
     */
    public function failed(?\Throwable $e): void
    {
        Log::error('S3 Recording: Upload job failed permanently', [
            'recording_id' => $this->recording->id,
            'record_id' => $this->recording->record_id,
            'error' => $e?->getMessage(),
        ]);
    }
}
