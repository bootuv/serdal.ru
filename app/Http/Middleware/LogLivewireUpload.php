<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogLivewireUpload
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log all upload requests
        if (str_contains($request->path(), 'livewire/upload-file')) {
            Log::info('Livewire Upload Request', [
                'path' => $request->path(),
                'method' => $request->method(),
                'has_files' => $request->hasFile('files'),
                'all_files' => array_keys($request->allFiles()),
                'files_count' => count($request->allFiles()),
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
                'livewire_config_disk' => config('livewire.temporary_file_upload.disk'),
                'filesystem_default' => config('filesystems.default'),
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $key => $file) {
                    Log::info("Livewire Upload File [$key]", [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'error' => $file->getError(),
                        'error_message' => $file->getErrorMessage(),
                        'is_valid' => $file->isValid(),
                    ]);
                }
            }
        }

        $response = $next($request);

        // Log response for upload failures
        if (str_contains($request->path(), 'livewire/upload-file') && $response->getStatusCode() >= 400) {
            Log::error('Livewire Upload Failed', [
                'status_code' => $response->getStatusCode(),
                'content' => $response->getContent(),
            ]);
        }

        return $response;
    }
}
