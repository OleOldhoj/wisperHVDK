<?php
// insert_sound_files.php
// Recursively scan a directory for audio files and insert qualifying paths into
// the sales_call_ratings table.

/**
 * Return duration of a WAV file in seconds or null on failure.
 */
function getWavDuration(string $file): ?float
{
    $handle = fopen($file, 'rb');
    if ($handle === false) {
        return null;
    }

    // Channels
    fseek($handle, 22);
    $channels = unpack('v', fread($handle, 2))[1] ?? 0;

    // Sample rate
    $sampleRate = unpack('V', fread($handle, 4))[1] ?? 0;

    // Bits per sample
    fseek($handle, 34);
    $bitsPerSample = unpack('v', fread($handle, 2))[1] ?? 0;

    // Data size
    fseek($handle, 40);
    $dataSize = unpack('V', fread($handle, 4))[1] ?? 0;

    fclose($handle);

    if ($channels === 0 || $bitsPerSample === 0 || $sampleRate === 0) {
        return null;
    }

    $bytesPerSample = ($bitsPerSample / 8) * $channels;
    return $dataSize / ($sampleRate * $bytesPerSample);
}

$directory = $argv[1] ?? 'C:\\wisper\\sound';

if (!is_dir($directory)) {
    fwrite(STDERR, "Directory not found: {$directory}\n");
    exit(1);
}

 $connection = getenv('DB_CONNECTION') ?: 'mysql';
 $database   = getenv('DB_DATABASE')   ?: 'salescallsanalyse';
 $username   = getenv('DB_USERNAME')   ?: 'root';
 $password   = getenv('DB_PASSWORD')   ?: '';
 $host       = getenv('DB_HOST')       ?: '127.0.0.1';

 if ($connection === 'sqlite') {
     $dsn = "sqlite:" . $database;
     $username = null;
     $password = null;
 } else {
     $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
 }

 try {
     $pdo = new PDO($dsn, $username, $password, [
         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
);

$sql = "INSERT INTO sales_call_ratings (
    filepath,
    call_id,
    greeting_quality,
    needs_assessment,
    product_knowledge,
    persuasion,
    closing
) VALUES (
    :filepath,
    :call_id,
    0,
    0,
    0,
    0,
    0
)";

$stmt = $pdo->prepare($sql);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    if (strtolower($file->getExtension()) !== 'wav') {
        continue; // Only process WAV files
    }

    $path = $file->getPathname();
    $duration = getWavDuration($path);

    if ($duration !== null && $duration < 120) {
        $callId = pathinfo($path, PATHINFO_FILENAME);
        try {
            $stmt->execute([
                ':filepath' => $path,
                ':call_id' => $callId,
            ]);
        } catch (PDOException $e) {
            // Continue on duplicate or other insertion errors.
            fwrite(STDERR, "Failed to insert {$path}: " . $e->getMessage() . "\n");
        }
    }
}
