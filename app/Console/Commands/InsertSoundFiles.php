<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

class InsertSoundFiles extends Command
{
    protected $signature = 'insert:sound-files {directory? : Directory to scan for WAV files}';
    protected $description = 'Insert qualifying WAV files into sales_call_ratings';

    public function handle(): int
    {
        $directory = $this->argument('directory') ?? 'C:\\wisper\\sound';

        if (!is_dir($directory)) {
            $this->error("Directory not found: {$directory}");
            return 1;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'wav') {
                continue;
            }

            $path = $file->getPathname();
            $duration = $this->getWavDuration($path);

            if ($duration !== null && $duration < 120) {
                $callId = pathinfo($path, PATHINFO_FILENAME);
                try {
                    DB::table('sales_call_ratings')->insert([
                        'filepath' => $path,
                        'call_id' => $callId,
                        'greeting_quality' => 0,
                        'needs_assessment' => 0,
                        'product_knowledge' => 0,
                        'persuasion' => 0,
                        'closing' => 0,
                    ]);
                } catch (\Throwable $e) {
                    $this->warn("Failed to insert {$path}: " . $e->getMessage());
                }
            }
        }

        $this->info('Insert sound files completed');
        return 0;
    }

    private function getWavDuration(string $file): ?float
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return null;
        }

        fseek($handle, 22);
        $channels = unpack('v', fread($handle, 2))[1] ?? 0;
        $sampleRate = unpack('V', fread($handle, 4))[1] ?? 0;
        fseek($handle, 34);
        $bitsPerSample = unpack('v', fread($handle, 2))[1] ?? 0;
        fseek($handle, 40);
        $dataSize = unpack('V', fread($handle, 4))[1] ?? 0;
        fclose($handle);

        if ($channels === 0 || $bitsPerSample === 0 || $sampleRate === 0) {
            return null;
        }

        $bytesPerSample = ($bitsPerSample / 8) * $channels;
        return $dataSize / ($sampleRate * $bytesPerSample);
    }
}
