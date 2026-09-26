<?php

namespace App\Services;

use App\Models\Homework;
use App\Models\MeetingSession;
use App\Models\User;

/**
 * Успеваемость ученика у конкретного учителя: посещаемость, задания в срок, качество знаний.
 */
class StudentPerformanceService
{
    /**
     * @return array{
     *   attendance:int, discipline:int, knowledge:int,
     *   lessons_total:int, lessons_attended:int,
     *   homework_total:int, homework_on_time:int, graded_count:int
     * }
     */
    public function stats(User $student, int $teacherId): array
    {
        // 1. Посещаемость: завершённые занятия учителя, где ученик — участник
        $sessions = MeetingSession::query()
            ->where('status', 'completed')
            ->whereHas('room', fn ($q) => $q->where('user_id', $teacherId)
                ->whereHas('participants', fn ($p) => $p->where('users.id', $student->id)))
            ->get();

        $lessonsTotal = $sessions->count();
        $lessonsAttended = $sessions->filter(fn (MeetingSession $s) => $s->attendedBy($student->id))->count();

        // 2. Задания в срок и 3. качество знаний — по видимым ученику заданиям
        $homeworks = Homework::query()
            ->where('teacher_id', $teacherId)
            ->where('is_visible', true)
            ->whereHas('students', fn ($q) => $q->where('users.id', $student->id))
            ->with(['submissions' => fn ($q) => $q->where('student_id', $student->id)])
            ->get();

        $missing = 0;
        // Не сдано, но срок ещё не прошёл — пока не в счёт: ни «в срок», ни «опоздание»
        $pending = 0;
        $gradesSum = 0.0;
        $gradedCount = 0;

        foreach ($homeworks as $homework) {
            $submission = $homework->submissions->first();
            $isSubmitted = $submission && $submission->submitted_at;

            // Вернули на доработку и срок прошёл — считается несданным
            if ($isSubmitted && $submission->status === 'revision_requested' && $homework->is_overdue) {
                $missing++;
                continue;
            }

            if ($isSubmitted) {
                $max = $homework->effective_max_score;
                if ($submission->grade !== null && $max > 0) {
                    $gradesSum += ($submission->grade / $max) * 100;
                    $gradedCount++;
                }
            } elseif ($homework->is_overdue) {
                $missing++;
            } else {
                $pending++;
            }
        }

        $homeworkTotal = $homeworks->count() - $pending;

        return [
            'attendance' => $lessonsTotal > 0 ? (int) round($lessonsAttended / $lessonsTotal * 100) : 0,
            'discipline' => $homeworkTotal > 0 ? (int) round(($homeworkTotal - $missing) / $homeworkTotal * 100) : 0,
            'knowledge' => $gradedCount > 0 ? (int) round($gradesSum / $gradedCount) : 0,
            'lessons_total' => $lessonsTotal,
            'lessons_attended' => $lessonsAttended,
            'homework_total' => $homeworkTotal,
            'homework_pending' => $pending,
            'homework_on_time' => $homeworkTotal - $missing,
            'graded_count' => $gradedCount,
        ];
    }

    /**
     * Метрики для компонента <x-ui.rings>.
     *
     * @return array<int, array{value:int, label:string, sub:string}>
     */
    /** Общая успеваемость — среднее по показателям, где уже есть данные; null — данных нет совсем. */
    public static function overall(array $metrics): ?int
    {
        $values = collect($metrics)->reject(fn (array $m) => $m['empty'] ?? false)->pluck('value');

        return $values->isEmpty() ? null : (int) round($values->avg());
    }

    public function metrics(User $student, int $teacherId): array
    {
        $s = $this->stats($student, $teacherId);

        return [
            ['value' => $s['attendance'], 'label' => 'Посещаемость', 'empty' => ! $s['lessons_total'], 'sub' => $s['lessons_total']
                ? $s['lessons_attended'] . ' из ' . plural_ru($s['lessons_total'], 'занятия', 'занятий', 'занятий')
                : 'занятий ещё не было'],
            ['value' => $s['discipline'], 'label' => 'Задания в срок', 'empty' => ! $s['homework_total'], 'sub' => $s['homework_total']
                ? $s['homework_on_time'] . ' из ' . plural_ru($s['homework_total'], 'задания', 'заданий', 'заданий')
                : (($s['homework_pending'] ?? 0) ? 'сроки сдачи ещё не наступили' : 'заданий ещё не было')],
            ['value' => $s['knowledge'], 'label' => 'Качество знаний', 'empty' => ! $s['graded_count'], 'sub' => $s['graded_count']
                ? 'средний балл за ' . plural_ru($s['graded_count'], 'работу', 'работы', 'работ')
                : 'оценок пока нет'],
        ];
    }
}
