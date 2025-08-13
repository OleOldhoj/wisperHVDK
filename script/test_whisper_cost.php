<?php

function create_wav(string $path, int $seconds, int $rate = 8000): void {
    $numChannels = 1;
    $bitsPerSample = 16;
    $blockAlign = $numChannels * $bitsPerSample / 8;
    $byteRate = $rate * $blockAlign;
    $numSamples = $rate * $seconds;
    $dataSize = $numSamples * $blockAlign;
    $chunkSize = 36 + $dataSize;
    $h = fopen($path, 'wb');
    fwrite($h, 'RIFF');
    fwrite($h, pack('V', $chunkSize));
    fwrite($h, 'WAVEfmt ');
    fwrite($h, pack('VvvVVvv', 16, 1, $numChannels, $rate, $byteRate, $blockAlign, $bitsPerSample));
    fwrite($h, 'data');
    fwrite($h, pack('V', $dataSize));
    fwrite($h, str_repeat(pack('v', 0), $numSamples));
    fclose($h);
}

function run_cmd(string $cmd, ?string &$stderr = null): array {
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('Failed to execute command');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderrContent = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $stderr = $stderrContent;
    return [$code, $stdout];
}

$script = __DIR__ . '/whisper_cost.php';
$tmpdir = sys_get_temp_dir() . '/whisper_cost_' . uniqid();
mkdir($tmpdir);

create_wav($tmpdir . '/a.wav', 6);
create_wav($tmpdir . '/b.wav', 3);
[$code, $out] = run_cmd('php ' . escapeshellarg($script) . ' ' . escapeshellarg($tmpdir));
if ($code !== 0 || strpos($out, $tmpdir . "/a.wav\t$0.0006") === false || strpos($out, $tmpdir . "/b.wav\t$0.0003") === false || strpos($out, "Total\t$0.0009") === false) {
    fwrite(STDERR, "Directory test failed\n");
    array_map('unlink', glob($tmpdir . '/*.wav'));
    rmdir($tmpdir);
    exit(1);
}

$single = $tmpdir . '/single.wav';
create_wav($single, 60);
[$code, $out] = run_cmd('php ' . escapeshellarg($script) . ' ' . escapeshellarg($single));
if ($code !== 0 || strpos($out, $single . "\t$0.0060") === false || strpos($out, "Total\t$0.0060") === false) {
    fwrite(STDERR, "Single file test failed\n");
    array_map('unlink', glob($tmpdir . '/*.wav'));
    rmdir($tmpdir);
    exit(1);
}

[$code, $out] = run_cmd('php ' . escapeshellarg($script) . ' ' . escapeshellarg($tmpdir . '/missing'), $err);
if ($code === 0 || strpos($err, 'Path not found') === false) {
    fwrite(STDERR, "Missing path test failed\n");
    array_map('unlink', glob($tmpdir . '/*.wav'));
    rmdir($tmpdir);
    exit(1);
}

array_map('unlink', glob($tmpdir . '/*.wav'));
rmdir($tmpdir);

echo "OK\n";
?>
