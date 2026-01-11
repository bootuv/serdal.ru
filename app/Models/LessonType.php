<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonType extends Model
{
    const TYPE_GROUP = 'group';
    const TYPE_INDIVIDUAL = 'individual';

    protected $fillable = [
        'type',
        'price',
        'payment_type',
        'count_per_week',
        'duration',
        'user_id',
    ];

    use HasFactory;

    protected static function booted()
    {
        static::updated(function (LessonType $lessonType) {
            if ($lessonType->isDirty('price')) {
                $originalPrice = $lessonType->getOriginal('price');

                // Find rooms with the OLD price and set them to NULL (dynamic)
                // This upgrades legacy data to the new system where NULL = "use lesson type price"
                \App\Models\Room::where('user_id', $lessonType->user_id)
                    ->where('type', $lessonType->type)
                    ->where('base_price', $originalPrice)
                    ->update(['base_price' => null]);
            }
        });
    }
}
