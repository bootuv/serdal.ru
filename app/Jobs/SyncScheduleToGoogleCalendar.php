<?php

namespace App\Jobs;

use App\Models\RoomSchedule;
use App\Models\User;
use Google\Client;
use Google\Service\Calendar;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncScheduleToGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $schedule;
    public $userId;

    public function __construct(RoomSchedule $schedule, $userId)
    {
        $this->schedule = $schedule;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        // Reload schedule with room relationship
        $schedule = RoomSchedule::with('room')->find($this->schedule->id);

        if (!$schedule || !$schedule->room) {
            Log::warning('Schedule or room not found', ['schedule_id' => $this->schedule->id]);
            return;
        }

        $user = User::find($this->userId);

        if (!$user || !$user->google_access_token) {
            Log::info('User not connected to Google Calendar', ['user_id' => $this->userId]);
            return;
        }

        try {
            $client = $this->getClient($user);

            // Refresh token if expired
            if ($client->isAccessTokenExpired()) {
                if ($user->google_refresh_token) {
                    $client->setAccessToken([
                        'access_token' => $user->google_access_token,
                        'refresh_token' => $user->google_refresh_token,
                    ]);

                    $newToken = $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);

                    $user->update([
                        'google_access_token' => $newToken['access_token'],
                        'google_token_expires_at' => now()->addSeconds($newToken['expires_in']),
                    ]);
                } else {
                    Log::warning('Refresh token not available', ['user_id' => $user->id]);
                    return;
                }
            } else {
                $client->setAccessToken($user->google_access_token);
            }

            $service = new Calendar($client);

            // Get or create Serdal calendar
            $calendarId = $this->getOrCreateSerdalCalendar($service, $user);

            $this->syncSchedule($service, $schedule, $calendarId);

            Log::info('Schedule synced to Google Calendar', [
                'schedule_id' => $schedule->id,
                'user_id' => $user->id,
                'calendar_id' => $calendarId,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync schedule to Google Calendar', [
                'schedule_id' => $this->schedule->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function getOrCreateSerdalCalendar(Calendar $service, $user)
    {
        // Check if user already has a Serdal calendar ID
        if ($user->google_calendar_id) {
            try {
                // Verify the calendar still exists
                $calendar = $service->calendars->get($user->google_calendar_id);
                Log::info('Using existing Serdal calendar', ['calendar_id' => $user->google_calendar_id, 'user_id' => $user->id]);
                return $user->google_calendar_id;
            } catch (\Exception $e) {
                Log::warning('Saved calendar not found, creating new one', ['old_id' => $user->google_calendar_id, 'user_id' => $user->id]);
            }
        }

        // Search for existing Serdal calendar
        try {
            $calendarList = $service->calendarList->listCalendarList();
            foreach ($calendarList->getItems() as $calendarListEntry) {
                if ($calendarListEntry->getSummary() === 'Serdal') {
                    $calendarId = $calendarListEntry->getId();
                    $user->update(['google_calendar_id' => $calendarId]);
                    Log::info('Found existing Serdal calendar', ['calendar_id' => $calendarId, 'user_id' => $user->id]);
                    return $calendarId;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error searching for calendar', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }

        // Create new Serdal calendar
        try {
            $calendar = new \Google\Service\Calendar\Calendar();
            $calendar->setSummary('Serdal');
            $calendar->setDescription('Расписание занятий на платформе Serdal');
            $calendar->setTimeZone(config('app.timezone'));

            $createdCalendar = $service->calendars->insert($calendar);
            $calendarId = $createdCalendar->getId();

            // Save calendar ID to user
            $user->update(['google_calendar_id' => $calendarId]);

            Log::info('Created new Serdal calendar', ['calendar_id' => $calendarId, 'user_id' => $user->id]);
            return $calendarId;

        } catch (\Exception $e) {
            Log::error('Error creating calendar', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            // Fallback to primary calendar
            return 'primary';
        }
    }

    private function getClient($user)
    {
        $client = new Client();
        $client->setApplicationName(config('app.name'));
        $client->setScopes([Calendar::CALENDAR]);
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    private function syncSchedule(Calendar $service, $schedule, $calendarId)
    {
        $room = $schedule->room;

        // Prepare event data
        $eventData = [
            'summary' => $room->name,
            'description' => $room->welcome ?? '',
            'location' => 'Online (BigBlueButton)',
        ];

        if ($schedule->type === 'once') {
            // One-time event
            $start = $schedule->scheduled_at;
            $end = $schedule->scheduled_at->copy()->addMinutes($schedule->duration_minutes);

            $eventData['start'] = ['dateTime' => $start->toRfc3339String()];
            $eventData['end'] = ['dateTime' => $end->toRfc3339String()];

        } else {
            // Recurring event
            $date = \Carbon\Carbon::parse($schedule->start_date)->format('Y-m-d');
            $startTime = \Carbon\Carbon::parse($date . ' ' . $schedule->recurrence_time);
            $endTime = $startTime->copy()->addMinutes($schedule->duration_minutes);

            $eventData['start'] = ['dateTime' => $startTime->toRfc3339String(), 'timeZone' => config('app.timezone')];
            $eventData['end'] = ['dateTime' => $endTime->toRfc3339String(), 'timeZone' => config('app.timezone')];

            // Add recurrence rule
            $rrule = $this->buildRecurrenceRule($schedule);
            if ($rrule) {
                $eventData['recurrence'] = [$rrule];
            }
        }

        $event = new \Google\Service\Calendar\Event($eventData);

        // Check if this schedule already has a Google event
        if ($schedule->google_event_id) {
            try {
                // Try to update existing event
                $service->events->update($calendarId, $schedule->google_event_id, $event);
                Log::info('Updated existing Google Calendar event', [
                    'schedule_id' => $schedule->id,
                    'event_id' => $schedule->google_event_id,
                ]);
            } catch (\Exception $e) {
                // If event doesn't exist anymore, create a new one
                Log::warning('Event not found, creating new one', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);

                $createdEvent = $service->events->insert($calendarId, $event);
                $schedule->update(['google_event_id' => $createdEvent->getId()]);
            }
        } else {
            // Create new event
            $createdEvent = $service->events->insert($calendarId, $event);
            $schedule->update(['google_event_id' => $createdEvent->getId()]);

            Log::info('Created new Google Calendar event', [
                'schedule_id' => $schedule->id,
                'event_id' => $createdEvent->getId(),
            ]);
        }
    }

    private function buildRecurrenceRule($schedule): ?string
    {
        Log::info('Building recurrence rule', [
            'schedule_id' => $schedule->id,
            'recurrence_type' => $schedule->recurrence_type,
            'recurrence_days' => $schedule->recurrence_days,
            'end_date' => $schedule->end_date,
        ]);

        if ($schedule->recurrence_type === 'none' || !$schedule->recurrence_type) {
            return null;
        }

        $rrule = 'RRULE:';

        switch ($schedule->recurrence_type) {
            case 'daily':
                $rrule .= 'FREQ=DAILY';
                break;
            case 'weekly':
                $rrule .= 'FREQ=WEEKLY';
                if ($schedule->recurrence_days && is_array($schedule->recurrence_days)) {
                    $days = array_map(function ($day) {
                        return strtoupper(substr($day, 0, 2));
                    }, $schedule->recurrence_days);
                    $rrule .= ';BYDAY=' . implode(',', $days);
                }
                break;
            case 'monthly':
                $rrule .= 'FREQ=MONTHLY';
                if ($schedule->recurrence_day_of_month) {
                    $rrule .= ';BYMONTHDAY=' . $schedule->recurrence_day_of_month;
                }
                break;
        }

        if ($schedule->end_date) {
            // UNTIL must be in UTC format: YYYYMMDDTHHmmssZ
            $endDateTime = \Carbon\Carbon::parse($schedule->end_date)
                ->endOfDay()
                ->setTimezone('UTC')
                ->format('Ymd\THis\Z');
            $rrule .= ';UNTIL=' . $endDateTime;
        }

        Log::info('Built recurrence rule', [
            'schedule_id' => $schedule->id,
            'rrule' => $rrule,
        ]);

        return $rrule;
    }
}
