<?php

namespace App\Console\Commands;

use App\Models\Recording;
use App\Models\Room;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportGreenlightData extends Command
{
    protected $signature = 'import:greenlight 
                            {--dry-run : Preview changes without modifying data}
                            {--skip-passwords : Skip password import}
                            {--skip-rooms : Skip room import}
                            {--skip-recordings : Skip recording import}';

    protected $description = 'Import tutors data (passwords, rooms, recordings) from Greenlight v3';

    private bool $dryRun = false;
    private int $passwordsUpdated = 0;
    private int $roomsCreated = 0;
    private int $recordingsCreated = 0;
    private int $errors = 0;

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');

        if ($this->dryRun) {
            $this->components->warn('Running in DRY-RUN mode. No changes will be made.');
        }

        // Test connection
        try {
            DB::connection('greenlight')->getPdo();
            $this->components->info('Connected to Greenlight database.');
        } catch (\Exception $e) {
            $this->components->error('Failed to connect to Greenlight database: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Get tutor emails from LMS
        $tutorEmails = User::where('role', User::ROLE_TUTOR)
            ->pluck('email', 'id')
            ->toArray();

        $this->components->info('Found ' . count($tutorEmails) . ' tutors in LMS.');

        if (!$this->option('skip-passwords')) {
            $this->importPasswords($tutorEmails);
        }

        if (!$this->option('skip-rooms')) {
            $this->importRooms($tutorEmails);
        }

        if (!$this->option('skip-recordings')) {
            $this->importRecordings();
        }

        $this->displaySummary();

        return $this->errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function importPasswords(array $tutorEmails): void
    {
        $this->components->info('Importing passwords...');

        $glUsers = DB::connection('greenlight')
            ->table('users')
            ->whereIn('email', array_values($tutorEmails))
            ->whereNotNull('password_digest')
            ->get(['email', 'password_digest']);

        $bar = $this->output->createProgressBar($glUsers->count());
        $bar->start();

        foreach ($glUsers as $glUser) {
            try {
                $lmsUser = User::where('email', $glUser->email)->first();

                if (!$lmsUser) {
                    $this->warn("User not found in LMS: {$glUser->email}");
                    continue;
                }

                if ($this->dryRun) {
                    $this->components->task("[DRY] Update password for {$glUser->email}", fn() => true);
                } else {
                    // Directly set password_digest (bcrypt compatible)
                    $lmsUser->timestamps = false;
                    $lmsUser->update(['password' => $glUser->password_digest]);
                }

                $this->passwordsUpdated++;
            } catch (\Exception $e) {
                $this->error("Error updating password for {$glUser->email}: " . $e->getMessage());
                $this->errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function importRooms(array $tutorEmails): void
    {
        $this->components->info('Importing rooms...');

        // Map GL user IDs to emails
        $glUserIds = DB::connection('greenlight')
            ->table('users')
            ->whereIn('email', array_values($tutorEmails))
            ->pluck('email', 'id')
            ->toArray();

        // Get rooms from Greenlight
        $glRooms = DB::connection('greenlight')
            ->table('rooms')
            ->whereIn('user_id', array_keys($glUserIds))
            ->get();

        $bar = $this->output->createProgressBar($glRooms->count());
        $bar->start();

        foreach ($glRooms as $glRoom) {
            try {
                $userEmail = $glUserIds[$glRoom->user_id] ?? null;

                if (!$userEmail) {
                    continue;
                }

                $lmsUser = User::where('email', $userEmail)->first();

                if (!$lmsUser) {
                    continue;
                }

                // Skip if already imported
                $existingRoom = Room::where('greenlight_id', $glRoom->id)
                    ->orWhere('meeting_id', $glRoom->meeting_id)
                    ->first();

                if ($existingRoom) {
                    $bar->advance();
                    continue;
                }

                if ($this->dryRun) {
                    $this->components->task("[DRY] Create room '{$glRoom->name}' for {$userEmail}", fn() => true);
                } else {
                    Room::create([
                        'greenlight_id' => $glRoom->id,
                        'user_id' => $lmsUser->id,
                        'name' => $glRoom->name,
                        'meeting_id' => $glRoom->meeting_id,
                        'moderator_pw' => Str::random(12),
                        'attendee_pw' => Str::random(12),
                        'type' => 'individual',
                    ]);
                }

                $this->roomsCreated++;
            } catch (\Exception $e) {
                $this->error("Error creating room '{$glRoom->name}': " . $e->getMessage());
                $this->errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function importRecordings(): void
    {
        $this->components->info('Importing recordings...');

        // Get all meeting_ids from imported rooms
        $roomMeetingIds = Room::whereNotNull('greenlight_id')
            ->pluck('meeting_id')
            ->toArray();

        if (empty($roomMeetingIds)) {
            $this->warn('No imported rooms found. Skipping recordings.');
            return;
        }

        // Get room IDs from Greenlight by meeting_id
        $glRoomIds = DB::connection('greenlight')
            ->table('rooms')
            ->whereIn('meeting_id', $roomMeetingIds)
            ->pluck('meeting_id', 'id')
            ->toArray();

        // Get recordings from Greenlight
        $glRecordings = DB::connection('greenlight')
            ->table('recordings')
            ->whereIn('room_id', array_keys($glRoomIds))
            ->get();

        $bar = $this->output->createProgressBar($glRecordings->count());
        $bar->start();

        foreach ($glRecordings as $glRecording) {
            try {
                $meetingId = $glRoomIds[$glRecording->room_id] ?? null;

                if (!$meetingId) {
                    continue;
                }

                // Skip if already exists
                if (Recording::where('record_id', $glRecording->record_id)->exists()) {
                    $bar->advance();
                    continue;
                }

                // Get format URL
                $format = DB::connection('greenlight')
                    ->table('formats')
                    ->where('recording_id', $glRecording->id)
                    ->where('recording_type', 'presentation')
                    ->first();

                $url = $format->url ?? null;

                if ($this->dryRun) {
                    $this->components->task("[DRY] Create recording '{$glRecording->name}'", fn() => true);
                } else {
                    Recording::create([
                        'meeting_id' => $meetingId,
                        'record_id' => $glRecording->record_id,
                        'name' => $glRecording->name,
                        'published' => $glRecording->visibility === 'Published',
                        'start_time' => $glRecording->recorded_at,
                        'participants' => $glRecording->participants ?? 0,
                        'url' => $url,
                    ]);
                }

                $this->recordingsCreated++;
            } catch (\Exception $e) {
                $this->error("Error creating recording: " . $e->getMessage());
                $this->errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->components->info('=== Import Summary ===');

        $prefix = $this->dryRun ? '[DRY RUN] Would ' : '';

        $this->table(
            ['Data Type', 'Count'],
            [
                ['Passwords ' . ($this->dryRun ? 'to update' : 'updated'), $this->passwordsUpdated],
                ['Rooms ' . ($this->dryRun ? 'to create' : 'created'), $this->roomsCreated],
                ['Recordings ' . ($this->dryRun ? 'to create' : 'created'), $this->recordingsCreated],
                ['Errors', $this->errors],
            ]
        );

        if ($this->dryRun) {
            $this->newLine();
            $this->components->warn('This was a dry run. Run without --dry-run to apply changes.');
        }
    }
}
