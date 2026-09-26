<?php

namespace App\Filament\Student\Widgets;

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
        $s = app(\App\Services\StudentPerformanceService::class)->stats($student, $teacherId);

        return [
            'attendance' => $s['attendance'],
            'discipline' => $s['discipline'],
            'knowledge' => $s['knowledge'],
        ];
    }
}
