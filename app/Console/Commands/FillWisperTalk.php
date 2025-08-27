<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FillWisperTalk extends Command
{
    protected $signature = 'fill:wispertalk';
    protected $description = 'Transcribe audio files and populate WisperTALK for Sales department';

    public function handle(): int
    {
        require_once base_path('app/Support/WisperTalk.php');
        require_once base_path('script/db.php');
        require_once base_path('public_html/openai_transcribe.php');

        try {
            $pdo = create_pdo_from_env();
        } catch (\PDOException $e) {
            $this->error('Database connection failed: ' . $e->getMessage());
            return 1;
        }

        $updated = process_missing_transcriptions($pdo, 'openai_transcribe');
        $this->info('Updated ' . $updated . ' record(s)');
        return 0;
    }
}
