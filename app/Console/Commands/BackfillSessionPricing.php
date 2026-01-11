<?php

namespace App\Console\Commands;

use App\Models\MeetingSession;
use Illuminate\Console\Command;

class BackfillSessionPricing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:backfill-pricing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill pricing_snapshot for old sessions that do not have it';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sessions = MeetingSession::query()
            ->whereNull('pricing_snapshot')
            ->with(['room.participants', 'room.user.lessonTypes'])
            ->get();

        $count = $sessions->count();
        $this->info("Found {$count} sessions without pricing_snapshot.");

        if ($count === 0) {
            $this->info('Nothing to do.');
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $updated = 0;
        foreach ($sessions as $session) {
            $snapshot = $session->capturePricingSnapshot();

            if (!empty($snapshot)) {
                $session->update(['pricing_snapshot' => $snapshot]);
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated {$updated} sessions with pricing_snapshot.");

        return 0;
    }
}
