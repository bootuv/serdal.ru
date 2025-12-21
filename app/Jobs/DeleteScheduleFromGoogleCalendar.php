<?php

namespace App\Jobs;

use App\Models\User;
use Google\Client;
use Google\Service\Calendar;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteScheduleFromGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $googleEventId;
    public $userId;
    public $scheduleId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $googleEventId, int $userId, int $scheduleId)
    {
        $this->googleEventId = $googleEventId;
        $this->userId = $userId;
        $this->scheduleId = $scheduleId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user || !$user->google_access_token) {
            Log::info('User not connected to Google Calendar, skipping delete', [
                'user_id' => $this->userId,
                'schedule_id' => $this->scheduleId,
            ]);
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

            // Get the Serdal calendar ID
            $calendarId = $user->google_calendar_id ?? 'primary';

            // Delete the event
            $service->events->delete($calendarId, $this->googleEventId);

            Log::info('Deleted event from Google Calendar', [
                'schedule_id' => $this->scheduleId,
                'event_id' => $this->googleEventId,
                'user_id' => $user->id,
                'calendar_id' => $calendarId,
            ]);

        } catch (\Google\Service\Exception $e) {
            // If event is already deleted (404), log and continue
            if ($e->getCode() === 404) {
                Log::info('Event already deleted from Google Calendar', [
                    'schedule_id' => $this->scheduleId,
                    'event_id' => $this->googleEventId,
                    'user_id' => $user->id,
                ]);
                return;
            }

            Log::error('Failed to delete event from Google Calendar', [
                'schedule_id' => $this->scheduleId,
                'event_id' => $this->googleEventId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete event from Google Calendar', [
                'schedule_id' => $this->scheduleId,
                'event_id' => $this->googleEventId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
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
}
