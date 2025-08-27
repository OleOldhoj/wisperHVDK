<?php
$path = __DIR__ . '/../1234_cron_fill_call_ratings.php';
if (!file_exists($path)) {
    fwrite(STDERR, "Cron file missing\n");
    exit(1);
}
$contents = file_get_contents($path);
if (strpos($contents, '1234_cron_fill_call_ratings.php') === false) {
    fwrite(STDERR, "Cron file not prefixed correctly\n");
    exit(1);
}
print "Cron file present with correct prefix\n";
