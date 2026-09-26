<?php

namespace App\Http\Controllers;

use Google\Client;
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

        // Куда вернуть после Google: на страницу, с которой подключали (новый или старый кабинет)
        session(['google_calendar_return' => url()->previous()]);

        return redirect($authUrl);
    }

    public function handleGoogleCallback(Request $request)
    {
        \Log::info('Google Calendar Callback received', ['has_code' => $request->has('code'), 'has_error' => $request->has('error')]);

        if ($request->has('error')) {
            \Log::warning('Google Calendar authorization cancelled', ['error' => $request->error]);

            $this->notify('Авторизация отменена', 'Вы отменили авторизацию Google Calendar.', 'warning');

            return $this->backToCabinet();
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

            // Trigger initial sync of all existing schedules
            $this->triggerInitialSync($user);

            $this->notify('Google Calendar подключен!', 'Теперь ваше расписание будет автоматически синхронизироваться с Google Calendar.', 'success');

            return $this->backToCabinet();

        } catch (\Exception $e) {
            \Log::error('Google Calendar OAuth Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->notify('Ошибка подключения', 'Не удалось подключить Google Calendar: ' . $e->getMessage(), 'danger');

            return $this->backToCabinet();
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

        $this->notify('Google Calendar отключен', 'Синхронизация с Google Calendar отключена.', 'warning');

        return redirect()->back();
    }

    private function triggerInitialSync($user)
    {
        try {
            \Log::info('Triggering initial sync for user', ['user_id' => $user->id]);

            // Get user's schedules based on role
            if ($user->role === 'student') {
                // For students: get schedules for rooms they are assigned to
                $schedules = \App\Models\RoomSchedule::whereHas('room', function ($query) use ($user) {
                    $query->whereJsonContains('participants', (string) $user->id);
                })->where('is_active', true)->get();
            } else {
                // For teachers/tutors: get their own room schedules
                $schedules = \App\Models\RoomSchedule::whereHas('room', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->where('is_active', true)->get();
            }

            // Dispatch sync jobs for each schedule
            foreach ($schedules as $schedule) {
                \App\Jobs\SyncScheduleToGoogleCalendar::dispatch($schedule, $user->id);
            }

            \Log::info('Initial sync jobs dispatched', [
                'user_id' => $user->id,
                'schedules_count' => $schedules->count(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to trigger initial sync', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function syncSchedule()
    {
        $user = Auth::user();

        if (!$user->google_access_token) {
            $this->notify('Google Calendar не подключен', 'Сначала подключите Google Calendar.', 'warning');

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

            // Синхронизацию делает та же задача, что и автоматическая: она учитывает отменённые и перенесённые занятия
            $syncedCount = 0;
            foreach ($schedules as $schedule) {
                \App\Jobs\SyncScheduleToGoogleCalendar::dispatch($schedule, $user->id);
                $syncedCount++;
            }

            $this->notify('Синхронизация завершена!', "Синхронизировано занятий: {$syncedCount}", 'success');

            return redirect()->back();

        } catch (\Exception $e) {
            \Log::error('Google Calendar Sync Error: ' . $e->getMessage());

            $this->notify('Ошибка синхронизации', $e->getMessage(), 'danger');

            return redirect()->back();
        }
    }
    private function backToCabinet()
    {
        $back = session()->pull('google_calendar_return');
        if ($back && ! str_contains($back, '/google/calendar')) {
            return redirect($back);
        }

        return Auth::user()?->role === 'student'
            ? redirect()->route('cabinet.student.schedule')
            : redirect()->route('cabinet.teacher.schedule');
    }

    /** Сообщение о результате: в старом кабинете — уведомление Filament, в новом — тост. */
    private function notify(string $title, string $body, string $type): void
    {
        $path = (string) parse_url((string) (session('google_calendar_return') ?? url()->previous()), PHP_URL_PATH);
        if (str_starts_with($path, '/tutor') || str_starts_with($path, '/student')) {
            Notification::make()->title($title)->body($body)->{$type}()->send();

            return;
        }

        session()->flash($type === 'danger' ? 'error' : 'toast', $type === 'danger' ? $title . '. ' . $body : $title);
    }
}
