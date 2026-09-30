<?php

namespace Tests\Feature;

use App\Models\BbbServer;
use App\Models\Recording;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Миграция на несколько серверов: общий сервер из настроек и личные серверы учителей переезжают в таблицу. */
class BbbServersMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_09_30_120000_create_bbb_servers_table.php';

    public function test_existing_servers_and_lessons_move_to_servers_table(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();

        Setting::create(['key' => 'bbb_url', 'value' => 'https://room.serdal.ru/bigbluebutton/']);
        Setting::create(['key' => 'bbb_secret', 'value' => 'main-secret']);

        $regular = User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 't1']);
        $own = User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 't2']);
        $same = User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 't3']);
        DB::table('users')->where('id', $own->id)->update(['bbb_url' => 'https://own.example/bigbluebutton/', 'bbb_secret' => 'own-secret']);
        DB::table('users')->where('id', $same->id)->update(['bbb_url' => 'https://room.serdal.ru/bigbluebutton', 'bbb_secret' => 'main-secret']);

        $rooms = [];
        foreach ([$regular, $own, $same] as $teacher) {
            $rooms[$teacher->id] = DB::table('rooms')->insertGetId([
                'user_id' => $teacher->id, 'name' => 'Занятие', 'meeting_id' => 'm-' . $teacher->id,
                'moderator_pw' => 'mp', 'attendee_pw' => 'ap', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('recordings')->insert(['meeting_id' => 'm-' . $own->id, 'record_id' => 'r1', 'name' => 'x', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $main = BbbServer::whereNull('user_id')->sole();
        $this->assertSame('room.serdal.ru', $main->name);
        $this->assertSame('main-secret', $main->secret);

        $personal = BbbServer::where('user_id', $own->id)->sole();
        $this->assertSame('own-secret', $personal->secret);
        $this->assertSame(2, BbbServer::count(), 'Личный сервер, совпадающий с общим, не дублируется');

        $this->assertSame($main->id, Room::find($rooms[$regular->id])->bbb_server_id);
        $this->assertSame($main->id, Room::find($rooms[$same->id])->bbb_server_id);
        $this->assertSame($personal->id, Room::find($rooms[$own->id])->bbb_server_id);
        $this->assertSame($personal->id, Recording::where('record_id', 'r1')->value('bbb_server_id'));
    }
}
