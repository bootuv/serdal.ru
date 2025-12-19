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
        if ($request->has('error')) {
            return redirect()
                ->route('filament.app.resources.rooms.index')
                ->with('error', 'Авторизация Google Calendar отменена.');
        }

        $client = $this->getClient();

        try {
            // Exchange authorization code for access token
            $token = $client->fetchAccessTokenWithAuthCode($request->code);

            if (isset($token['error'])) {
                throw new \Exception($token['error_description'] ?? 'Unknown error');
            }

            // Save tokens to user
            $user = Auth::user();
            $user->update([
                'google_access_token' => $token['access_token'],
                'google_refresh_token' => $token['refresh_token'] ?? null,
                'google_token_expires_at' => now()->addSeconds($token['expires_in']),
            ]);

            Notification::make()
                ->title('Google Calendar подключен!')
                ->body('Теперь ваши занятия будут автоматически синхронизироваться с Google Calendar.')
                ->success()
                ->send();

            return redirect()->route('filament.app.resources.rooms.index');

        } catch (\Exception $e) {
            \Log::error('Google Calendar OAuth Error: ' . $e->getMessage());

            return redirect()
                ->route('filament.app.resources.rooms.index')
                ->with('error', 'Ошибка подключения Google Calendar: ' . $e->getMessage());
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

            $syncedCount = 0;

            foreach ($schedules as $schedule) {
                // Create or update Google Calendar event
                $this->syncScheduleToGoogle($service, $schedule);
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

    private function syncScheduleToGoogle(Calendar $service, $schedule)
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
            $startTime = \Carbon\Carbon::parse($schedule->start_date . ' ' . $schedule->recurrence_time);
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

        // Insert event into calendar
        $calendarId = 'primary'; // Use primary calendar
        $createdEvent = $service->events->insert($calendarId, $event);

        \Log::info('Google Calendar event created', [
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
