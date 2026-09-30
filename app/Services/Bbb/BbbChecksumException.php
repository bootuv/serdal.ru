<?php

namespace App\Services\Bbb;

/** Сервер видеосвязи не принял подпись запроса: неверный ключ или алгоритм подписи. */
class BbbChecksumException extends \RuntimeException
{
}
