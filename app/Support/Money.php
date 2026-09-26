<?php

namespace App\Support;

/** Суммы в кабинетах: «1 500 ₽». */
class Money
{
    public static function format(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' ₽';
    }
}
