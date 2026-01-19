<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\Setting;

$accessToken = Setting::where('key', 'vk_access_token')->value('value');
$ownerId = '-235411509';
$videoId = '456239220';

echo "Access Token: " . substr($accessToken, 0, 10) . "...\n";

$response = Http::get('https://api.vk.com/method/video.get', [
    'access_token' => $accessToken,
    'v' => '5.199',
    'videos' => "{$ownerId}_{$videoId}",
    'count' => 1
]);

$data = $response->json();

print_r($data);

if (isset($data['response']['items'][0]['player'])) {
    $playerUrl = $data['response']['items'][0]['player'];
    echo "\nPlayer URL: $playerUrl\n";

    if (preg_match('/hash=([a-f0-9]+)/', $playerUrl, $matches)) {
        echo "Extracted Hash (Hex): " . $matches[1] . "\n";
    } else {
        echo "Regex failed to match hex hash.\n";
    }

    if (preg_match('/hash=([a-zA-Z0-9_\-]+)/', $playerUrl, $matches)) {
        echo "Extracted Hash (Alphanumeric): " . $matches[1] . "\n";
    }
}
