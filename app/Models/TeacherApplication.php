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
        'telegram',
        'about',
        'subjects',
        'directs',
        'grade',
        'status',
        'desired_tariff_id',
    ];

    /**
     * Тариф, выбранный на публичной странице тарифов перед подачей заявки.
     */
    public function desiredTariff()
    {
        return $this->belongsTo(Tariff::class, 'desired_tariff_id')->withTrashed();
    }

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
