<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Письмо рассылки одному адресату: отправка, открытия, переходы, отписка. token — в ссылках письма. */
class MailingDelivery extends Model
{
    public const QUEUED = 'queued';
    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    protected $fillable = [
        'mailing_campaign_id', 'mailing_contact_id', 'email', 'token', 'status', 'error',
        'sent_at', 'opened_at', 'opens', 'clicked_at', 'clicks', 'unsubscribed_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MailingCampaign::class, 'mailing_campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(MailingContact::class, 'mailing_contact_id');
    }
}
