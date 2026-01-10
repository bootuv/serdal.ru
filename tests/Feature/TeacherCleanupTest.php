<?php

namespace Tests\Feature;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherCleanupTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_cleans_up_teacher_resources_and_files_on_delete()
    {
        Storage::fake('public');
        Storage::fake('s3');

        // Create teacher
        $teacher = User::factory()->create([
            'role' => 'tutor',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Avatar
        $avatarPath = UploadedFile::fake()->image('avatar.jpg')->store('avatars', 'public');
        $teacher->update(['avatar' => $avatarPath]);

        // Create Room with presentation
        $presentationPath = UploadedFile::fake()->create('presentation.pdf', 100)->store('presentations', 's3');
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Physics 101',
            'meeting_id' => 'meet-123',
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'is_running' => false,
            'presentations' => [$presentationPath]
        ]);

        // Create Homework with attachment
        $homeworkAttachment = UploadedFile::fake()->create('homework.pdf', 100)->store('homeworks', 'public');
        $homework = Homework::create([
            'teacher_id' => $teacher->id,
            'room_id' => $room->id,
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'HW 1',
            'description' => 'Test',
            'attachments' => [$homeworkAttachment],
            'deadline' => now()->addDay(),
            'max_score' => 10,
            'is_visible' => true,
        ]);

        // Create Student and Submission
        $student = User::factory()->create([
            'role' => 'student',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
        $submissionAttachment = UploadedFile::fake()->create('submission.pdf', 100)->store('submissions', 'public');
        $submission = HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'attachments' => [$submissionAttachment],
            'status' => HomeworkSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        // Create Message with attachment
        $messageAttachment = 'messages/' . UploadedFile::fake()->create('image.jpg', 100)->hashName();
        Storage::disk('s3')->put($messageAttachment, 'content');

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $teacher->id,
            'content' => 'Hello',
            'attachments' => [['path' => $messageAttachment]]
        ]);

        // Verify existence before delete
        $this->assertDatabaseHas('users', ['id' => $teacher->id]);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
        $this->assertDatabaseHas('homeworks', ['id' => $homework->id]);
        $this->assertDatabaseHas('homework_submissions', ['id' => $submission->id]);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);

        Storage::disk('public')->assertExists($avatarPath);
        Storage::disk('s3')->assertExists($presentationPath);
        Storage::disk('public')->assertExists($homeworkAttachment);
        Storage::disk('public')->assertExists($submissionAttachment);
        Storage::disk('s3')->assertExists($messageAttachment);

        // Delete Teacher
        $teacher->delete();

        // Verify Deletion
        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
        $this->assertDatabaseMissing('homeworks', ['id' => $homework->id]);
        $this->assertDatabaseMissing('homework_submissions', ['id' => $submission->id]);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);

        // Rooms are force deleted by our logic in User model
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);

        // Verify File Deletion
        Storage::disk('public')->assertMissing($avatarPath);
        Storage::disk('s3')->assertMissing($presentationPath);
        Storage::disk('public')->assertMissing($homeworkAttachment);
        Storage::disk('public')->assertMissing($submissionAttachment);
        Storage::disk('s3')->assertMissing($messageAttachment);
    }
}
