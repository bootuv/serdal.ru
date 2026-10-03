<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewReportDecided;
use Illuminate\Database\Eloquent\Builder;

/**
 * Модерация отзывов администратором: жалобы учителей, скрытие и возврат отзыва.
 * Используется админкой (Cabinet\Admin\Reviews).
 * Скрытый отзыв (is_rejected) не виден на странице учителя, не входит в оценку, и ученик не может оставить новый.
 */
class AdminReviewsService
{
    public const TAB_REPORTS = 'reports';

    public const TAB_ALL = 'all';

    public const TAB_HIDDEN = 'hidden';

    public const TAB_PLATFORM = 'platform';

    /** Отзывы вкладки: жалобы без решения · все видимые · скрытые (об учителях) · о платформе (сначала ждущие проверки). */
    public function query(string $tab, string $search = ''): Builder
    {
        $query = Review::query()->with(['user:id,name,avatar', 'teacher:id,name']);

        if ($tab === self::TAB_PLATFORM) {
            $query->platform()
                ->orderByRaw('CASE WHEN approved_at IS NULL AND is_rejected = 0 AND show_on_site = 1 THEN 0 ELSE 1 END')
                ->latest('updated_at')->orderByDesc('id');
        } else {
            $query->aboutTeachers();
        }

        match ($tab) {
            self::TAB_PLATFORM => null,
            self::TAB_REPORTS => $query->where('is_reported', true)->where('is_rejected', false)->orderBy('reported_at')->orderBy('id'),
            self::TAB_HIDDEN => $query->where('is_rejected', true)->orderByDesc('hidden_at')->orderByDesc('id'),
            default => $query->where('is_rejected', false)->latest()->orderByDesc('id'),
        };

        if (($term = trim($search)) !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $query->where(fn (Builder $q) => $q
                ->whereHas('user', fn (Builder $u) => $u->where('name', 'like', $like))
                ->orWhereHas('teacher', fn (Builder $u) => $u->where('name', 'like', $like)));
        }

        return $query;
    }

    /** Жалобы без решения — счётчик вкладки. */
    public function reportsCount(): int
    {
        return Review::where('is_reported', true)->where('is_rejected', false)->count();
    }

    /** Отзывы о платформе, ждущие проверки, — счётчик вкладки. */
    public function platformPendingCount(): int
    {
        return app(AdminInboxService::class)->platformReviewsPending();
    }

    /** «Опубликовать» отзыв о платформе: появится на /reviews. */
    public function approve(Review $review): void
    {
        abort_unless($review->isPlatform(), 404);
        $review->update(['approved_at' => now(), 'is_rejected' => false, 'hidden_at' => null]);
    }

    /** «Оставить отзыв»: жалоба снята, отзыв остаётся. Причина жалобы стирается. */
    public function keep(Review $review, bool $notifyTeacher = false): void
    {
        $wasReported = (bool) $review->is_reported;

        $review->update(['is_reported' => false, 'report_reason' => null, 'report_note' => null, 'reported_at' => null]);

        if ($wasReported && $notifyTeacher) {
            $this->notifyTeacher($review, ReviewReportDecided::KEPT);
        }
    }

    /** «Скрыть отзыв». Причина жалобы сохраняется — видна во вкладке «Скрытые». */
    public function hide(Review $review, bool $notifyTeacher = false): void
    {
        if ($review->is_rejected) {
            return;
        }

        $wasReported = (bool) $review->is_reported;

        $review->update(['is_rejected' => true, 'is_reported' => false, 'hidden_at' => now()]);

        if ($wasReported && $notifyTeacher) {
            $this->notifyTeacher($review, ReviewReportDecided::HIDDEN);
        }
    }

    /** Письмо и уведомление учителю о решении (учителя берём целиком: нужна почта). */
    private function notifyTeacher(Review $review, string $decision): void
    {
        User::find($review->teacher_id)?->notify(new ReviewReportDecided($review, $decision));
    }

    /** «Вернуть отзыв»: снова виден на странице учителя. */
    public function restore(Review $review): void
    {
        $review->update(['is_rejected' => false, 'hidden_at' => null]);
    }

    /**
     * «Отменить» сразу после скрытия: возвращает отзыв в прежнее состояние, включая жалобу.
     *
     * @param  array{is_reported:bool}  $before
     */
    public function undoHide(Review $review, array $before): void
    {
        $review->update(['is_rejected' => false, 'hidden_at' => null, 'is_reported' => (bool) ($before['is_reported'] ?? false)]);
    }
}
