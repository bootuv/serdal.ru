<?php

namespace App\Livewire\Cabinet\Admin;

use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogStatsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Статистика блога: просмотры по дням, источники, лайки и комментарии, самые читаемые статьи (и авторы — у админа).
 * Учитель видит то же по своим статьям (Teacher\BlogStats). Одна статья — ?post=id. Логика — App\Services\BlogStatsService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Статистика блога', 'active' => 'blog'])]
class BlogStats extends Component
{
    #[Url]
    public int $days = 30;

    #[Url]
    public ?int $post = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === ($this->isTeacher() ? User::ROLE_TUTOR : User::ROLE_ADMIN), 403);
        if (! array_key_exists($this->days, BlogStatsService::PERIODS)) {
            $this->days = 30;
        }
        if ($this->post) {
            $this->selected(); // чужая статья учителю — 404
        }
    }

    protected function isTeacher(): bool
    {
        return false;
    }

    private function route(string $name): string
    {
        return ($this->isTeacher() ? 'cabinet.teacher.' : 'cabinet.admin.') . $name;
    }

    public function showPost(?int $id): void
    {
        $this->post = $id;
        $this->selected();
    }

    private function selected(): ?BlogPost
    {
        if (! $this->post) {
            return null;
        }
        $post = BlogPost::published()->findOrFail($this->post);
        abort_if($this->isTeacher() && $post->author_id !== auth()->id(), 404);

        return $post;
    }

    public function render(BlogStatsService $stats)
    {
        $post = $this->selected();
        $report = $stats->report($this->days, $this->isTeacher() ? auth()->user() : null, $post?->id);

        $change = $report['previous'] > 0 ? (int) round(($report['views'] - $report['previous']) / $report['previous'] * 100) : null;

        return view('livewire.cabinet.admin.blog-stats', [
            'report' => $report,
            'selected' => $post,
            'teacher' => $this->isTeacher(),
            'periods' => collect(BlogStatsService::PERIODS)->mapWithKeys(fn ($l, $d) => [(string) $d => $l])->all(),
            'backUrl' => route($this->route('blog')),
            'period' => 'За ' . BlogStatsService::PERIODS[$this->days],
            'change' => match (true) {
                $change === null => null,
                $change === 0 => 'Столько же, сколько за прошлые ' . BlogStatsService::PERIODS[$this->days],
                default => ($change > 0 ? '+' : '−') . abs($change) . '% к прошлым ' . BlogStatsService::PERIODS[$this->days],
            },
            'sourcesTotal' => array_sum(array_column($report['sources'], 'value')),
        ]);
    }
}
