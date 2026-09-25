<?php

namespace Tests\Feature;

use App\Models\Recording;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Подпись ссылок S3 считается локально, сеть не нужна
        config([
            'filesystems.disks.s3.key' => 'test-key',
            'filesystems.disks.s3.secret' => 'test-secret',
            'filesystems.disks.s3.region' => 'ru-central1',
            'filesystems.disks.s3.bucket' => 'serdal',
            'filesystems.disks.s3.endpoint' => 'https://s3.example.com',
            'filesystems.disks.s3.url' => 'https://s3.example.com/serdal',
            'filesystems.disks.s3.root' => null,
        ]);
    }

    protected function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    protected function makeRecording(User $teacher, ?string $s3Url = 'https://s3.example.com/serdal/recordings/1/lesson.m4v'): Recording
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Математика',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'is_running' => false,
        ]);

        return Recording::create([
            'meeting_id' => $room->meeting_id,
            'record_id' => 'rec-' . uniqid(),
            'name' => 'Математика',
            'published' => true,
            'start_time' => now(),
            's3_url' => $s3Url,
        ]);
    }

    public function test_teacher_student_and_admin_can_download(): void
    {
        $teacher = $this->makeUser(User::ROLE_TUTOR);
        $student = $this->makeUser(User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $recording = $this->makeRecording($teacher);

        foreach ([$teacher, $student, $admin] as $user) {
            $location = $this->actingAs($user)
                ->get(route('recordings.download', $recording))
                ->assertRedirect()
                ->headers->get('Location');

            $this->assertStringContainsString('recordings/1/lesson.m4v', $location);
            $this->assertStringContainsString('response-content-disposition=attachment', $location);
            $this->assertStringContainsString('X-Amz-Signature', $location);
        }
    }

    public function test_strangers_cannot_download(): void
    {
        $teacher = $this->makeUser(User::ROLE_TUTOR);
        $recording = $this->makeRecording($teacher);

        $otherTeacher = $this->makeUser(User::ROLE_TUTOR);
        $otherStudent = $this->makeUser(User::ROLE_STUDENT);
        $otherTeacher->students()->attach($otherStudent->id);

        foreach ([$otherTeacher, $otherStudent] as $user) {
            $this->actingAs($user)->get(route('recordings.download', $recording))->assertForbidden();
        }
    }

    public function test_recording_not_in_storage_yet_returns_404(): void
    {
        $teacher = $this->makeUser(User::ROLE_TUTOR);
        $recording = $this->makeRecording($teacher, null);

        $this->actingAs($teacher)->get(route('recordings.download', $recording))->assertNotFound();
    }
}
