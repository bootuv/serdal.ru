<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Список адресов для рассылки («Школы Казани»). Логика — App\Services\MailingService. */
class MailingList extends Model
{
    protected $fillable = ['name'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(MailingContact::class, 'mailing_contact_list')->withPivot('created_at');
    }
}
