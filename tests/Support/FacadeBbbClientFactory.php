<?php

namespace Tests\Support;

use App\Models\BbbServer;
use App\Services\Bbb\BbbClientFactory;
use JoisarJignesh\Bigbluebutton\Bbb;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

/**
 * Клиенты серверов видеосвязи в тестах — фасад Bigbluebutton: тесты подменяют его ответы
 * (Bigbluebutton::shouldReceive) и не ходят в сеть. Какой сервер выбран, тесты проверяют
 * по записям в базе (bbb_server_id), а ответы сервера — через фасад.
 */
class FacadeBbbClientFactory extends BbbClientFactory
{
    public function make(BbbServer $server): Bbb
    {
        return Bigbluebutton::getFacadeRoot();
    }

    public function meetings(BbbServer $server): array
    {
        return collect(Bigbluebutton::all())
            ->map(fn ($m) => ['id' => (string) ($m['meetingID'] ?? ''), 'participants' => (int) ($m['participantCount'] ?? 0)])
            ->values()
            ->all();
    }

    public function recordings(BbbServer $server, array $params = []): array
    {
        return collect(Bigbluebutton::getRecordings($params))->values()->all();
    }

    public function version(BbbServer $server): ?string
    {
        return null;
    }
}
