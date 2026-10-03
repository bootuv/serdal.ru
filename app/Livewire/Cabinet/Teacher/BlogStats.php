<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Admin\BlogStats as AdminBlogStats;
use Livewire\Attributes\Layout;

/** Статистика статей учителя: тот же экран, что у админа, но только по его статьям (см. Admin\BlogStats). */
#[Layout('components.layouts.cabinet', ['title' => 'Статистика статей', 'active' => 'blog'])]
class BlogStats extends AdminBlogStats
{
    protected function isTeacher(): bool
    {
        return true;
    }
}
