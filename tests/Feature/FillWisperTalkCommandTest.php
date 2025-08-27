<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FillWisperTalkCommandTest extends TestCase
{
    public function testTranscribesMissingSalesRows(): void
    {
        $dir = sys_get_temp_dir() . '/wisper' . uniqid();
        mkdir($dir);
        $path = $dir . '/dummy.wav';
        file_put_contents($path, '');

        DB::statement('CREATE TABLE sales_call_ratings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filepath TEXT,
            WisperTALK TEXT,
            Dept TEXT
        )');

        DB::table('sales_call_ratings')->insert([
            'filepath' => $path,
            'Dept' => 'Sales',
        ]);

        putenv('OPENAI_TRANSCRIBE_FAKE=stub text');
        Artisan::call('fill:wispertalk');

        $this->assertDatabaseHas('sales_call_ratings', [
            'filepath' => $path,
            'WisperTALK' => 'stub text',
        ]);
    }
}
