<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Файл из редактора: адрес в тексте, пути на хранилище (у видео — ролик и обложка), чей текст его использует. */
class EditorMedia extends Model
{
    protected $table = 'editor_media';

    protected $fillable = ['url', 'paths', 'owner_type', 'owner_id', 'user_id'];

    protected $casts = ['paths' => 'array'];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
