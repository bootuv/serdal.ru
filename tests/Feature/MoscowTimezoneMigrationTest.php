<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Переход на Europe/Moscow: системные отметки сдвигаются на +3 ч, время, введённое людьми, — нет. */
class MoscowTimezoneMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_runs_in_moscow_time(): void
    {
        $this->assertSame('Europe/Moscow', config('app.timezone'));
    }

    public function test_migration_shifts_system_timestamps_only(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 't' . uniqid()]);
        $homeworkId = DB::table('homeworks')->insertGetId([
            'teacher_id' => $teacher->id, 'title' => 'Эссе', 'type' => 'homework', 'is_visible' => true,
            'deadline' => '2026-09-11 20:00:00', 'created_at' => '2026-09-04 18:30:00', 'updated_at' => '2026-09-04 18:30:00',
        ]);

        $migration = require database_path('migrations/2026_09_26_120000_shift_system_timestamps_to_moscow.php');
        $migration->up();

        $row = DB::table('homeworks')->find($homeworkId);
        $this->assertSame('2026-09-11 20:00:00', $row->deadline, 'срок, введённый учителем, не меняется');
        $this->assertSame('2026-09-04 21:30:00', $row->created_at, 'системная отметка сдвигается на +3 часа');

        $migration->down();
        $this->assertSame('2026-09-04 18:30:00', DB::table('homeworks')->find($homeworkId)->created_at);
    }
}
