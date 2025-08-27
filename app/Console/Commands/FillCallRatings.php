<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FillCallRatings extends Command
{
    protected $signature = 'fill:call-ratings';
    protected $description = 'Update rating fields from WisperTALK transcripts';

    public function handle(): int
    {
        require_once base_path('script/fill_call_ratings.php');
        try {
            $pdo = create_pdo_from_env();
        } catch (\PDOException $e) {
            $this->error('Database connection failed: '.$e->getMessage());
            return 1;
        }
        $updated = process_missing_ratings($pdo, 'openai_evaluate');
        $this->info('Updated '.$updated.' record(s)');
        return 0;
    }
}
