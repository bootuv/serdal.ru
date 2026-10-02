<?php

namespace App\Jobs;

use App\Services\MediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Сжатие видео из текста новости (MediaService::convert). Долгая задача — в очередь записей со своим воркером. */
class ConvertVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** Меньше retry_after подключения redis-long (config/queue.php). */
    public int $timeout = 3600;

    public function __construct(
        public string $source,
        public string $video,
        public string $poster,
        public bool $loop = false,
    ) {
        // На проде — отдельный воркер долгой очереди (serdal-queue-recordings.service), локально — обычная очередь
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis-long')->onQueue('recordings');
        }
    }

    public function backoff(): array
    {
        return [60];
    }

    public function handle(MediaService $media): void
    {
        $media->convert($this->source, $this->video, $this->poster, $this->loop);
    }

    public function failed(?\Throwable $e): void
    {
        app(MediaService::class)->markFailed($this->video, $this->source);
    }
}
