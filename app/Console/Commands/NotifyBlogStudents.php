<?php

namespace App\Console\Commands;

use App\Services\BlogService;
use Illuminate\Console\Command;

/** Статьи учителей по расписанию: вышла — сообщить ученикам автора (BlogService::notifyStudents). */
class NotifyBlogStudents extends Command
{
    protected $signature = 'blog:notify';

    protected $description = 'Сообщить ученикам о вышедших статьях их учителей';

    public function handle(BlogService $blog): int
    {
        $n = $blog->notifyDue();
        if ($n) {
            $this->info("Разослано о статьях: {$n}");
        }

        return self::SUCCESS;
    }
}
