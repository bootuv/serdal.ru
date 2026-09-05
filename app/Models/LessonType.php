<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LessonType extends Model
{
    const TYPE_GROUP = 'group';
    const TYPE_INDIVIDUAL = 'individual';

    const PAYMENT_PER_LESSON = 'per_lesson';
    const PAYMENT_MONTHLY = 'monthly';

    /** Сколько недель считаем в месяце при пересчёте помесячной цены в цену за урок */
    const WEEKS_PER_MONTH = 4;

    protected $fillable = [
        'type',
        'price',
        'payment_type',
        'payment_due_days',
        'payment_due_day',
        'count_per_week',
        'duration',
        'user_id',
    ];

    use HasFactory;

    /**
     * SQL-выражение «цена за урок»: для помесячной оплаты — цена за месяц,
     * делённая на число занятий в месяц. Должно совпадать с pricePerLesson().
     */
    public static function pricePerLessonSql(): string
    {
        $weeks = self::WEEKS_PER_MONTH;

        return "case when payment_type = 'monthly' then round(price * 1.0 / (count_per_week * {$weeks})) else price end";
    }

    /** Типы занятий, по которым можно посчитать цену за урок */
    public function scopePriced(Builder $query): Builder
    {
        return $query
            ->where('price', '>', 0)
            ->where(function (Builder $q) {
                $q->where('payment_type', self::PAYMENT_PER_LESSON)
                    ->orWhere(function (Builder $q) {
                        $q->where('payment_type', self::PAYMENT_MONTHLY)
                            ->where('count_per_week', '>', 0);
                    });
            });
    }

    public function isMonthly(): bool
    {
        return $this->payment_type === self::PAYMENT_MONTHLY;
    }

    /** Цена за один урок в рублях или null, если посчитать нельзя. Совпадает с pricePerLessonSql(). */
    public function pricePerLesson(): ?int
    {
        if (!$this->price || $this->price <= 0) {
            return null;
        }

        if ($this->isMonthly()) {
            if (!$this->count_per_week || $this->count_per_week <= 0) {
                return null;
            }

            return (int) round($this->price / ($this->count_per_week * self::WEEKS_PER_MONTH));
        }

        return (int) $this->price;
    }

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
