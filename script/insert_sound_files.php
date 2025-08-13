<?php
// insert_sound_files.php
// Recursively scan a directory for files and insert each path into sales_call_ratings table.

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
    $path = $file->getPathname();
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
