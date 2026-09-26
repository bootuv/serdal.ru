<?php

namespace App\Livewire\Cabinet;

use App\Support\CabinetUrl;
use App\Support\HumanDate;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Панель уведомлений кабинета: открывается колокольчиком (событие notifications-open),
 * «Новые» — непрочитанные, «Раньше» — прочитанные. Макет: «Уведомления» (SyNotifTeacher, SyNotifMobile).
 */
class Notifications extends Component
{
    private const LIMIT = 50;

    /** Иконки уведомлений старого кабинета (heroicons) → иконки кабинета. */
    private const ICONS = [
        'chat' => 'chat', 'lifebuoy' => 'help', 'academic-cap' => 'tasks', 'clipboard' => 'tasks', 'document' => 'tasks',
        'star' => 'star', 'flag' => 'star', 'banknotes' => 'wallet', 'credit-card' => 'wallet', 'gift' => 'wallet',
        'play' => 'video', 'calendar' => 'calendar', 'clock' => 'clock', 'user' => 'users', 'arrow-path' => 'repeat',
    ];

    public bool $open = false;

    #[On('notifications-open')]
    public function show(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function getListeners(): array
    {
        return [
            'echo-private:App.Models.User.' . auth()->id() . ',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => 'incoming',
        ];
    }

    /** Пришло новое уведомление: тост и красная точка на колокольчике. */
    public function incoming(array $event = []): void
    {
        if (! empty($event['title'])) {
            $this->dispatch('toast', message: $event['title']);
        }
    }

    public function visit(string $id): void
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        $url = CabinetUrl::fromLegacy($n->data['actions'][0]['url'] ?? null, auth()->user());
        if ($url) {
            $this->redirect($url);
        }
    }

    public function readAll(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function clear(): void
    {
        auth()->user()->notifications()->delete();
    }

    /** Счётчики пунктов меню: сообщения, работы на проверку, новые отзывы. */
    public static function navCounts(\App\Models\User $user): array
    {
        $counts = ['messages' => app(\App\Services\MessengerService::class)->unreadCount($user)];
        if ($user->role !== \App\Models\User::ROLE_STUDENT) {
            $counts['tasks'] = \App\Services\HomeworkSubmissionService::toReview($user->id)->reorder()->count();
            $counts['reviews'] = app(\App\Services\TeacherReviewsService::class)->unreadCount($user);
        }

        return $counts;
    }

    private function icon(?string $heroicon): string
    {
        foreach (self::ICONS as $needle => $icon) {
            if ($heroicon && str_contains($heroicon, $needle)) {
                return $icon;
            }
        }

        return 'bell';
    }

    private function time(\Carbon\CarbonInterface $at): string
    {
        return match (true) {
            $at->isToday() => $at->format('H:i'),
            $at->isYesterday() => 'вчера',
            default => HumanDate::date($at),
        };
    }

    public function render()
    {
        $user = auth()->user();
        $unread = $user->unreadNotifications()->count();
        $this->dispatch('notifications-count', count: $unread);
        // Счётчики в меню (раскладка слушает cabinet-counts): обновляются с каждым уведомлением и опросом раз в минуту
        $this->dispatch('cabinet-counts', counts: self::navCounts($user));

        $items = $this->open
            ? $user->notifications()->latest()->limit(self::LIMIT)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => (string) ($n->data['title'] ?? ''),
                'body' => trim(strip_tags((string) ($n->data['body'] ?? ''))),
                'icon' => $this->icon($n->data['icon'] ?? null),
                'time' => $this->time($n->created_at),
                'unread' => $n->read_at === null,
                'link' => ! empty($n->data['actions'][0]['url']),
            ])
            : collect();

        $teacher = $user->role !== \App\Models\User::ROLE_STUDENT;
        $settings = $teacher
            ? (Route::has('cabinet.teacher.profile') ? route('cabinet.teacher.profile', ['tab' => 'notify']) : null)
            : (Route::has('cabinet.student.profile') ? route('cabinet.student.profile') : null);

        return view('livewire.cabinet.notifications', [
            'fresh' => $items->where('unread', true)->values(),
            'old' => $items->where('unread', false)->values(),
            'unread' => $unread,
            'settingsUrl' => $settings,
            'emptyText' => $teacher
                ? 'Пока пусто — здесь появятся сданные работы, сообщения, отзывы и оплаты.'
                : 'Пока пусто — здесь появятся задания, оценки и изменения в расписании.',
        ]);
    }
}
