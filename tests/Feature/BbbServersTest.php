<?php

namespace Tests\Feature;

use App\Events\RoomStatusUpdated;
use App\Livewire\Cabinet\Admin\VideoServers;
use App\Models\BbbServer;
use App\Models\MeetingSession;
use App\Models\Recording;
use App\Models\Room;
use App\Models\Tariff;
use App\Models\User;
use App\Services\Bbb\BbbChecksumException;
use App\Services\Bbb\BbbClientFactory;
use App\Services\Bbb\BbbServerMonitor;
use App\Services\Bbb\BbbServerPool;
use App\Services\RecordingSyncService;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use JoisarJignesh\Bigbluebutton\Bbb;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Livewire\Livewire;
use Tests\Support\FacadeBbbClientFactory;
use Tests\TestCase;

/**
 * Несколько серверов видеосвязи: новое занятие — на наименее загруженный сервер, идущее занятие
 * и его записи — на своём сервере; проверка серверов (связь, алгоритм подписи, сверка занятий); экран в админке.
 */
class BbbServersTest extends TestCase
{
    use RefreshDatabase;

    /** Фабрика, которая запоминает, к какому серверу обращались, и отдаёт заданные ответы. */
    private FakeServers $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        SubscriptionService::flushCanStartCache();
        Event::fake([RoomStatusUpdated::class]);

        $this->api = new FakeServers();
        $this->app->instance(BbbClientFactory::class, $this->api);
    }

    private function server(string $name, array $attrs = []): BbbServer
    {
        return BbbServer::create(array_merge([
            'name' => $name,
            'url' => "https://{$name}.test/bigbluebutton/",
            'secret' => 'secret-' . $name,
            'capacity' => 100,
            'checked_at' => now()->subMinute(),
        ], $attrs));
    }

    private function teacher(): User
    {
        $teacher = User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_profile_completed' => true,
        ]);
        SubscriptionService::activate($teacher, Tariff::where('slug', 'start')->first());

        return $teacher;
    }

    private function room(User $teacher, array $attrs = []): Room
    {
        return Room::create(array_merge([
            'user_id' => $teacher->id,
            'name' => 'Химия',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ], $attrs));
    }

    public function test_new_lesson_goes_to_least_loaded_server_by_share_of_capacity(): void
    {
        $this->server('a', ['meeting_loads' => ['other-40' => 40], 'capacity' => 100]);
        $b = $this->server('b', ['meeting_loads' => ['other-50' => 50], 'capacity' => 200]);
        $this->server('off', ['is_online' => false]);
        $this->server('disabled', ['is_enabled' => false]);

        $this->assertSame($b->id, app(BbbServerPool::class)->pick($this->room($this->teacher()))->id);
    }

    public function test_lessons_started_after_last_check_count_as_load(): void
    {
        $a = $this->server('a');
        $b = $this->server('b');
        $teacher = $this->teacher();

        // На «a» только что начали занятие — проверка его ещё не видела
        $running = $this->room($teacher, ['is_running' => true, 'bbb_server_id' => $a->id]);
        MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $running->id, 'meeting_id' => $running->meeting_id,
            'bbb_server_id' => $a->id, 'started_at' => now(), 'status' => 'running']);

        $this->assertSame($b->id, app(BbbServerPool::class)->pick($this->room($teacher))->id);
    }

    public function test_just_started_group_lesson_counts_by_expected_students_not_by_who_joined(): void
    {
        $a = $this->server('a');
        $b = $this->server('b', ['meeting_loads' => ['someone-else' => 10]]);
        $teacher = $this->teacher();

        // На «a» минуту назад началось групповое занятие на 15 учеников — пока подключился только учитель
        $group = $this->room($teacher, ['is_running' => true, 'bbb_server_id' => $a->id]);
        foreach (range(1, 15) as $i) {
            $group->participants()->attach(User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 'st' . $i . uniqid()])->id);
        }
        MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $group->id, 'meeting_id' => $group->meeting_id,
            'bbb_server_id' => $a->id, 'started_at' => now()->subMinutes(2), 'status' => 'running']);
        $a->update(['meeting_loads' => [$group->meeting_id => 1], 'checked_at' => now()]);

        $pool = app(BbbServerPool::class);
        $this->assertSame(['meetings' => 1, 'participants' => 16], $pool->loads(collect([$a->fresh()]))[$a->id]);
        $this->assertSame($b->id, $pool->pick($this->room($teacher))->id);
    }

    public function test_lesson_bigger_than_expected_counts_by_who_is_there(): void
    {
        $a = $this->server('a');
        $teacher = $this->teacher();
        $room = $this->room($teacher, ['is_running' => true, 'bbb_server_id' => $a->id]);
        MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id,
            'bbb_server_id' => $a->id, 'started_at' => now()->subHour(), 'status' => 'running']);
        // Позвали гостей по ссылке: в занятии 6 человек вместо ожидаемых двух
        $a->update(['meeting_loads' => [$room->meeting_id => 6, 'external' => 3]]);

        $this->assertSame(['meetings' => 2, 'participants' => 9], app(BbbServerPool::class)->loads(collect([$a->fresh()]))[$a->id]);
    }

    public function test_offline_servers_are_used_only_when_nothing_else_is_left(): void
    {
        $off = $this->server('off', ['is_online' => false]);

        $this->assertSame($off->id, app(BbbServerPool::class)->pick($this->room($this->teacher()))->id);
    }

    public function test_personal_server_serves_only_its_teacher(): void
    {
        $shared = $this->server('shared', ['meeting_loads' => ['other-90' => 90]]);
        $teacher = $this->teacher();
        $personal = $this->server('own', ['user_id' => $teacher->id]);

        $pool = app(BbbServerPool::class);
        $this->assertSame($personal->id, $pool->pick($this->room($teacher))->id);
        $this->assertSame($shared->id, $pool->pick($this->room($this->teacher()))->id);
    }

    public function test_start_creates_lesson_on_picked_server_and_remembers_it(): void
    {
        $this->server('busy', ['meeting_loads' => ['other-80' => 80]]);
        $free = $this->server('free');
        $teacher = $this->teacher();
        $room = $this->room($teacher);

        Bigbluebutton::shouldReceive('create')->once()->andReturn(['internalMeetingID' => 'i-1']);
        Bigbluebutton::shouldReceive('join')->once()->andReturn('https://free.test/join');

        $this->actingAs($teacher)->get(route('rooms.start', $room))->assertRedirect('https://free.test/join');

        $this->assertSame($free->id, $room->fresh()->bbb_server_id);
        $this->assertSame($free->id, MeetingSession::where('room_id', $room->id)->value('bbb_server_id'));
        $this->assertSame(['free', 'free'], $this->api->used);
    }

    public function test_start_moves_to_next_server_when_picked_one_is_down(): void
    {
        $down = $this->server('down');
        $next = $this->server('next', ['meeting_loads' => ['other-30' => 30]]);
        $teacher = $this->teacher();
        $room = $this->room($teacher);

        Bigbluebutton::shouldReceive('create')->twice()->andReturnUsing(function () {
            static $calls = 0;
            if ($calls++ === 0) {
                throw new \RuntimeException('Connection timed out');
            }

            return ['internalMeetingID' => 'i-2'];
        });
        Bigbluebutton::shouldReceive('join')->once()->andReturn('https://next.test/join');

        $this->actingAs($teacher)->get(route('rooms.start', $room))->assertRedirect('https://next.test/join');

        $this->assertFalse($down->fresh()->is_online);
        $this->assertSame($next->id, $room->fresh()->bbb_server_id);
        $this->assertSame(1, MeetingSession::where('room_id', $room->id)->count());
        $this->assertSame($next->id, MeetingSession::where('room_id', $room->id)->value('bbb_server_id'));
    }

    public function test_start_fails_cleanly_when_every_server_is_down(): void
    {
        $this->server('down');
        $teacher = $this->teacher();
        $room = $this->room($teacher);

        Bigbluebutton::shouldReceive('create')->once()->andThrow(new \RuntimeException('Connection timed out'));

        $this->actingAs($teacher)->from(route('cabinet.teacher.today'))->get(route('rooms.start', $room))
            ->assertRedirect(route('cabinet.teacher.today'))
            ->assertSessionHas('error', 'Не удалось начать занятие. Попробуйте через минуту.');

        $this->assertFalse($room->fresh()->is_running);
        $this->assertSame(0, MeetingSession::where('room_id', $room->id)->count(), 'Неначатое занятие не остаётся «идущим»');
    }

    public function test_student_joins_on_the_server_of_the_running_lesson(): void
    {
        $this->server('free');
        $busy = $this->server('busy', ['meeting_loads' => ['other-80' => 80]]);
        $room = $this->room($this->teacher(), ['is_running' => true, 'bbb_server_id' => $busy->id]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 's' . uniqid(), 'is_active' => true]);

        Bigbluebutton::shouldReceive('isMeetingRunning')->andReturn(true);
        Bigbluebutton::shouldReceive('getMeetingInfo')->andReturn(collect());
        Bigbluebutton::shouldReceive('join')->once()->andReturn('https://busy.test/join');

        $this->actingAs($student)->get(route('rooms.connect', $room))->assertRedirect('https://busy.test/join');

        $this->assertSame(['busy'], array_values(array_unique($this->api->used)));
    }

    public function test_check_picks_checksum_the_server_accepts_and_counts_load(): void
    {
        $server = $this->server('new', ['checksum' => 'sha1']);
        $this->api->rejectChecksums['new'] = ['sha1'];
        $this->api->meetings['new'] = [['id' => 'm1', 'participants' => 7], ['id' => 'm2', 'participants' => 3]];
        $this->api->versions['new'] = '3.0.4';

        app(BbbServerMonitor::class)->check($server);

        $server->refresh();
        $this->assertTrue($server->is_online);
        $this->assertSame('sha256', $server->checksum);
        $this->assertSame('3.0.4', $server->version);
        $this->assertSame(2, $server->meetings);
        $this->assertSame(10, $server->participants);
        $this->assertSame(['m1' => 7, 'm2' => 3], $server->meeting_loads);
    }

    public function test_wrong_secret_marks_server_offline(): void
    {
        $server = $this->server('bad');
        $this->api->rejectChecksums['bad'] = BbbServer::CHECKSUMS;

        app(BbbServerMonitor::class)->check($server);

        $this->assertFalse($server->fresh()->is_online);
        $this->assertSame('Сервер не принял секретный ключ', $server->fresh()->error);
    }

    public function test_check_closes_lessons_the_server_no_longer_runs(): void
    {
        $server = $this->server('a');
        $teacher = $this->teacher();
        $ended = $this->room($teacher, ['is_running' => true, 'bbb_server_id' => $server->id]);
        $endedSession = MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $ended->id, 'meeting_id' => $ended->meeting_id,
            'bbb_server_id' => $server->id, 'started_at' => now()->subHour(), 'status' => 'running']);
        $fresh = $this->room($teacher, ['is_running' => true, 'bbb_server_id' => $server->id]);
        MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $fresh->id, 'meeting_id' => $fresh->meeting_id,
            'bbb_server_id' => $server->id, 'started_at' => now(), 'status' => 'running']);
        $alive = $this->room($teacher, ['is_running' => false, 'bbb_server_id' => $server->id]);
        $this->api->meetings['a'] = [['id' => $alive->meeting_id, 'participants' => 2]];

        app(BbbServerMonitor::class)->check($server);

        $this->assertFalse($ended->fresh()->is_running);
        $this->assertSame('completed', $endedSession->fresh()->status);
        $this->assertTrue($fresh->fresh()->is_running, 'Только что начатое занятие сервер мог ещё не показать');
        $this->assertTrue($alive->fresh()->is_running);
    }

    public function test_unreachable_server_does_not_close_its_lessons(): void
    {
        $server = $this->server('down');
        $room = $this->room($this->teacher(), ['is_running' => true, 'bbb_server_id' => $server->id]);
        $this->api->down[] = 'down';

        app(BbbServerMonitor::class)->check($server);

        $this->assertFalse($server->fresh()->is_online);
        $this->assertTrue($room->fresh()->is_running);
    }

    public function test_recordings_sync_keeps_recordings_of_unreachable_server(): void
    {
        $a = $this->server('a');
        $b = $this->server('b');
        $teacher = $this->teacher();
        $room = $this->room($teacher);
        Recording::create(['meeting_id' => $room->meeting_id, 'record_id' => 'on-b', 'name' => 'x', 'bbb_server_id' => $b->id, 'start_time' => now()->subDay()]);
        $this->api->recordings['a'] = [[
            'meetingID' => $room->meeting_id, 'recordID' => 'on-a', 'name' => 'Химия', 'published' => 'true',
            'state' => 'published', 'startTime' => now()->subDay()->getTimestampMs(), 'playback' => ['format' => ['url' => 'https://a.test/p']],
        ]];
        $this->api->down[] = 'b';

        $this->assertSame(1, app(RecordingSyncService::class)->syncAll());

        $this->assertSame($a->id, Recording::where('record_id', 'on-a')->value('bbb_server_id'));
        $this->assertTrue(Recording::where('record_id', 'on-b')->exists());
    }

    public function test_recording_is_deleted_on_its_own_server(): void
    {
        $this->server('a');
        $b = $this->server('b');
        $room = $this->room($this->teacher());
        $recording = Recording::create(['meeting_id' => $room->meeting_id, 'record_id' => 'r1', 'name' => 'x', 'bbb_server_id' => $b->id]);

        Bigbluebutton::shouldReceive('deleteRecordings')->once()->andReturn(collect());
        app(RecordingSyncService::class)->delete($recording);

        $this->assertSame(['b'], $this->api->used);
    }

    public function test_webhook_recording_remembers_the_server_the_lesson_ran_on(): void
    {
        $old = $this->server('old');
        $new = $this->server('new');
        $teacher = $this->teacher();
        // Занятие шло на «old», с тех пор комната переехала на «new»
        $room = $this->room($teacher, ['bbb_server_id' => $new->id]);
        MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id,
            'internal_meeting_id' => 'int-1', 'bbb_server_id' => $old->id, 'started_at' => now()->subHours(2), 'status' => 'completed']);

        Event::fake([\App\Events\RecordingUpdated::class]);
        $this->postJson(route('api.bbb.webhook'), ['event' => [[
            'type' => 'publish_ended',
            'payload' => ['record_id' => 'int-1', 'external_meeting_id' => $room->meeting_id, 'playback' => ['link' => 'https://old.test/playback/presentation/2.3/int-1']],
        ]]])->assertOk();

        $this->assertSame($old->id, Recording::where('record_id', 'int-1')->value('bbb_server_id'));
    }

    public function test_admin_adds_server_and_it_is_checked_at_once(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'a' . uniqid()]);
        $this->api->versions['room2.serdal.ru'] = '3.0.4';

        Livewire::actingAs($admin)->test(VideoServers::class)
            ->call('edit')
            ->call('save')
            ->assertHasErrors(['name', 'url', 'secret'])
            ->set('name', 'Второй')
            ->set('url', 'https://room2.serdal.ru/bigbluebutton/')
            ->set('secret', 'top-secret')
            ->set('capacity', '250')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSee('Второй')
            ->assertSee('версия 3.0.4');

        $server = BbbServer::where('name', 'Второй')->first();
        $this->assertSame('top-secret', $server->secret);
        $this->assertNotSame('top-secret', \DB::table('bbb_servers')->where('id', $server->id)->value('secret'), 'Ключ хранится зашифрованным');
        $this->assertSame(250, $server->capacity);
        $this->assertTrue($server->is_online);

        // Пустой ключ при правке — ключ не меняется
        Livewire::actingAs($admin)->test(VideoServers::class)
            ->call('edit', $server->id)
            ->set('capacity', '300')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('top-secret', $server->fresh()->secret);
    }

    public function test_server_with_running_lessons_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'a' . uniqid()]);
        $server = $this->server('a');
        $room = $this->room($this->teacher(), ['is_running' => true, 'bbb_server_id' => $server->id]);

        Livewire::actingAs($admin)->test(VideoServers::class)
            ->call('askDelete', $server->id)
            ->assertSee('удалить его можно, когда оно закончится')
            ->call('delete');
        $this->assertModelExists($server);

        $room->update(['is_running' => false]);
        Livewire::actingAs($admin)->test(VideoServers::class)
            ->call('askDelete', $server->id)
            ->call('delete')
            ->assertDispatched('toast');
        $this->assertModelMissing($server);
    }

    public function test_only_admin_sees_servers(): void
    {
        Livewire::actingAs($this->teacher())->test(VideoServers::class)->assertForbidden();
    }
}

/**
 * Серверы в тестах: ответы по имени сервера, без сети. Клиент (join, create…) — фасад Bigbluebutton,
 * который подменяют тесты; used — к каким серверам обращались через клиент.
 */
class FakeServers extends FacadeBbbClientFactory
{
    public array $used = [];

    public array $meetings = [];

    public array $recordings = [];

    public array $versions = [];

    /** Сервер => алгоритмы подписи, которые он не принимает. */
    public array $rejectChecksums = [];

    /** Серверы, которые не отвечают. */
    public array $down = [];

    public function make(BbbServer $server): Bbb
    {
        $this->used[] = $server->name;

        return parent::make($server);
    }

    public function meetings(BbbServer $server): array
    {
        $this->reachable($server);

        return $this->meetings[$server->name] ?? [];
    }

    public function recordings(BbbServer $server, array $params = []): array
    {
        $this->reachable($server);

        return $this->recordings[$server->name] ?? [];
    }

    public function version(BbbServer $server): ?string
    {
        return $this->versions[$server->host()] ?? $this->versions[$server->name] ?? null;
    }

    private function reachable(BbbServer $server): void
    {
        if (in_array($server->name, $this->down, true)) {
            throw new \RuntimeException('Connection timed out');
        }
        if (in_array($server->checksum, $this->rejectChecksums[$server->name] ?? [], true)) {
            throw new BbbChecksumException('checksumError');
        }
    }
}
