<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CachePresentationSize implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $path)
    {
    }

    public function handle(): void
    {
        try {
            if (Cache::has("file_size_{$this->path}")) {
                return;
            }

            if (Storage::disk('s3')->exists($this->path)) {
                $size = Storage::disk('s3')->size($this->path);
                Cache::put("file_size_{$this->path}", $size, 86400 * 30); // 30 days
            }
        } catch (\Exception $e) {
            // Ignore errors (file not found, etc)
        }
    }
}
