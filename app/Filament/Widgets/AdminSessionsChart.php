<?php

namespace App\Filament\Widgets;

use App\Models\MeetingSession;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class AdminSessionsChart extends ChartWidget
{
    use InteractsWithPageFilters; // This trait allows access to $this->filters

    public function getHeading(): \Illuminate\Contracts\Support\Htmlable|string
    {
        $teacherIds = $this->filters['teacher_ids'] ?? [];

        $activeCount = \App\Models\Room::where('is_running', true)
            ->when(!empty($teacherIds), fn($q) => $q->whereIn('user_id', $teacherIds))
            ->count();

        return new \Illuminate\Support\HtmlString(
            view('filament.widgets.partials.admin-sessions-chart-heading', ['count' => $activeCount])->render()
        );
    }

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $period = $this->filters['period'] ?? 'week';
        $now = now();
        $endDate = $now->copy()->endOfDay();

        $startDate = match ($period) {
            'day' => $now->copy()->subDay()->startOfDay(),
            'week' => $now->copy()->subWeek()->startOfDay(),
            'month' => $now->copy()->subMonth()->startOfDay(),
            'quarter' => $now->copy()->subQuarter()->startOfDay(),
            'year' => $now->copy()->subYear()->startOfDay(),
            default => $now->copy()->subWeek()->startOfDay(),
        };

        $teacherIds = $this->filters['teacher_ids'] ?? [];
        $datasets = [];
        $labels = [];

        // Generate labels (dates)
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $labels[] = $current->format('d.m.Y');
            $current->addDay();
        }

        if (empty($teacherIds)) {
            // Show aggregate for all teachers
            $data = $this->queryData(null, $startDate, $endDate);
            $datasets[] = [
                'label' => 'Все сессии',
                'data' => $data,
                'borderColor' => '#f59e0b', // Amber-500
                'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                'fill' => true,
            ];
        } else {
            // Show individual line for each selected teacher
            foreach ($teacherIds as $teacherId) {
                $teacher = \App\Models\User::find($teacherId);
                if (!$teacher)
                    continue;

                $data = $this->queryData($teacherId, $startDate, $endDate);
                $color = $teacher->avatar_text_color ?? '#f59e0b';

                $datasets[] = [
                    'label' => trim("{$teacher->last_name} {$teacher->first_name}") ?: $teacher->name,
                    'data' => $data,
                    'borderColor' => $color,
                    'backgroundColor' => 'transparent', // No fill for multiple lines to avoid clutter
                    'fill' => false,
                ];
            }
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function queryData(?int $teacherId, Carbon $startDate, Carbon $endDate): array
    {
        $query = MeetingSession::query()
            ->whereBetween('started_at', [$startDate->startOfDay(), $endDate->endOfDay()]);

        if ($teacherId) {
            $query->whereHas('room', function ($q) use ($teacherId) {
                $q->where('user_id', $teacherId);
            });
        }

        $data = $query->selectRaw('DATE(started_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Fill zeros
        $chartData = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $chartData[] = $data[$dateKey] ?? 0;
            $current->addDay();
        }

        return $chartData;
    }

    protected function getType(): string
    {
        return 'line';
    }
}
