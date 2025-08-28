<?php
namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class InsertSoundFiles extends Command
{
    protected $signature   = 'insert:sound-files';
    protected $description = 'Upsert qualifying WAV files into sales_call_ratings with date parts and role';

    /**
     * Parse employee from a phonestring, return [name, role]
     */
    public function findEmployee(string $phonestring): array
    {
        // id => [name, role]
        $employees = [
            1001 => ['Deniz Isikli', 'Sales'], // Senior Key Account
            1004 => ['Rasmus Detlefsen', 'CS'],
            1007 => ['Mikkel Bonde', 'CS'],
            1151 => ['Bilal S', 'Sales'],
            1183 => ['Sune Kidmose', 'Sales'],
            1184 => ['Sebastian S', 'Sales'],
            1191 => ['Minh Lam', 'CS'],
            1212 => ['Kristian Rogvi', 'Sales'],
            1213 => ['Thorsten Olsen', 'Admin'],
            8001 => ['Nicolai Stenius', 'Sales'], // Senior Key Account
            8002 => ['Admin', 'Admin'],
            8003 => ['SK (CEO)', 'Admin'],
            8006 => ['Frederic Nygaard', 'Sales'],
            8501 => ['Sascha Saxo Larsen', 'CS'],
            8502 => ['Nikola Sulejic', 'CS'],
            8504 => ['Morten Hyldgaard', 'CS'],
        ];

        // Grab all dash-delimited numbers, prefer the last one that exists in the map
        if (preg_match_all('/-(\d{3,10})-/', $phonestring, $matches)) {
            $candidates = array_map('intval', $matches[1]);
            for ($i = count($candidates) - 1; $i >= 0; $i--) {
                $id = $candidates[$i];
                if (isset($employees[$id])) {
                    return ['name' => $employees[$id][0], 'role' => $employees[$id][1]];
                }
            }
        }

        // Fallback, trailing digits after a dash
        if (preg_match('/-(\d{3,5})(?:\D|$)/', $phonestring, $m)) {
            $id = (int) $m[1];
            if (isset($employees[$id])) {
                return ['name' => $employees[$id][0], 'role' => $employees[$id][1]];
            }
        }

        return ['name' => 'ukendt', 'role' => 'ukendt'];
    }

    public function handle(): int
    {
        $directory = 'C:\\SOUNDS';

        if (! is_dir($directory)) {
            $this->error("Directory not found: {$directory}");
            return 1;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        // Do not truncate when upserting
        $batch   = [];
        $chunkSz = 500;

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'wav') {
                continue;
            }

            $path      = $file->getPathname();
            $duration  = $this->getWavDuration($path);
            $callId    = pathinfo($path, PATHINFO_FILENAME);
            $who       = $this->findEmployee($path);
            $dateParts = $this->extractDateParts($path);

            // Optional filter, skip corrupt or tiny files if you want
            // if ($duration !== null && $duration < 2) continue;

            print("\n {$who['name']} {$duration} {$path}");

            $batch[] = [
                'filepath'    => $path,
                'call_id'     => $callId,
                'length_sec'  => $duration,
                'caller_name' => $who['name'],
                'Dept'        => $who['role'],
                'day'         => $dateParts['day'],
                'month'       => $dateParts['month'],
                'year'        => $dateParts['year'],
            ];

            $this->upsertBatch($batch);
            $batch = [];

        }

        $this->info("\nUpsert of sound files completed");
        return 0;
    }

    /**
     * Batch upsert using call_id as the conflict key.
     * Make sure there is a unique index on call_id in sales_call_ratings.
     */
    private function upsertBatch(array $rows): void
    {
        DB::table('sales_call_ratings')->upsert(
            $rows,
            ['call_id'], // or ['filepath'] if that is your unique column
            ['filepath', 'length_sec', 'caller_name', 'Dept', 'day', 'month', 'year']
        );
    }

    /**
     * Extract YYYY, MM, DD from folder components like ...\2025\08\11\...
     */
    private function extractDateParts(string $path): array
    {
        $norm = str_replace('\\', '/', $path);
        if (preg_match('~/(\d{4})/(\d{2})/(\d{2})/~', $norm, $m)) {
            return [
                'year'  => (int) $m[1],
                'month' => (int) $m[2],
                'day'   => (int) $m[3],
            ];
        }

        // Secondary fallback, try to parse YYYYMMDD inside filename if present
        // Example: exten-8502-unknown-20250811-131146-....wav
        if (preg_match('/-(\d{4})(\d{2})(\d{2})-/', $norm, $m)) {
            return [
                'year'  => (int) $m[1],
                'month' => (int) $m[2],
                'day'   => (int) $m[3],
            ];
        }

        return ['year' => null, 'month' => null, 'day' => null];
    }

    /**
     * Lightweight WAV duration reader. Returns seconds as float or null.
     */
    private function getWavDuration(string $file): ?float
    {
        try {

            $handle = @fopen($file, 'rb');
            if ($handle === false) {
                return null;
            }

            // Minimal RIFF parsing
            fseek($handle, 22);
            $channels   = unpack('v', fread($handle, 2))[1] ?? 0;
            $sampleRate = unpack('V', fread($handle, 4))[1] ?? 0;
            fseek($handle, 34);
            $bitsPerSample = unpack('v', fread($handle, 2))[1] ?? 0;

            // Find data chunk size, simple fast path
            fseek($handle, 40);
            $dataSize = unpack('V', fread($handle, 4))[1] ?? 0;

            fclose($handle);

            if ($channels === 0 || $bitsPerSample === 0 || $sampleRate === 0) {
                return null;
            }

            $bytesPerSample = ($bitsPerSample / 8) * $channels;
            if ($bytesPerSample <= 0) {
                return null;
            }

            return $dataSize / ($sampleRate * $bytesPerSample);
        } catch (\Throwable $e) {
            return 0;
        }

    }
}
