<?php

namespace App\Services\Bbb;

use App\Events\RoomStatusUpdated;
use App\Models\BbbServer;
use App\Models\MeetingSession;
use App\Models\Room;
use Illuminate\Support\Facades\Log;

/**
 * Проверка серверов видеосвязи (раз в минуту, команда bbb:check-servers, и при сохранении в админке):
 * на связи ли, версия, подходящий алгоритм подписи, сколько занятий и участников — для распределения
 * занятий (BbbServerPool). Заодно сверка: занятие, которого на сервере уже нет, отмечается завершённым
 * (если вебхук о завершении потерялся), а идущее — идущим.
 */
class BbbServerMonitor
{
    /** Только что начатое занятие сервер может ещё не показывать в списке — не трогаем его столько минут. */
    private const FRESH_MINUTES = 2;

    public function __construct(private BbbClientFactory $api)
    {
    }

    public function check(BbbServer $server): BbbServer
    {
        try {
            $version = $this->api->version($server);
        } catch (\Throwable $e) {
            $version = null;
        }

        try {
            $meetings = $this->meetings($server);
        } catch (BbbChecksumException $e) {
            return $this->offline($server, 'Сервер не принял секретный ключ', $version);
        } catch (\Throwable $e) {
            Log::warning('Сервер видеосвязи не отвечает', ['server' => $server->host(), 'error' => $e->getMessage()]);

            return $this->offline($server, 'Сервер не отвечает', $version);
        }

        $server->fill([
            'is_online' => true,
            'error' => null,
            'version' => $version ?? $server->version,
            'meetings' => count($meetings),
            'participants' => array_sum(array_column($meetings, 'participants')),
            'meeting_loads' => array_column($meetings, 'participants', 'id'),
            'checked_at' => now(),
        ]);
        if ($server->exists) {
            $server->save();
        }

        $this->reconcile($server, array_column($meetings, 'id'));

        return $server;
    }

    /** Проверить все серверы. */
    public function checkAll(): void
    {
        foreach (app(BbbServerPool::class)->all() as $server) {
            $this->check($server);
        }
    }

    /**
     * Список занятий с подбором алгоритма подписи: сначала текущий, потом остальные.
     * Подошедший алгоритм запоминается — новые версии BBB могут не принимать sha1.
     */
    private function meetings(BbbServer $server): array
    {
        $current = $server->checksum ?: 'sha1';
        $algorithms = array_values(array_unique([$current, ...BbbServer::CHECKSUMS]));

        foreach ($algorithms as $algorithm) {
            $server->checksum = $algorithm;
            try {
                return $this->api->meetings($server);
            } catch (BbbChecksumException $e) {
                continue;
            } catch (\Throwable $e) {
                $server->checksum = $current;

                throw $e;
            }
        }

        $server->checksum = $current;

        throw new BbbChecksumException('Ни один алгоритм подписи не подошёл');
    }

    private function offline(BbbServer $server, string $error, ?string $version): BbbServer
    {
        $server->fill([
            'is_online' => false,
            'error' => $error,
            'version' => $version ?? $server->version,
            'checked_at' => now(),
        ]);
        if ($server->exists) {
            $server->save();
        }

        return $server;
    }

    /** Сверить отметки «идёт» у занятий этого сервера с тем, что на нём на самом деле идёт. */
    private function reconcile(BbbServer $server, array $runningIds): void
    {
        $rooms = fn () => Room::query()->when(
            $server->exists,
            fn ($q) => $q->where('bbb_server_id', $server->id),
            fn ($q) => $q->whereNull('bbb_server_id'),
        );

        $started = $rooms()->whereIn('meeting_id', $runningIds)->where('is_running', false)->update(['is_running' => true]);

        $stopped = 0;
        foreach ($rooms()->where('is_running', true)->whereNotIn('meeting_id', $runningIds)->get() as $room) {
            $session = MeetingSession::where('room_id', $room->id)
                ->where('meeting_id', $room->meeting_id)
                ->where('status', 'running')
                ->orderByDesc('started_at')
                ->first();

            if ($session?->started_at && $session->started_at->gt(now()->subMinutes(self::FRESH_MINUTES))) {
                continue;
            }

            $session?->update([
                'ended_at' => now(),
                'status' => 'completed',
                'pricing_snapshot' => $session->capturePricingSnapshot(),
            ]);

            $room->update(['is_running' => false]);
            $stopped++;
        }

        if ($started || $stopped) {
            RoomStatusUpdated::dispatch();
            Log::info('Сверка занятий с сервером видеосвязи', ['server' => $server->host(), 'started' => $started, 'stopped' => $stopped]);
        }
    }
}
