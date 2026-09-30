<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Записи, сохранённые после перехода приложения на Europe/Moscow (27.09.2026), получили время начала и конца
 * на 3 часа раньше: Carbon 3 создаёт время из метки BBB в UTC, а в базу оно ложилось без перевода.
 * Код исправлен (createFromTimestamp… с config('app.timezone')). Здесь — уже сохранённые записи.
 *
 * Номер записи BBB оканчивается меткой начала занятия в миллисекундах — по ней видно, что время сдвинуто,
 * и на сколько. Правим только записи со сдвигом ровно на −3 часа (±2 минуты): повторный запуск ничего не меняет.
 */
return new class extends Migration
{
    private const SHIFT = 3 * 3600;

    private const TOLERANCE = 120;

    public function up(): void
    {
        $tz = config('app.timezone');

        DB::table('recordings')
            ->whereNotNull('start_time')
            ->where('record_id', 'not like', '%placeholder%')
            ->orderBy('id')
            ->each(function (object $r) use ($tz) {
                if (! preg_match('/-(\d{13})$/', $r->record_id, $m)) {
                    return;
                }

                $saved = \Illuminate\Support\Carbon::parse($r->start_time, $tz);
                $real = intdiv((int) $m[1], 1000);

                if (abs($saved->getTimestamp() - $real + self::SHIFT) > self::TOLERANCE) {
                    return;
                }

                DB::table('recordings')->where('id', $r->id)->update([
                    'start_time' => $saved->addSeconds(self::SHIFT)->format('Y-m-d H:i:s'),
                    'end_time' => $r->end_time
                        ? \Illuminate\Support\Carbon::parse($r->end_time, $tz)->addSeconds(self::SHIFT)->format('Y-m-d H:i:s')
                        : null,
                ]);
            });
    }

    public function down(): void
    {
        // Исправление данных: откатывать нечего
    }
};
