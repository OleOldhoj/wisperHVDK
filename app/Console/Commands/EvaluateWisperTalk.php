<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EvaluateWisperTalk extends Command
{
    protected $signature = 'evaluate:wispertalk {limit=10}';
    protected $description = 'Send WisperTALK transcripts to the assistant and print ratings';

    public function handle(): int
    {
        require_once base_path('script/evaluate_wispertalk.php');
        try {
            $pdo = create_pdo_from_env();
        } catch (\PDOException $e) {
            $this->error('Database connection failed: ' . $e->getMessage());
            return 1;
        }

        $limit = (int) $this->argument('limit');
        $processed = evaluate_wispertalk($pdo, 'openai_evaluate', $limit);
        $this->info('Processed ' . $processed . ' record(s)');
        return 0;
    }
}
