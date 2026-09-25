<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use App\Models\Room;
use App\Services\RecordingStorageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecordingDownloadController extends Controller
{
    /**
     * Скачивание записи урока. Отдаём не сам файл, а редирект на временную ссылку S3
     * с Content-Disposition: attachment — иначе браузер откроет видео, а не скачает его.
     */
    public function __invoke(Recording $recording, RecordingStorageService $storage)
    {
        $user = auth()->user();

        // Доступ — как на странице просмотра: админ, владелец комнаты или ученик владельца
        if (!$user->isAdmin()) {
            $ownerId = Room::where('meeting_id', $recording->meeting_id)->value('user_id');
            $isOwner = $ownerId === $user->id;
            $isStudent = $ownerId && $user->teachers()->where('users.id', $ownerId)->exists();

            if (!$isOwner && !$isStudent) {
                abort(403);
            }
        }

        if (empty($recording->s3_url)) {
            abort(404);
        }

        $path = $storage->pathFromUrl($recording->s3_url);
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'mp4';
        $filename = $this->filename($recording) . '.' . $extension;

        try {
            $url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30), [
                'ResponseContentDisposition' => sprintf(
                    "attachment; filename=\"%s\"; filename*=UTF-8''%s",
                    Str::ascii($filename),
                    rawurlencode($filename),
                ),
            ]);
        } catch (\Throwable $e) {
            // Хранилище без подписанных ссылок — отдаём публичную, браузер хотя бы откроет видео
            Log::warning('Recording download: temporaryUrl failed', [
                'recording_id' => $recording->id,
                'message' => $e->getMessage(),
            ]);
            $url = $recording->s3_url;
        }

        return redirect()->away($url);
    }

    private function filename(Recording $recording): string
    {
        $date = $recording->start_time?->setTimezone('Europe/Moscow')->format('Y-m-d H-i');
        $name = trim(($recording->name ?: 'Запись урока') . ($date ? " {$date}" : ''));

        // Убираем символы, недопустимые в именах файлов
        return trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $name)) ?: 'recording';
    }
}
