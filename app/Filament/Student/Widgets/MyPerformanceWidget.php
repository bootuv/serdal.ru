<?php

namespace App\Filament\Student\Widgets;

use App\Models\Homework;
use App\Models\MeetingSession;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

class MyPerformanceWidget extends ChartWidget
{
    protected static ?string $heading = 'Моя успеваемость';

    protected static string $view = 'filament.student.widgets.my-performance-widget';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 0;

    public ?int $selectedTeacherId = null;

    protected function getListeners(): array
    {
        return [
            'selectTeacher' => 'setTeacher',
        ];
    }

    public function setTeacher(?int $teacherId): void
    {
        $this->selectedTeacherId = $teacherId;
    }

    public function getTeachers(): Collection
    {
        /** @var User $student */
        $student = auth()->user();

        return $student->teachers()->get();
    }

    protected function getData(): array
    {
        /** @var User $student */
        $student = auth()->user();
        $teacherId = $this->selectedTeacherId;

        if (!$teacherId) {
            // Default to first teacher
            $firstTeacher = $this->getTeachers()->first();
            $teacherId = $firstTeacher?->id;
        }

        if (!$teacherId) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $stats = $this->calculateStats($student, $teacherId);
        $offset = 40;
        $minVisible = 70;

        $attendance = $stats['attendance'] > 0 ? $stats['attendance'] + $offset : $minVisible;
        $discipline = $stats['discipline'] > 0 ? $stats['discipline'] + $offset : $minVisible;
        $knowledge = $stats['knowledge'] > 0 ? $stats['knowledge'] + $offset : $minVisible;

        return [
            'datasets' => [
                [
                    'label' => 'Успеваемость',
                    'data' => [
                        $attendance,
                        $discipline,
                        $knowledge,
                    ],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                    ],
                    'borderColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => ['Посещаемость', 'Дисциплина (ДЗ)', 'Качество знаний'],
        ];
    }

    protected function getType(): string
    {
        return 'polarArea';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'r' => [
                    'min' => 40,
                    'max' => 140,
                    'ticks' => ['display' => false],
                    'grid' => ['display' => true, 'color' => 'rgba(0, 0, 0, 0.05)'],
                    'angleLines' => ['display' => false],
                    'pointLabels' => ['display' => false],
                ],
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
            'maintainAspectRatio' => true,
            'aspectRatio' => 1,
            'layout' => ['padding' => 0],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) { return context.label + ': ' + Math.round(context.raw - 40) + '%'; }",
                    ],
                ],
            ],
        ];
    }

    protected function calculateStats(User $student, int $teacherId): array
    {
        // 1. Attendance
        $sessions = MeetingSession::whereHas('room', function ($q) use ($teacherId) {
            $q->where('user_id', $teacherId);
        })
            ->where('status', 'completed')
            ->get();

        $totalSessions = 0;
        $attendedSessions = 0;

        foreach ($sessions as $session) {
            $room = $session->room;
            if (!$room || !$room->participants->contains($student->id)) {
                continue;
            }

            $totalSessions++;

            $attended = false;
            $studentIdStr = (string) $student->id;

            if (isset($session->pricing_snapshot['participants'])) {
                foreach ($session->pricing_snapshot['participants'] as $p) {
                    if (($p['user_id'] ?? '') == $studentIdStr && ($p['attended'] ?? false)) {
                        $attended = true;
                        break;
                    }
                }
            } else {
                $analytics = $session->analytics_data ?? [];
                $participants = $analytics['participants'] ?? [];
                foreach ($participants as $p) {
                    if (($p['user_id'] ?? '') == $studentIdStr) {
                        $attended = true;
                        break;
                    }
                }
            }

            if ($attended) {
                $attendedSessions++;
            }
        }

        $attendanceRate = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

        // 2. Discipline (Homework Submission Rate)
        $homeworks = Homework::where('teacher_id', $teacherId)
            ->whereHas('students', function ($q) use ($student) {
                $q->where('users.id', $student->id);
            })
            ->with([
                'submissions' => function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                }
            ])
            ->get();

        $totalHomeworks = $homeworks->count();
        $missingHomeworks = 0;
        $gradesSum = 0;
        $gradesCount = 0;

        foreach ($homeworks as $homework) {
            $submission = $homework->submissions->first();
            $isSubmitted = $submission && $submission->submitted_at;

            // If submission exists but status is 'revision_requested' AND it is overdue, it counts as missing
            if ($isSubmitted && $submission->status === 'revision_requested' && $homework->is_overdue) {
                $missingHomeworks++;
                continue;
            }

            if ($isSubmitted) {
                if ($submission->grade !== null) {
                    $max = $homework->effective_max_score;
                    if ($max > 0) {
                        $gradesSum += ($submission->grade / $max) * 100;
                        $gradesCount++;
                    }
                }
            } elseif ($homework->is_overdue) {
                // If not submitted AND overdue -> missing
                $missingHomeworks++;
            }
        }

        $disciplineRate = $totalHomeworks > 0 ? (($totalHomeworks - $missingHomeworks) / $totalHomeworks) * 100 : 0;

        // 3. Knowledge Quality (Average Grade)
        $knowledgeRate = $gradesCount > 0 ? ($gradesSum / $gradesCount) : 0;

        return [
            'attendance' => round($attendanceRate),
            'discipline' => round($disciplineRate),
            'knowledge' => round($knowledgeRate),
        ];
    }
}
