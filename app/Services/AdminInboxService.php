<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\Review;
use App\Models\SupportMessage;
use App\Models\TeacherApplication;
use App\Models\User;

/** Счётчики «Входящих» админки: что ждёт решения администратора. */
class AdminInboxService
{
    /** Непрочитанные сообщения пользователей в поддержку. */
    public function supportUnread(): int
    {
        return SupportMessage::whereNull('read_at')
            ->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_ADMIN))
            ->count();
    }

    /** Заявки учителей на рассмотрении. */
    public function applicationsPending(): int
    {
        return TeacherApplication::where('status', TeacherApplication::STATUS_PENDING)->count();
    }

    /** Жалобы учителей на отзывы без решения. */
    public function reviewComplaints(): int
    {
        return Review::where('is_reported', true)->where('is_rejected', false)->count();
    }

    /** Запросы учителей на удаление проведённого занятия. */
    public function deletionRequests(): int
    {
        return MeetingSession::whereNotNull('deletion_requested_at')->count();
    }

    /** Все счётчики для меню. */
    public function counts(): array
    {
        return [
            'support' => $this->supportUnread(),
            'applications' => $this->applicationsPending(),
            'reviews' => $this->reviewComplaints(),
            'lessons' => $this->deletionRequests(),
        ];
    }
}
