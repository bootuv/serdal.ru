<?php

namespace App\Http\Controllers;

use Google\Client;
use Google\Service\Calendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class GoogleCalendarController extends Controller
{
    private function getClient()
    {
        $client = new Client();
        $client->setApplicationName(config('app.name'));
        $client->setScopes([Calendar::CALENDAR]);


        // Set OAuth credentials from config
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(route('google.calendar.callback'));

        $client->setAccessType('offline');
        $client->setPrompt('consent');

        // Set redirect URI
        $client->setRedirectUri(route('google.calendar.callback'));

        return $client;
    }

    public function redirectToGoogle()
    {
        $client = $this->getClient();
        $authUrl = $client->createAuthUrl();

        return redirect($authUrl);
    }

    public function handleGoogleCallback(Request $request)
    {
        \Log::info('Google Calendar Callback received', ['has_code' => $request->has('code'), 'has_error' => $request->has('error')]);

        if ($request->has('error')) {
            \Log::warning('Google Calendar authorization cancelled', ['error' => $request->error]);

            Notification::make()
                ->title('Авторизация отменена')
                ->body('Вы отменили авторизацию Google Calendar.')
                ->warning()
                ->send();

            return redirect()->route('filament.app.pages.schedule-calendar');
        }

        $client = $this->getClient();

        try {
            // Exchange authorization code for access token
            \Log::info('Fetching access token with auth code');
            $token = $client->fetchAccessTokenWithAuthCode($request->code);

            \Log::info('Token response received', ['has_access_token' => isset($token['access_token']), 'has_error' => isset($token['error'])]);

            if (isset($token['error'])) {
                throw new \Exception($token['error_description'] ?? 'Unknown error');
            }

            // Save tokens to user
            $user = Auth::user();
            \Log::info('Saving tokens for user', ['user_id' => $user->id]);

            $user->update([
                'google_access_token' => $token['access_token'],
                'google_refresh_token' => $token['refresh_token'] ?? null,
                'google_token_expires_at' => now()->addSeconds($token['expires_in']),
            ]);

            \Log::info('Tokens saved successfully', ['user_id' => $user->id]);

            Notification::make()
                ->title('Google Calendar подключен!')
                ->body('Теперь вы можете синхронизировать ваше расписание с Google Calendar.')
                ->success()
                ->send();

            return redirect()->route('filament.app.pages.schedule-calendar');

        } catch (\Exception $e) {
            \Log::error('Google Calendar OAuth Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->title('Ошибка подключения')
                ->body('Не удалось подключить Google Calendar: ' . $e->getMessage())
                ->danger()
                ->send();

            return redirect()->route('filament.app.pages.schedule-calendar');
        }
    }

    public function disconnect()
    {
        $user = Auth::user();
        $user->update([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'google_calendar_id' => null,
        ]);

        Notification::make()
            ->title('Google Calendar отключен')
            ->body('Синхронизация с Google Calendar отключена.')
            ->warning()
            ->send();

        return redirect()->back();
    }

    private function getOrCreateSerdalCalendar(Calendar $service, $user)
    {
        // Check if user already has a Serdal calendar ID
        if ($user->google_calendar_id) {
            try {
                // Verify the calendar still exists
                $calendar = $service->calendars->get($user->google_calendar_id);
                \Log::info('Using existing Serdal calendar', ['calendar_id' => $user->google_calendar_id]);
                return $user->google_calendar_id;
            } catch (\Exception $e) {
                \Log::warning('Saved calendar not found, creating new one', ['old_id' => $user->google_calendar_id]);
            }
        }

        // Search for existing Serdal calendar
        try {
            $calendarList = $service->calendarList->listCalendarList();
            foreach ($calendarList->getItems() as $calendarListEntry) {
                if ($calendarListEntry->getSummary() === 'Serdal') {
                    $calendarId = $calendarListEntry->getId();
                    $user->update(['google_calendar_id' => $calendarId]);
                    \Log::info('Found existing Serdal calendar', ['calendar_id' => $calendarId]);
                    return $calendarId;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error searching for calendar', ['error' => $e->getMessage()]);
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

            \Log::info('Created new Serdal calendar', ['calendar_id' => $calendarId]);
            return $calendarId;

        } catch (\Exception $e) {
            \Log::error('Error creating calendar', ['error' => $e->getMessage()]);
            // Fallback to primary calendar
            return 'primary';
        }
    }

    public function syncSchedule()
    {
        $user = Auth::user();

        if (!$user->google_access_token) {
            Notification::make()
                ->title('Google Calendar не подключен')
                ->body('Сначала подключите Google Calendar.')
                ->warning()
                ->send();

            return redirect()->back();
        }

        try {
            $client = $this->getClient();

            // Check if token is expired and refresh if needed
            if ($user->google_token_expires_at && now()->gte($user->google_token_expires_at)) {
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
                    throw new \Exception('Refresh token not available. Please reconnect.');
                }
            } else {
                $client->setAccessToken($user->google_access_token);
            }

            $service = new Calendar($client);

            // Get user's schedules based on role
            if ($user->role === 'student') {
                // For students: get schedules for rooms they are assigned to
                $schedules = \App\Models\RoomSchedule::whereHas('room', function ($query) use ($user) {
                    $query->whereHas('participants', function ($q) use ($user) {
                        $q->where('users.id', $user->id);
                    });
                })->where('is_active', true)->get();
            } else {
                // For teachers: get schedules for their own rooms
                $schedules = \App\Models\RoomSchedule::whereHas('room', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->where('is_active', true)->get();
            }

            // Get or create Serdal calendar
            $calendarId = $this->getOrCreateSerdalCalendar($service, $user);

            $syncedCount = 0;

            foreach ($schedules as $schedule) {
                // Create or update Google Calendar event
                $this->syncScheduleToGoogle($service, $schedule, $calendarId);
                $syncedCount++;
            }

            Notification::make()
                ->title('Синхронизация завершена!')
                ->body("Синхронизировано занятий: {$syncedCount}")
                ->success()
                ->send();

            return redirect()->back();

        } catch (\Exception $e) {
            \Log::error('Google Calendar Sync Error: ' . $e->getMessage());

            Notification::make()
                ->title('Ошибка синхронизации')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return redirect()->back();
        }
    }

    private function syncScheduleToGoogle(Calendar $service, $schedule, $calendarId = 'primary')
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
            // Extract just the date part from start_date and combine with recurrence_time
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
                \Log::info('Updating existing Google Calendar event', [
                    'schedule_id' => $schedule->id,
                    'event_id' => $schedule->google_event_id,
                ]);

                $createdEvent = $service->events->update($calendarId, $schedule->google_event_id, $event);

            } catch (\Exception $e) {
                // If event doesn't exist anymore, create a new one
                \Log::warning('Event not found, creating new one', [
                    'schedule_id' => $schedule->id,
                    'old_event_id' => $schedule->google_event_id,
                    'error' => $e->getMessage(),
                ]);

                $createdEvent = $service->events->insert($calendarId, $event);
                $schedule->update(['google_event_id' => $createdEvent->getId()]);
            }
        } else {
            // Create new event
            \Log::info('Creating new Google Calendar event', ['schedule_id' => $schedule->id]);
            $createdEvent = $service->events->insert($calendarId, $event);

            // Save event ID to schedule
            $schedule->update(['google_event_id' => $createdEvent->getId()]);
        }

        \Log::info('Google Calendar event synced', [
            'schedule_id' => $schedule->id,
            'event_id' => $createdEvent->getId(),
        ]);
    }

    private function buildRecurrenceRule($schedule): ?string
    {
        if (!$schedule->recurrence_type) {
            return null;
        }

        $rrule = 'RRULE:';

        switch ($schedule->recurrence_type) {
            case 'daily':
                $rrule .= 'FREQ=DAILY';
                break;

            case 'weekly':
                $rrule .= 'FREQ=WEEKLY';
                if ($schedule->recurrence_days) {
                    $days = array_map(function ($day) {
                        return ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'][$day];
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
            $rrule .= ';UNTIL=' . \Carbon\Carbon::parse($schedule->end_date)->format('Ymd\THis\Z');
        }

        return $rrule;
    }
}
