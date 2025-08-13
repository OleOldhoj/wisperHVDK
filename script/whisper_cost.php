<?php
// Calculate transcription cost for audio files using Whisper pricing.
// Usage: php whisper_cost.php <audio_file_or_dir>

const RATE_PER_MINUTE = 0.006; // USD per minute

function wav_duration_seconds(string $path): float {
    $header = @file_get_contents($path, false, null, 0, 44);
    if ($header === false || strlen($header) < 44) {
        throw new RuntimeException("Invalid WAV file: $path");
    }
    $sampleRate = unpack('V', substr($header, 24, 4))[1];
    $numChannels = unpack('v', substr($header, 22, 2))[1];
    $bitsPerSample = unpack('v', substr($header, 34, 2))[1];
    $dataSize = unpack('V', substr($header, 40, 4))[1];
    $byteRate = $sampleRate * $numChannels * $bitsPerSample / 8;
    return $dataSize / $byteRate;
}

function iter_wav_files(string $target): Generator {
    if (is_file($target)) {
        if (strtolower(pathinfo($target, PATHINFO_EXTENSION)) === 'wav') {
            yield $target;
        }
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'wav') {
            yield $file->getPathname();
        }
    }
}

if ($argc !== 2) {
    fwrite(STDERR, "Usage: whisper_cost.php <audio_file_or_dir>\n");
    $argv[1] = 'C:\wisper\sound';
}

$target = $argv[1];
if (!file_exists($target)) {
    fwrite(STDERR, "Path not found: $target\n");
    exit(1);
}

$totalCost = 0.0;
$totalMinutes = 0.0;

$filenameWidth = 120;
$costWidth = 10;
$lengthWidth = 15;

foreach (iter_wav_files($target) as $wav) {
    $durationSec = wav_duration_seconds($wav);
    $durationMin = $durationSec / 60.0;
    $cost = $durationMin * RATE_PER_MINUTE;

    $totalCost += $cost;
    $totalMinutes += $durationMin;

    $minDisplay = floor($durationMin);
     $tmp = $durationSec;
    $secDisplay = $durationSec % 60;

    echo str_pad($wav, $filenameWidth);
    echo str_pad("$" . number_format($cost, 4, '.', ''), $costWidth);
    echo str_pad("L:{$minDisplay}m " . number_format($secDisplay, 0) . "s", $lengthWidth);
    echo str_pad("Ls: $tmp", 5);
    echo PHP_EOL;
}

$totalHours = $totalMinutes / 60.0;

echo str_repeat("-", $filenameWidth + $costWidth + $lengthWidth) . PHP_EOL;
echo str_pad("TOTAL", $filenameWidth);
echo str_pad("$" . number_format($totalCost, 4, '.', ''), $costWidth);
echo str_pad("Sum: " . number_format($totalMinutes, 2) . " min", $lengthWidth);
echo PHP_EOL;
echo str_pad("", $filenameWidth);
echo str_pad("", $costWidth);
echo str_pad("(" . number_format($totalHours, 2) . " h)", $lengthWidth);
echo PHP_EOL;
