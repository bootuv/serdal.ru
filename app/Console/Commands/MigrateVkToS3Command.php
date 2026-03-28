<?php

namespace App\Console\Commands;

use App\Jobs\MigrateVkRecordingToS3;
use App\Models\Recording;
use Illuminate\Console\Command;

class MigrateVkToS3Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recordings:migrate-vk {--limit= : Limit the number of records to process in one go}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate all old VK videos to S3 via yt-dlp';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding VK recordings that need to be migrated to S3...');

        $query = Recording::whereNotNull('vk_video_url')
            ->whereNull('s3_url');

        $limit = $this->option('limit');
        if ($limit) {
            $query->limit((int)$limit);
        }

        $recordings = $query->get();

        if ($recordings->isEmpty()) {
            $this->info('No recordings found to migrate. Everything is on S3!');
            return 0;
        }

        $this->info("Found {$recordings->count()} recordings to migrate.");
        
        if (!$this->confirm('Do you wish to dispatch jobs for all these recordings?', true)) {
            $this->info('Migration cancelled.');
            return 0;
        }

        $bar = $this->output->createProgressBar($recordings->count());

        foreach ($recordings as $recording) {
            $this->newLine();
            $this->info("   Downloading ID " . $recording->id . "...");
            try {
                MigrateVkRecordingToS3::dispatchSync($recording);
            } catch (\Exception $e) {
                $this->error("Failed on " . $recording->id . ": " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        
        $this->newLine(2);
        $this->info("Successfully processed {$recordings->count()} recordings directly in the terminal.");

        return 0;
    }
}
