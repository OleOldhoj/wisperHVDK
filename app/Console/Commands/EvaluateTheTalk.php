<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EvaluateTheTalk extends Command
{
    protected $signature   = 'EvaluateTheTalk:talk';
    protected $description = '';

    public function handle(): int
    {

        $assistantId = getenv('OPENAI_ASSISTANT_ID') ?: null;

        $rows = DB::table('sales_call_ratings')
            ->whereRaw('CHAR_LENGTH(WisperTALK) > 100')
            ->WhereNull('greeting_quality')
            ->WhereNull('needs_assessment')
            ->WhereNull('closing')
            ->WhereNull('product_knowledge')

            ->limit(50)
            ->get();

        $this->info('Found ' . $rows->count() . ' row(s) to process');

        foreach ($rows as $row) {
            print_r($row);
            $row->WisperTALK; 
            
        }
        return 0;
    }




    
}
