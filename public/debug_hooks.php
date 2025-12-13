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

// 1. Find User Config or Global Config
$user = \App\Models\User::whereNotNull('bbb_url')->where('bbb_url', '!=', '')->first();
$bbbUrl = null;
$bbbSecret = null;

if ($user) {
    echo "Found Config for User: {$user->id}<br>";
    $bbbUrl = rtrim($user->bbb_url, '/') . '/';
    $bbbSecret = $user->bbb_secret;
} else {
    echo "No user specific settings found. Checking Global Settings...<br>";
    $bbbUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
    $bbbSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');

    if ($bbbUrl && $bbbSecret) {
        echo "Found Global Config.<br>";
        $bbbUrl = rtrim($bbbUrl, '/') . '/';
    } else {
        die("No BBB settings found (User or Global).");
    }
}

echo "BBB URL: " . htmlspecialchars($bbbUrl) . "<br>";
echo "Secret Length: " . strlen($bbbSecret) . "<br>";

$webhookUrl = route('api.bbb.webhook');
echo "Target Callback URL: $webhookUrl <br>";

// 2. List Registered Hooks
echo "<h3>Current Hooks List</h3>";
$actionList = "hooks/list";
$checksumList = sha1($actionList . $bbbSecret);
$listUrl = $bbbUrl . "api/" . $actionList . "?checksum=" . $checksumList;

$chList = curl_init();
curl_setopt($chList, CURLOPT_URL, $listUrl);
curl_setopt($chList, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chList, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($chList, CURLOPT_TIMEOUT, 10);
$outputList = curl_exec($chList);
curl_close($chList);

if ($outputList) {
    // Attempt to parse XML
    $xml = simplexml_load_string($outputList);
    if ($xml && isset($xml->hooks->hook)) {
        debug_print("Found Hooks (Active)", $xml);
    } else {
        echo "<b>No active hooks found.</b> (Server returned success but empty list)<br>";
        if ($outputList)
            debug_print("Raw Response", $outputList);
    }
} else {
    echo "Failed to fetch hooks list.<br>";
}

// 3. Manual Register Test (just in case)
echo "<h3>Register Attempt (Check for duplicate)</h3>";
$action = "hooks/create";
$params = "callbackURL=" . urlencode($webhookUrl);
$checksum = sha1($action . $params . $bbbSecret);
$fullUrl = $bbbUrl . "api/" . $action . "?" . $params . "&checksum=" . $checksum;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$output = curl_exec($ch);
curl_close($ch);
debug_print("Register Response", htmlspecialchars($output));
