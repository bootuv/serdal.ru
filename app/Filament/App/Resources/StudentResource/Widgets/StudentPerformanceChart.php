<?php

namespace App\Filament\App\Resources\StudentResource\Widgets;

use App\Models\Homework;
use App\Models\MeetingSession;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class StudentPerformanceChart extends ChartWidget
{
    protected static ?string $heading = 'Успеваемость';

    protected static string $view = 'filament.app.resources.student-resource.widgets.student-performance-chart';

    public ?Model $record = null;

    protected function getData(): array
    {
        /** @var User $student */
        $student = $this->record;

        if (!$student) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $stats = $this->calculateStats($student);

        return [
            'datasets' => [
                [
                    'label' => 'Успеваемость',
                    'data' => [
                        $stats['attendance'],
                        $stats['discipline'],
                        $stats['knowledge'],
                    ],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)', // Blue (Attendance)
                        'rgba(16, 185, 129, 0.8)', // Emerald (Discipline)
                        'rgba(245, 158, 11, 0.8)', // Amber (Knowledge)
                    ],
                    'borderColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                    ],
                    'borderWidth' => 0, // No borders looks cleaner like the reference
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
                    'min' => 0,
                    'max' => 100,
                    'ticks' => [
                        'display' => false, // No numbers
                    ],
                    'grid' => [
                        'display' => true, // Circular grid (rings) ON
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                    'angleLines' => [
                        'display' => false, // No spokes
                    ],
                    'pointLabels' => [
                        'display' => false, // Hide labels on the chart (we have legend below)
                    ],
                ],
                'x' => [
                    'display' => false, // Ensure no X axis
                ],
                'y' => [
                    'display' => false, // Ensure no Y axis
                ],
            ],
            'maintainAspectRatio' => true,
            'aspectRatio' => 1,
            'layout' => [
                'padding' => 0,
            ],
            'plugins' => [
                'legend' => [
                    'display' => false, // Hide internal legend to keep chart centered
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) { return context.label + ': ' + Math.round(context.raw) + '%'; }",
                    ],
                ],
            ],
            'cutout' => '30%', // Make space in center? PolarArea doesn't support cutout natively like Doughnut.
            // But we can just overlay.
        ];
    }

    protected function calculateStats(User $student): array
    {
        $teacherId = auth()->id();

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
            if (!$room || !$room->participants->contains($student->id))
                isset($room); // Keep logic same as ViewStudent

            // Assuming simple check: if student is participant of room, count session
            if ($room && $room->participants->contains($student->id)) {
                $totalSessions++;

                // Check attendance
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

                if ($attended)
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
        $submittedHomeworks = 0;
        $gradesSum = 0;
        $gradesCount = 0;

        foreach ($homeworks as $homework) {
            $submission = $homework->submissions->first();
            if ($submission && $submission->submitted_at) {
                $submittedHomeworks++;

                if ($submission->grade !== null) {
                    $max = $homework->effective_max_score;
                    if ($max > 0) {
                        $gradesSum += ($submission->grade / $max) * 100;
                        $gradesCount++;
                    }
                }
            }
        }

        $disciplineRate = $totalHomeworks > 0 ? ($submittedHomeworks / $totalHomeworks) * 100 : 0;

        // 3. Knowledge Quality (Average Grade)
        $knowledgeRate = $gradesCount > 0 ? ($gradesSum / $gradesCount) : 0;

        return [
            'attendance' => round($attendanceRate),
            'discipline' => round($disciplineRate),
            'knowledge' => round($knowledgeRate),
        ];
    }
}
