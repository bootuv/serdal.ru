<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\TeacherBlogDemo;
use App\Demo\Screen;
use App\Services\BlogStatsService;

/**
 * «Статистика статей» — App\Livewire\Cabinet\Teacher\BlogStats (шаблон админского экрана с teacher = true).
 * Состояние: ?days=7|30|90, ?post=<id> — одна статья.
 */
class BlogStats extends Screen
{
    use TeacherBlogDemo;

    public const PATH = 'blog/stats';

    public const EXAMPLES = ['blog/stats', 'blog/stats?days=7', 'blog/stats?days=90', 'blog/stats?post=301'];

    public string $view = 'livewire.cabinet.admin.blog-stats';

    public string $title = 'Статистика статей';

    public ?string $active = 'blog';

    public function actions(): array
    {
        return [
            'showPost' => ['set' => ['post' => '{0}']],
        ];
    }

    public function data(): array
    {
        $days = $this->state('days', 30);
        if (! array_key_exists($days, BlogStatsService::PERIODS)) {
            $days = 30;
        }
        $postId = $this->state('post', 0) === self::BLOG_PUBLISHED ? self::BLOG_PUBLISHED : null;
        $report = self::blogReport($days, $postId);
        $change = $report['previous'] > 0 ? (int) round(($report['views'] - $report['previous']) / $report['previous'] * 100) : null;

        return [
            'report' => $report,
            'selected' => $postId ? self::blogPost($postId) : null,
            'teacher' => true,
            'periods' => collect(BlogStatsService::PERIODS)->mapWithKeys(fn ($l, $d) => [(string) $d => $l])->all(),
            'backUrl' => route('cabinet.teacher.blog'),
            'period' => 'За ' . BlogStatsService::PERIODS[$days],
            'change' => match (true) {
                $change === null => null,
                $change === 0 => 'Столько же, сколько за прошлые ' . BlogStatsService::PERIODS[$days],
                default => ($change > 0 ? '+' : '−') . abs($change) . '% к прошлым ' . BlogStatsService::PERIODS[$days],
            },
            'sourcesTotal' => array_sum(array_column($report['sources'], 'value')),
            // Публичные свойства компонента
            'days' => $days,
            'post' => $postId,
        ];
    }
}
