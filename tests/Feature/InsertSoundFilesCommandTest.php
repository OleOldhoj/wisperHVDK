<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InsertSoundFilesCommandTest extends TestCase
{
    public function testInsertsShortWavFiles(): void
    {
        $dir = sys_get_temp_dir().'/wavtest'.uniqid();
        mkdir($dir);
        $path = $dir.'/test.wav';
        file_put_contents($path, $this->generateWav(1));

        DB::statement('CREATE TABLE sales_call_ratings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filepath TEXT,
            call_id TEXT,
            greeting_quality INTEGER,
            needs_assessment INTEGER,
            product_knowledge INTEGER,
            persuasion INTEGER,
            closing INTEGER
        )');

        Artisan::call('insert:sound-files', ['directory' => $dir]);

        $this->assertDatabaseHas('sales_call_ratings', [
            'filepath' => $path,
            'call_id' => 'test',
        ]);
    }

    private function generateWav(int $seconds): string
    {
        $sampleRate = 8000;
        $numSamples = $seconds * $sampleRate;
        $data = str_repeat(pack('v', 0), $numSamples);
        $header = 'RIFF'.
            pack('V', 36 + strlen($data)).
            'WAVEfmt '.
            pack('V', 16).
            pack('v', 1).
            pack('v', 1).
            pack('V', $sampleRate).
            pack('V', $sampleRate * 2).
            pack('v', 2).
            pack('v', 16).
            'data'.
            pack('V', strlen($data));
        return $header.$data;
    }
}
