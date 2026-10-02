<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Admin\BlogArticle as AdminBlogArticle;
use Livewire\Attributes\Layout;

/** Статья учителя в блоге: тот же экран, что у админа, но учитель отправляет статью на проверку (см. Admin\BlogArticle). */
#[Layout('components.layouts.cabinet', ['title' => 'Статья', 'active' => 'blog'])]
class BlogArticle extends AdminBlogArticle
{
    protected function isTeacher(): bool
    {
        return true;
    }
}
