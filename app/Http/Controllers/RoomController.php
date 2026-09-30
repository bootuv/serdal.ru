<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\Bbb\BbbServerPool;
use App\Services\Bbb\NoBbbServerException;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /** Диск, на котором FileUploadHelper хранит презентации занятий. */
    private const PRESENTATIONS_DISK = 's3';

    public function start(Room $room)
    {
        if ($room->user_id !== auth()->id()) {
            abort(403);
        }

        // Check if user already has a running meeting
        $hasRunningMeeting = Room::where('user_id', auth()->id())
            ->where('is_running', true)
            ->where('id', '!=', $room->id)
            ->exists();

        if ($hasRunningMeeting) {
            return back()->with('error', 'У вас уже есть запущенное занятие. Пожалуйста, завершите его перед запуском нового.');
        }

        // Лимиты подписки: блокируем создание нового занятия (повторный вход
        // в уже запущенную комнату не ограничиваем)
        if (!$room->is_running && ($limitError = \App\Services\SubscriptionService::canStartLesson(auth()->user()))) {
            return back(fallback: route('cabinet.teacher.subscription'))->with('error', $limitError);
        }

        $user = auth()->user();
        $pool = app(BbbServerPool::class);

        try {
            // Сервер, на котором занятие уже идёт (повторный вход учителя). Не отвечает, а занятие
            // у нас не отмечено идущим — просто начинаем заново на другом сервере
            $server = $room->is_running || $room->bbb_server_id ? $pool->forRoom($room) : null;
            $running = false;
            if ($server) {
                try {
                    $running = $server->client()->isMeetingRunning(['meetingID' => $room->meeting_id]);
                } catch (\Throwable $e) {
                    if ($room->is_running) {
                        throw $e;
                    }
                }
            }

            if (! $running) {
                // Новое занятие — на наименее загруженный сервер (или личный сервер учителя)
                $server = $pool->pick($room);

                // Prepare presentations (will be used on production only)
                $presentationFiles = [];
                if ($room->presentations) {
                    $presentationFiles = $room->presentations;
                }

                // Create meeting parameters
                // Load global BBB settings
                $globalSettings = [
                    'record' => \App\Models\Setting::where('key', 'bbb_record')->value('value') === '1',
                    'auto_start_recording' => \App\Models\Setting::where('key', 'bbb_auto_start_recording')->value('value') === '1',
                    'allow_start_stop_recording' => \App\Models\Setting::where('key', 'bbb_allow_start_stop_recording')->value('value') !== '0',
                    'mute_on_start' => \App\Models\Setting::where('key', 'bbb_mute_on_start')->value('value') === '1',
                    'webcams_only_for_moderator' => \App\Models\Setting::where('key', 'bbb_webcams_only_for_moderator')->value('value') === '1',
                    'max_participants' => (int) (\App\Models\Setting::where('key', 'bbb_max_participants')->value('value') ?? 0),
                    'duration' => (int) (\App\Models\Setting::where('key', 'bbb_duration')->value('value') ?? 0),
                ];

                // Лимиты тарифа: участники и длительность применяются сервером BBB
                // (берём самое строгое из глобального и тарифного ограничения),
                // запись отключается, если тариф не включает хранение записей
                $tariffLimits = \App\Services\SubscriptionService::meetingLimits($user);

                $globalSettings['max_participants'] = collect([
                    $globalSettings['max_participants'],
                    $tariffLimits['max_participants'],
                ])->filter(fn($v) => (int) $v > 0)->min() ?? 0;

                $globalSettings['duration'] = collect([
                    $globalSettings['duration'],
                    $tariffLimits['duration_minutes'],
                ])->filter(fn($v) => (int) $v > 0)->min() ?? 0;

                $globalSettings['record'] = $globalSettings['record'] && $tariffLimits['record_allowed'];
                $globalSettings['auto_start_recording'] = $globalSettings['auto_start_recording'] && $tariffLimits['record_allowed'];

                $inviteUrl = route('rooms.join', $room);
                $welcomeMsg = $room->welcome_msg ?: "Добро пожаловать на занятие <b>{$room->name}</b>!<br>Пожалуйста, проверьте работу микрофона и динамиков.";
                $finalWelcomeMsg = $welcomeMsg . "<br><br>Пригласить гостя можно по ссылке:<br><a href='{$inviteUrl}' target='_blank'>{$inviteUrl}</a>";

                $createParams = [
                    'meetingID' => $room->meeting_id,
                    'meetingName' => $room->name,
                    'attendeePW' => $room->attendee_pw,
                    'moderatorPW' => $room->moderator_pw,
                    'welcome' => $finalWelcomeMsg,

                    // Apply global settings
                    'record' => $globalSettings['record'],
                    'autoStartRecording' => $globalSettings['auto_start_recording'],
                    'allowStartStopRecording' => $globalSettings['allow_start_stop_recording'],
                    'muteOnStart' => $globalSettings['mute_on_start'],
                    'webcamsOnlyForModerator' => $globalSettings['webcams_only_for_moderator'],
                    'maxParticipants' => $globalSettings['max_participants'],
                    'duration' => $globalSettings['duration'],
                ];

                // Create MeetingSession FIRST to get ID for logout URL
                $meetingSession = \App\Models\MeetingSession::create([
                    'user_id' => auth()->id(),
                    'room_id' => $room->id,
                    'bbb_server_id' => $server->id,
                    'meeting_id' => $room->meeting_id,
                    'internal_meeting_id' => null, // Will be updated after BBB create
                    'started_at' => now(),
                    'status' => 'running',
                    'settings_snapshot' => $createParams,
                ]);

                // Set logout URL to our redirect controller that handles role-based routing
                // BBB only allows one logoutUrl per meeting, so we use a controller to handle different roles
                $createParams['logoutUrl'] = route('session.logout', $meetingSession);

                \Illuminate\Support\Facades\Log::info('BBB Create: logoutUrl being set', [
                    'logoutUrl' => $createParams['logoutUrl'],
                    'session_id' => $meetingSession->id,
                ]);

                // Only upload presentations if not running on localhost
                $appUrl = config('app.url');
                $isLocalhost = str_contains($appUrl, '127.0.0.1') || str_contains($appUrl, 'localhost');
                $forceLocalPresentations = config('bigbluebutton.force_local_presentations', env('BBB_FORCE_LOCAL_PRESENTATIONS', false));

                // Prepare presentation URLs
                $presentationUrls = [];

                // ALWAYS add whiteboard as the first presentation (works on both localhost and production)
                $whiteboardPath = public_path('defaults/whiteboard.pdf');
                if (file_exists($whiteboardPath)) {
                    $presentationUrls[] = [
                        'link' => url('defaults/whiteboard.pdf'),
                        'fileName' => 'whiteboard.pdf',
                    ];
                }

                // Презентации учителя: проверяем и берём ссылку на том же диске, куда их сохранил
                // FileUploadHelper (s3), а не на диске по умолчанию — иначе при FILESYSTEM_DISK=local
                // файлы молча пропускались и не попадали в класс
                $presentationDisk = \Illuminate\Support\Facades\Storage::disk(self::PRESENTATIONS_DISK);
                if (!empty($presentationFiles)) {
                    foreach ($presentationFiles as $path) {
                        if (is_string($path) && $path !== '' && $presentationDisk->exists($path)) {
                            $presentationUrls[] = [
                                'link' => $presentationDisk->url($path),
                                'fileName' => basename($path),
                            ];
                        }
                    }
                }

                // Determine if we should send presentations
                $shouldSendPresentations = !$isLocalhost || $forceLocalPresentations;
                // Облачный диск презентаций отдаёт ссылки, доступные серверу BBB даже при запуске с localhost
                if (! in_array(config('filesystems.disks.' . self::PRESENTATIONS_DISK . '.driver'), ['local', null], true)) {
                    $shouldSendPresentations = true;
                }

                if ($shouldSendPresentations) {
                    // Add presentations to create params if any exist
                    if (!empty($presentationUrls)) {
                        $createParams['presentation'] = $presentationUrls;

                        \Illuminate\Support\Facades\Log::info('Creating BBB meeting with presentations', [
                            'meetingID' => $room->meeting_id,
                            'presentation_count' => count($presentationUrls),
                            'presentations' => array_column($presentationUrls, 'fileName'),
                            'is_localhost' => $isLocalhost,
                            'forced' => $forceLocalPresentations,
                            'filesystem' => self::PRESENTATIONS_DISK
                        ]);
                    }
                } else {
                    \Illuminate\Support\Facades\Log::info('Skipping presentations on localhost', [
                        'meetingID' => $room->meeting_id,
                        'reason' => 'BBB server cannot access localhost URLs',
                        'note' => 'Presentations will work automatically on production with public domain',
                        'prepared_files' => array_column($presentationUrls, 'fileName'),
                        'tip' => 'Set BBB_FORCE_LOCAL_PRESENTATIONS=true in .env to override'
                    ]);
                }

                // Сервер упал между проверками — помечаем его и создаём занятие на следующем по нагрузке
                $failed = [];
                while (true) {
                    try {
                        $response = $server->client()->create($createParams);
                        if (! is_iterable($response)) {
                            throw new \RuntimeException('BBB create failed: ' . $response);
                        }
                        break;
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('BBB: не удалось создать занятие на сервере', ['server' => $server->host(), 'error' => $e->getMessage()]);
                        if (! $server->exists) {
                            $meetingSession->delete();
                            throw $e;
                        }
                        $server->update(['is_online' => false, 'error' => 'Сервер не отвечает', 'checked_at' => now()]);
                        $failed[] = $server->id;
                        try {
                            $server = $pool->pick($room, $failed);
                        } catch (NoBbbServerException) {
                            $meetingSession->delete();
                            throw $e;
                        }
                        $meetingSession->update(['bbb_server_id' => $server->id]);
                    }
                }
                $internalMeetingId = $response['internalMeetingID'] ?? null;

                // Update session with internalMeetingId
                $meetingSession->update([
                    'internal_meeting_id' => $internalMeetingId,
                    'settings_snapshot' => $createParams, // Update with final params including logoutURL
                ]);

                $room->update(['is_running' => true, 'bbb_server_id' => $server->id]);
                \App\Events\RoomStatusUpdated::dispatch();

                // Notify assigned students about lesson start
                foreach ($room->participants as $student) {
                    $student->notify(new \App\Notifications\LessonStarted($room));
                }

                // Register Webhook for Analytics
                try {
                    $webhookUrl = route('api.bbb.webhook');

                    // If localhost, we might need a tunnel URL or just log a warning
                    $appUrl = config('app.url');
                    if (str_contains($appUrl, '127.0.0.1') || str_contains($appUrl, 'localhost')) {
                        \Illuminate\Support\Facades\Log::warning('BBB Webhook: Skipping registration on localhost.', ['url' => $webhookUrl]);
                    } else {
                        $server->client()->hooksCreate([
                            'meetingID' => $room->meeting_id,
                            'callbackURL' => $webhookUrl,
                            'getRaw' => false, // Use processed format with external-meeting-id
                        ]);
                        \Illuminate\Support\Facades\Log::info('BBB Webhook: Registered successfully.', ['url' => $webhookUrl]);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('BBB Webhook: Failed to register.', ['error' => $e->getMessage()]);
                }
            } else {
                // If meeting is already running, we need to find the existing session for the redirect
                $meetingSession = \App\Models\MeetingSession::where('room_id', $room->id)
                    ->where('meeting_id', $room->meeting_id)
                    ->latest()
                    ->first();
            }

            // Note: logoutURL is set at meeting creation time, not per-user join
            // The redirect will go to the session report for all users
            return redirect()->to(
                $server->client()->join([
                    'meetingID' => $room->meeting_id,
                    'userName' => auth()->user()->name,
                    'password' => $room->moderator_pw, // Owner is moderator
                    'userID' => (string) auth()->id(),
                    'avatarURL' => auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : null,
                ])
            );
        } catch (NoBbbServerException $e) {
            \Illuminate\Support\Facades\Log::error('BBB: нет серверов для нового занятия', ['room_id' => $room->id]);

            return back()->with('error', 'Не удалось начать занятие: сервер видеосвязи не настроен. Напишите в поддержку.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('BBB Error in start()', [
                'room_id' => $room->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Не удалось начать занятие. Попробуйте через минуту.');
        }
    }

    public function joinAsGuest(Request $request, Room $room)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        session(['guest_name' => $data['name']]);

        return redirect()->route('rooms.connect', $room);
    }

    /**
     * Actually connect to BBB meeting (after Livewire guest join page)
     */
    public function connect(Room $room)
    {
        // Ученик с просроченной оплатой перед владельцем комнаты к занятию не допускается.
        // Это единственная точка входа в BBB для участников, поэтому проверка здесь —
        // кнопки в интерфейсе лишь дублируют её.
        if (auth()->check() && $room->user_id !== auth()->id()
            && \App\Services\PaymentRecordService::isBlockedForTeacher(auth()->id(), $room->user_id)) {
            return redirect()->route('cabinet.student.payments')
                ->with('error', "Вход закрыт: есть занятия у учителя {$room->user?->name}, не оплаченные в срок. Доступ откроется, когда учитель отметит оплату.");
        }

        // Сервер, на котором идёт занятие этой комнаты
        $server = app(BbbServerPool::class)->forRoom($room);
        if (! $server) {
            return redirect()->route('rooms.join', $room);
        }

        try {
            if (! $server->client()->isMeetingRunning(['meetingID' => $room->meeting_id])) {
                // If meeting not running, redirect to the Livewire join page
                return redirect()->route('rooms.join', $room);
            }

            // Determine User Identity
            if (auth()->check()) {
                $userName = auth()->user()->name;
                $password = $room->user_id === auth()->id() ? $room->moderator_pw : $room->attendee_pw;
                $userID = (string) auth()->id();
                $avatarURL = auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : null;
            } elseif (session()->has('guest_name')) {
                $userName = session('guest_name');
                $password = $room->attendee_pw;
                // Generate a consistent guest ID based on session
                $userID = 'guest_' . substr(session()->getId(), 0, 10);
                $avatarURL = null;
            } else {
                // Not authenticated and no guest name -> redirect to guest join page
                return redirect()->route('rooms.join', $room);
            }

            // Места в классе: лимит участников BBB считает всех. Заполнено — в BBB не отправляем
            // (там человек увидел бы служебную страницу), показываем свой экран и сообщаем учителю
            if ($room->user_id !== auth()->id()) {
                $capacity = app(\App\Services\RoomCapacityService::class);
                if ($max = $capacity->fullLimit($server, $room, $userID)) {
                    $capacity->refused($room, $userID, $userName, $max);

                    return redirect()->route('rooms.join', $room)->with(\App\Services\RoomCapacityService::SESSION_KEY, $max);
                }
            }

            // Note: logoutURL is set at meeting creation time (in start method)
            // The redirect is the same for all users of this meeting
            return redirect()->to(
                $server->client()->join([
                    'meetingID' => $room->meeting_id,
                    'userName' => $userName,
                    'password' => $password,
                    'userID' => $userID,
                    'avatarURL' => $avatarURL,
                    // Отказ BBB (место заняли в ту же секунду и т. п.) вернёт человека к нам, а не на служебную страницу
                    'errorRedirectUrl' => route('rooms.join-failed', $room),
                ])
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('BBB Error in connect()', [
                'room_id' => $room->id,
                'error' => $e->getMessage(),
            ]);

            return redirect(auth()->check() ? \App\Http\Middleware\EnsureCabinetRole::homeFor(auth()->user()) : route('rooms.join', $room))
                ->with('error', 'Не удалось подключиться к занятию. Попробуйте через минуту.');
        }
    }

    /**
     * Сюда BBB возвращает человека, если не пустил в класс (errorRedirectUrl при входе).
     * Код ошибки BBB дописывает к адресу; места кончились — maxParticipantsReached.
     */
    public function joinFailed(Request $request, Room $room)
    {
        if (! str_contains((string) $request->getQueryString(), 'maxParticipantsReached')) {
            return redirect()->route('rooms.join', $room)->with('error', 'Не удалось подключиться к занятию. Попробуйте через минуту.');
        }

        $capacity = app(\App\Services\RoomCapacityService::class);
        $max = $capacity->limitOf($room);

        if ($room->user_id !== auth()->id()) {
            [$userID, $userName] = auth()->check()
                ? [(string) auth()->id(), auth()->user()->name]
                : ['guest_' . substr(session()->getId(), 0, 10), (string) session('guest_name', 'Гость')];

            $capacity->refused($room, $userID, $userName, $max);
        }

        return redirect()->route('rooms.join', $room)->with(\App\Services\RoomCapacityService::SESSION_KEY, $max ?? 0);
    }

    public function stop(Room $room)
    {
        // Владелец занятия или администратор (раньше — hasRole(), которого у User нет: у администратора падало с 500)
        if ($room->user_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            abort(403);
        }

        $room->user_id !== auth()->id()
            ? app(\App\Services\LessonStopService::class)->stopByAdmin($room, auth()->user())
            : app(\App\Services\LessonStopService::class)->stop($room);

        return back()->with('success', 'Занятие завершено');
    }

}
