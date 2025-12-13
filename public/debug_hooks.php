<?php

// Load Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Helpers
function debug_print($title, $content)
{
    echo "<h3>$title</h3>";
    echo "<pre>" . htmlspecialchars(print_r($content, true)) . "</pre>";
}

// 1. Find User Config
$user = \App\Models\User::whereNotNull('bbb_url')->first();
if (!$user) {
    die("No user found with BBB settings.");
}

$bbbUrl = rtrim($user->bbb_url, '/') . '/';
$bbbSecret = $user->bbb_secret;

echo "Found Config for User: {$user->id}<br>";
echo "BBB URL: " . htmlspecialchars($bbbUrl) . "<br>";
// Hide secret for security in screenshot output, show length
echo "Secret Length: " . strlen($bbbSecret) . "<br>";

// 2. Prepare Hook URL
$webhookUrl = route('api.bbb.webhook');
echo "Target Callback URL: $webhookUrl <br>";

// 3. Manual CURL to hooks/create
$action = "hooks/create";
$params = "callbackURL=" . urlencode($webhookUrl); // Global hook for test
$checksum = sha1($action . $params . $bbbSecret);
$fullUrl = $bbbUrl . "api/" . $action . "?" . $params . "&checksum=" . $checksum;

echo "Requesting (Manual Curl): " . htmlspecialchars($fullUrl) . "<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Allow self-signed for debug
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$output = curl_exec($ch);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($error) {
    debug_print("CURL Error", $error);
} else {
    debug_print("HTTP Code", $info['http_code']);
    debug_print("Raw Response", $output);
}

// 4. Test Route Access (Self)
// Check if we can ping our own webhook URL to make sure it's accessible locally/publicly
echo "<h3>Self-Ping Check</h3>";
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $webhookUrl);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(['event' => ['type' => 'ping']]));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
// This might fail if loopback is blocked, but worth checking
$output2 = curl_exec($ch2);
$error2 = curl_error($ch2);
curl_close($ch2);

if ($error2) {
    echo "Self-ping failed: " . htmlspecialchars($error2);
} else {
    echo "Self-ping response: " . htmlspecialchars($output2);
}
