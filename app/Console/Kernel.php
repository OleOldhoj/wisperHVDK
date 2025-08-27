<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // 1. Schedule the call rating updater every five minutes.
        $schedule->command('fill:call-ratings')
            ->everyFiveMinutes()
            ->appendOutputTo('/var/log/wisper_ratings.log');

        // 2. Schedule WisperTALK transcription for Sales department.
        $schedule->command('fill:wispertalk')
            ->everyFiveMinutes()
            ->appendOutputTo('/var/log/wisper_transcribe.log');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
