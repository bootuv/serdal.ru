<?php

namespace App\Services;

use App\Models\BbbServer;
use App\Models\Recording;
use App\Services\Bbb\BbbClientFactory;
use App\Services\Bbb\BbbServerPool;
use Illuminate\Support\Carbon;

/**
 * Синхронизация всех записей со всех серверов видеосвязи («Обновить с сервера» в админке).
 * Удалённые у нас записи не возвращаются.
 */
class RecordingSyncService
{
    /**
     * Подтянуть опубликованные и обрабатываемые записи со всех серверов. Сбой одного сервера не мешает
     * остальным; если не ответил ни один — ошибка пробрасывается, экран показывает её сам.
     *
     * @return int сколько записей обновлено или добавлено
     */
    public function syncAll(): int
    {
        $servers = app(BbbServerPool::class)->all();
        $count = 0;
        $error = null;
        $answered = 0;

        foreach ($servers as $server) {
            try {
                $count += $this->syncServer($server);
                $answered++;
            } catch (\Throwable $e) {
                $error = $e;
                report($e);
            }
        }

        if ($error && $answered === 0) {
            throw $error;
        }

        return $count;
    }

    private function syncServer(BbbServer $server): int
    {
        $count = 0;

        foreach (app(BbbClientFactory::class)->recordings($server, ['state' => 'published,processing']) as $rec) {
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
                'bbb_server_id' => $server->id,
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
