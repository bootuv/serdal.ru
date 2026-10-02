<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ссылка из письма рассылки: переходы считаются через /m/c/{token}/{link}. */
class MailingLink extends Model
{
    protected $fillable = ['mailing_campaign_id', 'url', 'clicks'];
}
