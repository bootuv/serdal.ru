<?php

namespace Tests\Feature;

use App\Events\RoomStatusUpdated;
use App\Models\Room;
use App\Models\Tariff;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Tests\TestCase;

/** Презентации к занятию лежат на диске s3 (FileUploadHelper) — при старте их ищем там же, а не на диске по умолчанию. */
class RoomStartPresentationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        SubscriptionService::flushCanStartCache();
        Event::fake([RoomStatusUpdated::class]);
    }

    public function test_presentations_from_s3_reach_the_class_when_default_disk_is_local(): void
    {
        config(['filesystems.default' => 'local', 'app.url' => 'http://localhost']);
        Storage::fake('local');
        Storage::fake('s3');

        $tutor = User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
        SubscriptionService::activate($tutor, Tariff::where('slug', 'start')->first());

        Storage::disk('s3')->put('presentations/' . $tutor->id . '/slides.pdf', 'pdf');

        $room = Room::create([
            'user_id' => $tutor->id,
            'name' => 'Математика',
            'meeting_id' => 'test-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'presentations' => [
                'presentations/' . $tutor->id . '/slides.pdf',
                'presentations/' . $tutor->id . '/missing.pdf',
            ],
        ]);

        $created = null;
        Bigbluebutton::shouldReceive('isMeetingRunning')->andReturn(false);
        Bigbluebutton::shouldReceive('create')->once()->andReturnUsing(function (array $params) use (&$created) {
            $created = $params;

            return ['internalMeetingID' => 'internal-1'];
        });
        Bigbluebutton::shouldReceive('join')->andReturn('https://bbb.test/join');

        $this->actingAs($tutor)
            ->get(route('rooms.start', $room))
            ->assertRedirect('https://bbb.test/join');

        $this->assertNotNull($created);
        $files = array_column($created['presentation'] ?? [], 'fileName');
        $this->assertContains('slides.pdf', $files);
        $this->assertNotContains('missing.pdf', $files);

        $link = collect($created['presentation'])->firstWhere('fileName', 'slides.pdf')['link'];
        $this->assertSame(Storage::disk('s3')->url('presentations/' . $tutor->id . '/slides.pdf'), $link);
    }
}
