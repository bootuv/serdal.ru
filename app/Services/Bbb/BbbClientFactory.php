<?php

namespace App\Services\Bbb;

use App\Models\BbbServer;
use BigBlueButton\BigBlueButton;
use BigBlueButton\Http\Transport\CurlTransport;
use BigBlueButton\Parameters\GetRecordingsParameters;
use JoisarJignesh\Bigbluebutton\Bbb;

/**
 * Клиенты API серверов видеосвязи. Библиотека держит один клиент на процесс (синглтон из конфига),
 * и подмена адреса через config() в воркере очереди после первого обращения уже не действует —
 * поэтому клиент каждого сервера создаём сами, со своим адресом, ключом и алгоритмом подписи.
 * В тестах привязка заменяется на фасад Bigbluebutton, чтобы работали его моки (tests/TestCase).
 */
class BbbClientFactory
{
    /** Секунд на соединение и на весь запрос. */
    private const CONNECT_TIMEOUT = 5;

    private const TIMEOUT = 30;

    public function make(BbbServer $server): Bbb
    {
        return new Bbb($this->api($server));
    }

    /**
     * Идущие на сервере занятия (для проверки сервера). В отличие от Bbb::all() не прячет сбой
     * за пустым списком: иначе неверный ключ выглядел бы как «занятий нет» и сверка закрыла бы все занятия.
     *
     * @return list<array{id:string, participants:int}>
     *
     * @throws BbbChecksumException ключ или алгоритм подписи не подходят
     * @throws \Throwable сервер недоступен или ответил ошибкой
     */
    public function meetings(BbbServer $server): array
    {
        $response = $this->api($server)->getMeetings();

        if ($response->hasChecksumError()) {
            throw new BbbChecksumException($response->getMessage());
        }
        if (! $response->success()) {
            throw new \RuntimeException($response->getMessage() ?: 'Сервер ответил ошибкой');
        }

        return array_map(fn ($m) => [
            'id' => (string) $m->getMeetingId(),
            'participants' => (int) $m->getParticipantCount(),
        ], $response->getMeetings());
    }

    /**
     * Записи на сервере. Сбой — исключение, а не пустой список (Bbb::getRecordings прячет его,
     * и синхронизация удаляла бы у нас записи, которых «нет на сервере»).
     *
     * @param  array{meetingID?:string, state?:string}  $params
     * @return list<array>
     */
    public function recordings(BbbServer $server, array $params = []): array
    {
        $query = new GetRecordingsParameters();
        if (! empty($params['meetingID'])) {
            $query->setMeetingID($params['meetingID']);
        }
        if (! empty($params['state'])) {
            $query->setState($params['state']);
        }

        $response = $this->api($server)->getRecordings($query);

        if ($response->hasChecksumError()) {
            throw new BbbChecksumException($response->getMessage());
        }
        if (! $response->success()) {
            throw new \RuntimeException($response->getMessage() ?: 'Сервер ответил ошибкой');
        }

        $list = [];
        foreach ($response->getRawXml()->recordings->recording ?? [] as $recording) {
            $list[] = XmlToArray($recording);
        }

        return $list;
    }

    /** Версия BBB (запрос без подписи). */
    public function version(BbbServer $server): ?string
    {
        $response = $this->api($server)->getApiVersion();
        $xml = $response->getRawXml();

        // bbbVersion — полная версия (2.6+), version — версия API у старых
        $version = trim((string) ($xml->bbbVersion ?? '')) ?: trim((string) ($xml->version ?? ''));

        return $version !== '' ? $version : null;
    }

    private function api(BbbServer $server): BigBlueButton
    {
        // Недоступный сервер не должен подвешивать вход в класс и проверку остальных серверов
        $transport = CurlTransport::createWithDefaultOptions([
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
        ]);

        return new BigBlueButton($server->apiUrl(), trim((string) $server->secret), $transport, $server->checksum ?: 'sha1');
    }
}
