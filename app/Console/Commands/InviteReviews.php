<?php

namespace App\Console\Commands;

use App\Services\ReviewPromptService;
use Illuminate\Console\Command;

/** Предложить ученикам оставить отзыв об учителе после третьего занятия — один раз на пару. Запуск — раз в день. */
class InviteReviews extends Command
{
    protected $signature = 'reviews:invite';

    protected $description = 'Предложить ученикам оставить отзыв об учителе после третьего занятия';

    public function handle(ReviewPromptService $prompts): int
    {
        $this->info('Приглашений отправлено: ' . $prompts->inviteAfterLessons());

        return self::SUCCESS;
    }
}
