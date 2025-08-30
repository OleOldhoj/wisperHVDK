<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DumpEnv extends Command
{
    protected $signature = 'dump:env';
    protected $description = 'Print all env variables Laravel sees';

    public function handle(): int
    {
    foreach (getenv() as $key => $value) {
        $this->line($key . '=' . $value);
    }
        return 0;
    }
}
