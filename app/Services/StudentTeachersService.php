<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use App\Notifications\StudentLeftReview;
use App\Notifications\StudentLeftReviewAdmin;
use App\Notifications\StudentUpdatedReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Учителя ученика (текущие и бывшие), занятия с ними и отзывы.
 * Используется старым кабинетом (виджеты StudentTeachersWidget / StudentFormerTeachersWidget) и новым профилем.
 */
class StudentTeachersService
{
    /** Текущие учителя: связаны с учеником через teacher_student. */
    public function currentTeachers(int $studentId): Builder
    {
        return User::query()->whereHas('students', fn ($q) => $q->where('student_id', $studentId));
    }

    /** Бывшие учителя: были общие занятия, но связи teacher_student больше нет. */
    public function formerTeachers(int $studentId): Builder
    {
        $sid = (string) $studentId;

        return User::query()
            ->whereIn('id', function (QueryBuilder $query) use ($sid) {
                $query->select('rooms.user_id')
                    ->from('rooms')
                    ->join('meeting_sessions', 'meeting_sessions.room_id', '=', 'rooms.id')
                    ->where(function ($q) use ($sid) {
                        $q->whereJsonContains('meeting_sessions.analytics_data->participants', ['user_id' => $sid])
                            ->orWhereJsonContains('meeting_sessions.analytics_data->participants', ['user_id' => (int) $sid]);
                    });
            })
            ->whereNotIn('id', function (QueryBuilder $query) use ($sid) {
                $query->select('teacher_id')
                    ->from('teacher_student')
                    ->where('student_id', $sid);
            });
    }

    /**
     * Занятия ученика с текущим учителем: ученик и учитель (или его аккаунты с той же почтой)
     * были в числе участников.
     */
    public function lessonsWithCurrentTeacher(int $studentId, User $teacher): Collection
    {
        $sid = (string) $studentId;
        $teacherIds = User::where('email', $teacher->email)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $teacherIds[] = (string) $teacher->id;
        $teacherIds = array_unique($teacherIds);

        return MeetingSession::query()
            ->where(function ($q) use ($sid) {
                $q->whereJsonContains('analytics_data->participants', ['user_id' => $sid])
                    ->orWhereJsonContains('analytics_data->participants', ['user_id' => (int) $sid]);
            })
            ->get()
            ->filter(function (MeetingSession $session) use ($teacherIds) {
                $participants = $session->analytics_data['participants'] ?? [];
                if (! is_array($participants)) {
                    return false;
                }
                foreach ($participants as $p) {
                    if (isset($p['user_id']) && in_array((string) $p['user_id'], $teacherIds)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /** Занятия ученика с бывшим учителем: занятия в комнатах учителя, где ученик был участником. */
    public function lessonsWithFormerTeacher(int $studentId, User $teacher): Collection
    {
        return MeetingSession::query()
            ->whereHas('room', fn ($query) => $query->where('user_id', $teacher->id))
            ->get()
            ->filter(function (MeetingSession $session) use ($studentId) {
                $participants = $session->analytics_data['participants'] ?? [];
                if (! is_array($participants)) {
                    return false;
                }
                foreach ($participants as $p) {
                    if (isset($p['user_id']) && $p['user_id'] == $studentId) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /** Ссылка «Написать учителю»: личный чат или чат индивидуального занятия (см. MessengerService::chatUrlWith). */
    public function chatUrl(int $studentId, int $teacherId): string
    {
        return app(MessengerService::class)->chatUrlWith(User::findOrFail($studentId), $teacherId);
    }

    /** Отзыв ученика об учителе (один на пару ученик–учитель). */
    public function review(int $studentId, int $teacherId): ?Review
    {
        return Review::where('user_id', $studentId)->where('teacher_id', $teacherId)->first();
    }

    /** Отзыв отклонён модератором — новый оставить нельзя. */
    public function hasRejectedReview(int $studentId, int $teacherId): bool
    {
        return Review::where('user_id', $studentId)
            ->where('teacher_id', $teacherId)
            ->where('is_rejected', true)
            ->exists();
    }

    /** Можно ли оставить/изменить отзыв: было хотя бы одно занятие и отзыв не отклонён. */
    public function canReview(int $studentId, int $teacherId, int $lessonsCount): bool
    {
        return $lessonsCount > 0 && ! $this->hasRejectedReview($studentId, $teacherId);
    }

    /**
     * Сохранить отзыв (создать или обновить). О новом отзыве узнают учитель и админы.
     * Изменённый отзыв снова становится новым для учителя (он получает уведомление), а жалоба
     * на прежний текст снимается — модератор проверяет уже другой текст.
     */
    public function saveReview(User $student, User $teacher, int $rating, string $text): Review
    {
        $review = Review::firstOrNew(['user_id' => $student->id, 'teacher_id' => $teacher->id]);
        $isNew = ! $review->exists;
        $textChanged = $isNew || trim((string) $review->text) !== trim($text);

        $review->fill(['rating' => $rating, 'text' => $text]);

        if ($isNew) {
            $review->save();

            $teacher->notify(new StudentLeftReview($review, $student));

            foreach (User::where('role', User::ROLE_ADMIN)->get() as $admin) {
                $admin->notify(new StudentLeftReviewAdmin($review, $student, $teacher));
            }

            return $review;
        }

        if (! $review->isDirty(['rating', 'text'])) {
            return $review;
        }

        $review->teacher_read_at = null;

        if ($textChanged && $review->is_reported) {
            $review->fill(['is_reported' => false, 'report_reason' => null, 'report_note' => null, 'reported_at' => null]);
        }

        $review->save();
        $teacher->notify(new StudentUpdatedReview($review, $student));

        return $review;
    }
}
