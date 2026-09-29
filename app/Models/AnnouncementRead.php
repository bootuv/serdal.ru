<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Человек открыл новость. */
class AnnouncementRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['announcement_id', 'user_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
