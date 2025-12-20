<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'last_name',
        'first_name',
        'middle_name',
        'email',
        'phone',
        'whatsup',
        'instagram',
        'telegram',
        'about',
        'subjects',
        'directs',
        'grade',
        'status',
    ];

    protected $casts = [
        'subjects' => 'array',
        'directs' => 'array',
        'grade' => 'array',
    ];

    /**
     * Получить полное имя
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->last_name} {$this->first_name} {$this->middle_name}");
    }
}
