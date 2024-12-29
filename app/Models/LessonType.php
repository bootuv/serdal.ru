<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonType extends Model
{
    const TYPE_GROUP = 'group';
    const TYPE_INDIVIDUAL = 'individual';

    use HasFactory;
}
