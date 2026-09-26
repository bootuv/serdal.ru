<?php

namespace App\Services;

use App\Models\Recording;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

/**
 * Синхронизация всех записей с общего сервера видеосвязи («Обновить с сервера» в админке,
 * «Синхронизировать» в старой админке RecordingResource). Удалённые у нас записи не возвращаются.
 */
class RecordingSyncService
{
    /**
     * Подтянуть опубликованные и обрабатываемые записи. Ошибки сервера пробрасываются — экран показывает их сам.
     *
     * @return int сколько записей обновлено или добавлено
     */
    public function syncAll(): int
    {
        $globalUrl = Setting::where('key', 'bbb_url')->value('value');
        $globalSecret = Setting::where('key', 'bbb_secret')->value('value');
        if ($globalUrl && $globalSecret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
            ]);
        }

        $count = 0;

        foreach (collect(Bigbluebutton::getRecordings(['state' => 'published,processing'])) as $rec) {
            $r = (array) $rec;

            $meetingID = trim((string) ($r['meetingID'] ?? ''));
            $recordID = trim((string) ($r['recordID'] ?? ''));
            if ($meetingID === '' || $recordID === '') {
                continue;
            }

            $publishedStr = trim((string) ($r['published'] ?? 'false'));
            $state = trim((string) ($r['state'] ?? 'unknown'));
            $startTimeRaw = trim((string) ($r['startTime'] ?? ''));
            $endTimeRaw = trim((string) ($r['endTime'] ?? ''));

            $isPublished = ($publishedStr === 'true' || $publishedStr === '1');
            $startTime = $startTimeRaw ? Carbon::createFromTimestamp($startTimeRaw / 1000) : null;

            // Пропускаем удалённые и «зависшие» записи
            if (in_array($state, ['deleted', 'unpublished'], true) || (! $isPublished && (! $startTime || $startTime->lt(now()->subHours(24))))) {
                continue;
            }

            $recording = Recording::withTrashed()->where('record_id', $recordID)->first();
            if ($recording?->trashed()) {
                continue;
            }
            $recording ??= new Recording(['record_id' => $recordID]);

            $format = $r['playback']['format'] ?? [];
            $url = $format['url'] ?? ($format[0]['url'] ?? null);

            $recording->fill([
                'meeting_id' => $meetingID,
                'name' => trim((string) ($r['name'] ?? '')),
                'published' => $isPublished,
                'start_time' => $startTime,
                'end_time' => $endTimeRaw ? Carbon::createFromTimestamp($endTimeRaw / 1000) : null,
                'participants' => (int) trim((string) ($r['participants'] ?? '0')),
                'url' => $url ? trim((string) $url) : null,
                'raw_data' => json_decode(json_encode($r), true),
            ]);
            $recording->save();

            // Заглушка этого занятия больше не нужна
            Recording::where('meeting_id', $meetingID)->where('record_id', 'like', '%-placeholder-%')->delete();
            $count++;
        }

        return $count;
    }

    /** Удалить запись: с сервера видеосвязи, из хранилища (Recording::booted) и из списка. */
    public function delete(Recording $recording): void
    {
        app(TeacherRecordingsService::class)->deleteFromServer($recording);
        $recording->delete();
    }
}
