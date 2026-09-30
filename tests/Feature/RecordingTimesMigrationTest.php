<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Записи с временем, сохранённым в UTC вместо московского (−3 часа), исправляются по метке в номере записи. */
class RecordingTimesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_09_30_230000_fix_recording_times_shifted_to_utc.php';

    public function test_shifted_times_are_fixed_once_and_correct_ones_kept(): void
    {
        config(['app.timezone' => 'Europe/Moscow']);
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();

        // 1790797578166 мс = 30.09.2026 22:46:18 по Москве
        DB::table('recordings')->insert([
            ['record_id' => 'aaa-1790797578166', 'meeting_id' => 'm1', 'name' => 'x', 'start_time' => '2026-09-30 19:46:18', 'end_time' => '2026-09-30 19:47:11', 'created_at' => now(), 'updated_at' => now()],
            ['record_id' => 'bbb-1790797578166', 'meeting_id' => 'm2', 'name' => 'x', 'start_time' => '2026-09-30 22:46:18', 'end_time' => '2026-09-30 22:47:11', 'created_at' => now(), 'updated_at' => now()],
            ['record_id' => 'ccc-1790797578166-placeholder-1', 'meeting_id' => 'm3', 'name' => 'x', 'start_time' => '2026-09-30 19:46:18', 'end_time' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $row = fn ($id) => DB::table('recordings')->where('record_id', $id)->first();
        $this->assertSame('2026-09-30 22:46:18', (string) $row('aaa-1790797578166')->start_time);
        $this->assertSame('2026-09-30 22:47:11', (string) $row('aaa-1790797578166')->end_time);
        $this->assertSame('2026-09-30 22:46:18', (string) $row('bbb-1790797578166')->start_time, 'Верное время не трогаем');
        $this->assertSame('2026-09-30 19:46:18', (string) $row('ccc-1790797578166-placeholder-1')->start_time, 'Заглушки не трогаем');
    }
}
