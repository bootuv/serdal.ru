<?php

namespace App\Models;

use App\Services\Bbb\BbbClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JoisarJignesh\Bigbluebutton\Bbb;

/**
 * Сервер видеосвязи (BigBlueButton). Общие серверы делят занятия по нагрузке (BbbServerPool),
 * личный сервер учителя (user_id) обслуживает только его занятия. Состояние — из проверки раз в минуту
 * (BbbServerMonitor): на связи ли, версия, алгоритм подписи, сколько занятий и участников.
 */
class BbbServer extends Model
{
    /** Алгоритмы подписи запросов в порядке проверки: sha256 понимают все современные версии, sha1 — старые. */
    public const CHECKSUMS = ['sha256', 'sha1', 'sha512', 'sha384'];

    protected $fillable = [
        'name', 'url', 'secret', 'checksum', 'version', 'capacity', 'is_enabled', 'user_id',
        'is_online', 'meetings', 'participants', 'meeting_loads', 'checked_at', 'error',
    ];

    protected $hidden = ['secret'];

    protected $casts = [
        'secret' => 'encrypted',
        'capacity' => 'integer',
        'is_enabled' => 'boolean',
        'is_online' => 'boolean',
        'meetings' => 'integer',
        'participants' => 'integer',
        'meeting_loads' => 'array',
        'checked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Общие серверы (не личные серверы учителей). */
    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /** Клиент API этого сервера. Каждый раз свой: адрес и ключ не берутся из общего конфига. */
    public function client(): Bbb
    {
        return app(BbbClientFactory::class)->make($this);
    }

    /** Адрес API с косой чертой в конце, как ждёт библиотека. */
    public function apiUrl(): string
    {
        return rtrim(trim($this->url), '/') . '/';
    }

    /** Адрес без протокола — для экрана. */
    public function host(): string
    {
        return parse_url($this->url, PHP_URL_HOST) ?: $this->url;
    }
}
