<?php
// Calculate transcription cost for audio files using Whisper pricing.
// Usage: php whisper_cost.php <audio_file_or_dir>

const RATE_PER_MINUTE = 0.006;

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
    exit(1);
}
$target = $argv[1];
if (!file_exists($target)) {
    fwrite(STDERR, "Path not found: $target\n");
    exit(1);
}
$total = 0.0;
foreach (iter_wav_files($target) as $wav) {
    $duration = wav_duration_seconds($wav);
    $cost = ($duration / 60.0) * RATE_PER_MINUTE;
    $total += $cost;
    echo $wav . "\t$" . number_format($cost, 4, '.', '') . PHP_EOL;
}
echo "Total\t$" . number_format($total, 4, '.', '') . PHP_EOL;
