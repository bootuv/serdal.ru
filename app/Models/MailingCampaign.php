<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Письмо рассылки: черновик → запланировано / отправляется → отправлено или остановлено. Логика — App\Services\MailingService. */
class MailingCampaign extends Model
{
    public const DRAFT = 'draft';
    public const SCHEDULED = 'scheduled';
    public const SENDING = 'sending';
    public const SENT = 'sent';
    public const STOPPED = 'stopped';

    protected $fillable = [
        'subject', 'preheader', 'body', 'button_text', 'button_url', 'status', 'scheduled_at', 'started_at', 'finished_at', 'error', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(MailingList::class, 'mailing_campaign_list');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MailingDelivery::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(MailingLink::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Ещё можно править текст и адресатов. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::SCHEDULED], true);
    }
}
