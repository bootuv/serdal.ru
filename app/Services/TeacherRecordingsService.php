<?php

namespace App\Services;

use App\Jobs\SyncUserRecordings;
use App\Models\Recording;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

/**
 * Записи занятий учителя: синхронизация с сервером занятий и удаление.
 */
class TeacherRecordingsService
{
    /** Подтянуть новые записи с сервера занятий в фоне — не чаще раза в минуту. */
    public function syncInBackground(User $teacher): void
    {
        try {
            $cacheKey = "last_recordings_sync_{$teacher->id}";

            if (! Cache::has($cacheKey)) {
                SyncUserRecordings::dispatch($teacher);
                Cache::put($cacheKey, true, 60);
            }
        } catch (\Throwable $e) {
            Log::error('Recording Sync Dispatch Error: ' . $e->getMessage());
        }
    }

    /**
     * Удалить запись на сервере занятий (BBB). Ошибки не мешают локальному удалению.
     * Файл в хранилище удаляет сама модель (Recording::booted, событие deleted).
     */
    public function deleteFromServer(Recording $recording): void
    {
        try {
            $globalUrl = Setting::where('key', 'bbb_url')->value('value');
            $globalSecret = Setting::where('key', 'bbb_secret')->value('value');
            if ($globalUrl && $globalSecret) {
                config([
                    'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                    'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                ]);
            }

            Log::info('Attempting to delete recording from BBB', [
                'record_id' => $recording->record_id,
                'bbb_url' => config('bigbluebutton.BBB_SERVER_BASE_URL'),
            ]);

            $response = Bigbluebutton::deleteRecordings(['recordID' => $recording->record_id]);
            Log::info('BBB Delete Recording Response', ['record_id' => $recording->record_id, 'response' => $response]);
        } catch (\Exception $e) {
            Log::error('BBB Delete Recording Error', ['record_id' => $recording->record_id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Удалить записи учителя (только свои): с сервера занятий, из хранилища и из списка.
     *
     * @return int сколько удалено
     */
    public function delete(User $teacher, array $ids): int
    {
        /** @var Collection<int, Recording> $recordings */
        $recordings = Recording::forTeacher($teacher)->whereKey($ids)->get();

        foreach ($recordings as $recording) {
            $this->deleteFromServer($recording);
            $recording->delete();
        }

        return $recordings->count();
    }
}
