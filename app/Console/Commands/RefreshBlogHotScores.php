<?php

namespace App\Console\Commands;

use App\Services\BlogService;
use Illuminate\Console\Command;

/** «Популярные» в блоге: вес статьи падает с возрастом — пересчитываем раз в час (BlogService::hotScore). */
class RefreshBlogHotScores extends Command
{
    protected $signature = 'blog:hot';

    protected $description = 'Пересчитать вес статей блога для вкладки «Популярные»';

    public function handle(BlogService $blog): int
    {
        $this->info('Пересчитано статей: ' . $blog->refreshAllHotScores());

        return self::SUCCESS;
    }
}
