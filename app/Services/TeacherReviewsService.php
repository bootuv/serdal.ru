<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use App\Notifications\TeacherReportedReview;
use Illuminate\Database\Eloquent\Builder;

/**
 * Отзывы учеников об учителе: список, прочтение, жалоба, сводка оценок.
 * Используется старым (Filament, ReviewResource) и новым кабинетом учителя.
 */
class TeacherReviewsService
{
    /** Отзывы учителя, кроме отклонённых модератором. */
    public function query(User $teacher): Builder
    {
        return Review::query()
            ->where('teacher_id', $teacher->id)
            ->where('is_rejected', false);
    }

    /** Сколько новых (непрочитанных) отзывов — счётчик в меню. */
    public function unreadCount(User $teacher): int
    {
        return $this->query($teacher)->whereNull('teacher_read_at')->count();
    }

    /** Отзыв открыт учителем — считается прочитанным. */
    public function markRead(Review $review): void
    {
        if ($review->teacher_read_at === null) {
            $review->forceFill(['teacher_read_at' => now()])->saveQuietly();
        }
    }

    /** Жалоба на отзыв: отметка и уведомление всем администраторам. */
    public function report(Review $review, User $teacher): void
    {
        if ($review->is_reported) {
            return;
        }

        $review->update(['is_reported' => true]);

        foreach (User::where('role', User::ROLE_ADMIN)->get() as $admin) {
            $admin->notify(new TeacherReportedReview($review, $teacher));
        }
    }

    /**
     * Средняя оценка и распределение.
     *
     * @return array{count:int, avg:?float, bars:array<int,int>}
     */
    public function summary(User $teacher): array
    {
        $byRating = $this->query($teacher)
            ->selectRaw('rating, count(*) as n')
            ->groupBy('rating')
            ->pluck('n', 'rating');

        $bars = [];
        foreach ([5, 4, 3, 2, 1] as $r) {
            $bars[$r] = (int) ($byRating[$r] ?? 0);
        }

        $count = array_sum($bars);
        $sum = array_sum(array_map(fn ($r, $n) => $r * $n, array_keys($bars), $bars));

        return [
            'count' => $count,
            'avg' => $count > 0 ? round($sum / $count, 1) : null,
            'bars' => $bars,
        ];
    }
}
