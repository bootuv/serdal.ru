<?php
$logFile = __DIR__ . '/../storage/logs/laravel.log';

if (!file_exists($logFile)) {
    die("Log file not found.");
}

$lines = file($logFile);
$bbbLogs = [];

foreach ($lines as $line) {
    if (strpos($line, 'BBB Webhook') !== false) {
        $bbbLogs[] = $line;
    }
}

// Show last 50 entries in reverse order
$bbbLogs = array_reverse($bbbLogs);
$bbbLogs = array_slice($bbbLogs, 0, 50);

echo "<h1>BBB Webhook Logs (Last 50)</h1>";
echo "<pre>";
foreach ($bbbLogs as $log) {
    echo htmlspecialchars($log);
}
echo "</pre>";
