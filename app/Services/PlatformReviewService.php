<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\Review;
use App\Models\User;
use App\Notifications\PlatformReviewSubmitted;

/**
 * Отзывы учителей о платформе. Один отзыв на учителя, его можно изменить.
 * На /reviews отзыв попадает после проверки администратором (approved_at) и только с согласия автора (show_on_site).
 * Изменённый отзыв снова уходит на проверку.
 */
class PlatformReviewService
{
    /** Приглашение на «Сегодня»: после стольких проведённых занятий… */
    public const PROMPT_AFTER_LESSONS = 5;

    /** …и стольких дней на платформе. */
    public const PROMPT_AFTER_DAYS = 14;

    public function forTeacher(User $teacher): ?Review
    {
        return Review::platform()->where('user_id', $teacher->id)->first();
    }

    /** Показать приглашение «Как вам Serdal?»: отзыва нет, приглашение не закрывали, учитель уже освоился. */
    public function shouldPrompt(User $teacher): bool
    {
        if ($teacher->role !== User::ROLE_TUTOR || $teacher->platform_review_dismissed_at || $this->forTeacher($teacher)) {
            return false;
        }

        if ($teacher->created_at && $teacher->created_at->gt(now()->subDays(self::PROMPT_AFTER_DAYS))) {
            return false;
        }

        return MeetingSession::where('user_id', $teacher->id)->where('status', 'completed')->count() >= self::PROMPT_AFTER_LESSONS;
    }

    public function dismissPrompt(User $teacher): void
    {
        $teacher->forceFill(['platform_review_dismissed_at' => now()])->save();
    }

    /** Сохранить отзыв (новый или изменённый) — он уходит на проверку, администраторы получают уведомление. */
    public function save(User $teacher, int $rating, string $text, bool $showOnSite): Review
    {
        $review = $this->forTeacher($teacher) ?? new Review(['user_id' => $teacher->id]);
        $isNew = ! $review->exists;

        $review->fill([
            'rating' => max(1, min(5, $rating)),
            'text' => trim($text),
            'show_on_site' => $showOnSite,
            'approved_at' => null,
            'is_rejected' => false,
            'hidden_at' => null,
        ])->save();

        User::where('role', User::ROLE_ADMIN)->get()->each(fn (User $admin) => $admin->notify(new PlatformReviewSubmitted($review, $isNew)));

        return $review;
    }

    /** Состояние для учителя: «На проверке», «На сайте», «Виден только команде Serdal», «Не опубликован». */
    public static function status(Review $review): string
    {
        return match (true) {
            (bool) $review->is_rejected => 'Не опубликован',
            ! $review->show_on_site => 'Виден только команде Serdal',
            $review->approved_at !== null => 'На сайте',
            default => 'На проверке',
        };
    }
}
