<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewInvite;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Приглашения ученику оставить отзыв об учителе. Состояние — в строке teacher_student.
 * - Главная ученика: карточка «Как вам занятия?» после FIRST_AFTER_LESSONS занятий. «Позже» прячет её
 *   на AGAIN_AFTER_LESSONS занятий; после MAX_DISMISSALS закрытий сама она больше не появляется.
 * - Уведомление ученику — один раз, когда занятий стало FIRST_AFTER_LESSONS (команда reviews:invite).
 * - Учитель может попросить отзыв сам — не чаще раза в REQUEST_EVERY_DAYS дней на ученика; просьба
 *   показывает карточку снова, даже если ученик её уже закрывал.
 */
class ReviewPromptService
{
    public const FIRST_AFTER_LESSONS = 3;

    public const AGAIN_AFTER_LESSONS = 5;

    public const MAX_DISMISSALS = 2;

    public const REQUEST_EVERY_DAYS = 30;

    public function __construct(private StudentTeachersService $teachers) {}

    /** Строки teacher_student ученика по id учителя. */
    public function pivotsForStudent(int $studentId): Collection
    {
        return DB::table('teacher_student')->where('student_id', $studentId)->get()->keyBy('teacher_id');
    }

    private function pivot(int $teacherId, int $studentId): ?object
    {
        return DB::table('teacher_student')->where('teacher_id', $teacherId)->where('student_id', $studentId)->first();
    }

    private function pivotUpdate(int $teacherId, int $studentId, array $values): void
    {
        DB::table('teacher_student')->where('teacher_id', $teacherId)->where('student_id', $studentId)->update($values);
    }

    /** Учитель попросил отзыв, и ученик после этого карточку не закрывал. */
    public static function requested(object $pivot): bool
    {
        if (! $pivot->review_requested_at) {
            return false;
        }

        return ! $pivot->review_prompt_dismissed_at
            || Carbon::parse($pivot->review_requested_at)->gt(Carbon::parse($pivot->review_prompt_dismissed_at));
    }

    /** Показать ученику карточку «Как вам занятия?» по этому учителю. */
    public static function shouldPrompt(object $pivot, int $lessons, bool $hasReview, bool $canReview): bool
    {
        if ($hasReview || ! $canReview) {
            return false;
        }

        if (self::requested($pivot)) {
            return true;
        }

        return $lessons >= self::FIRST_AFTER_LESSONS
            && (int) $pivot->review_prompt_dismissals < self::MAX_DISMISSALS
            && $lessons >= (int) $pivot->review_prompt_resume_lessons;
    }

    /** «Позже»: спрятать карточку до следующих AGAIN_AFTER_LESSONS занятий. */
    public function dismiss(int $studentId, int $teacherId, int $lessons): void
    {
        $pivot = $this->pivot($teacherId, $studentId);
        if (! $pivot) {
            return;
        }

        $this->pivotUpdate($teacherId, $studentId, [
            'review_prompt_dismissals' => min(255, (int) $pivot->review_prompt_dismissals + 1),
            'review_prompt_dismissed_at' => now(),
            'review_prompt_resume_lessons' => $lessons + self::AGAIN_AFTER_LESSONS,
        ]);
    }

    /**
     * Можно ли учителю попросить отзыв у ученика.
     *
     * @return array{review: ?Review, rejected: bool, lessons: int, requestedAt: ?Carbon, can: bool}
     */
    public function requestState(User $teacher, User $student): array
    {
        $review = $this->teachers->review($student->id, $teacher->id);
        $rejected = (bool) $review?->is_rejected;
        $pivot = $this->pivot($teacher->id, $student->id);
        $requestedAt = $pivot?->review_requested_at ? Carbon::parse($pivot->review_requested_at) : null;
        $lessons = $review || ! $pivot ? 0 : $this->teachers->lessonsWithCurrentTeacher($student->id, $teacher)->count();

        return [
            'review' => $review,
            'rejected' => $rejected,
            'lessons' => $lessons,
            'requestedAt' => $requestedAt,
            'can' => $pivot && ! $review && $lessons > 0
                && (! $requestedAt || $requestedAt->lt(now()->subDays(self::REQUEST_EVERY_DAYS))),
        ];
    }

    /** «Попросить отзыв» у одного ученика. false — просить нельзя (нет занятий, отзыв уже есть, просили недавно). */
    public function request(User $teacher, User $student): bool
    {
        if (! $this->requestState($teacher, $student)['can']) {
            return false;
        }

        $this->pivotUpdate($teacher->id, $student->id, ['review_requested_at' => now()]);
        $student->notify(new ReviewInvite($teacher, byTeacher: true));

        return true;
    }

    /** Ученики, у которых учитель может попросить отзыв прямо сейчас. */
    public function askable(User $teacher): Collection
    {
        $reviewed = Review::where('teacher_id', $teacher->id)->pluck('user_id');

        return $teacher->students()
            ->where('users.role', User::ROLE_STUDENT)
            ->whereNotIn('users.id', $reviewed)
            ->where(fn ($q) => $q->whereNull('teacher_student.review_requested_at')
                ->orWhere('teacher_student.review_requested_at', '<', now()->subDays(self::REQUEST_EVERY_DAYS)))
            ->get()
            ->filter(fn (User $s) => $this->teachers->lessonsWithCurrentTeacher($s->id, $teacher)->isNotEmpty())
            ->values();
    }

    /** «Попросить учеников об отзыве»: всем, кого можно. Возвращает, скольких попросили. */
    public function requestAll(User $teacher): int
    {
        return $this->askable($teacher)->filter(fn (User $s) => $this->request($teacher, $s))->count();
    }

    /** Уведомление ученику после третьего занятия (один раз на пару). Возвращает, скольким отправлено. */
    public function inviteAfterLessons(): int
    {
        $sent = 0;

        DB::table('teacher_student')
            ->whereNull('review_prompt_notified_at')
            ->whereNull('review_requested_at')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('reviews')
                ->whereColumn('reviews.user_id', 'teacher_student.student_id')
                ->whereColumn('reviews.teacher_id', 'teacher_student.teacher_id'))
            // lazyById, а не each: строки меняются по ходу, и обход по страницам со смещением пропускал бы их
            ->lazyById()
            ->each(function (object $pivot) use (&$sent) {
                $student = User::where('role', User::ROLE_STUDENT)->find($pivot->student_id);
                $teacher = User::find($pivot->teacher_id);
                if (! $student || ! $teacher) {
                    return;
                }

                if ($this->teachers->lessonsWithCurrentTeacher($student->id, $teacher)->count() < self::FIRST_AFTER_LESSONS) {
                    return;
                }

                $this->pivotUpdate($teacher->id, $student->id, ['review_prompt_notified_at' => now()]);
                $student->notify(new ReviewInvite($teacher, byTeacher: false));
                $sent++;
            });

        return $sent;
    }
}
