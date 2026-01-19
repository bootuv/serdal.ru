<?php

use Illuminate\Support\Facades\Http;
use App\Models\Recording;
use App\Models\Setting;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$accessToken = Setting::where('key', 'vk_access_token')->value('value');
if (!$accessToken) {
    die("No VK access token found.\n");
}

echo "Access Token found. analyzing recordings...\n";

$recordings = Recording::whereNotNull('vk_video_url')->get();

foreach ($recordings as $rec) {
    // Check if key looks suspicious (too long or non-hex) based on our findings
    $key = $rec->vk_access_key;
    $needsFix = false;

    if (empty($key)) {
        // Only if it's a private video we expect a key. But we can verify all.
        $needsFix = true;
    } elseif (strlen($key) > 20) {
        // If it's the long API access key
        $needsFix = true;
        echo "Recording {$rec->id}: Key is too long (" . strlen($key) . " chars): $key\n";
    } elseif (!ctype_xdigit($key)) {
        // If it contains non-hex chars (like hyphens)
        $needsFix = true;
        echo "Recording {$rec->id}: Key is not hex: $key\n";
    }

    if ($needsFix) {
        // Extract OID and VID
        if (preg_match('/video(-?\d+)_(\d+)/', $rec->vk_video_url, $matches)) {
            $ownerId = $matches[1];
            $videoId = $matches[2];

            echo "Fetching hash for {$ownerId}_{$videoId}...\n";

            try {
                $response = Http::get('https://api.vk.com/method/video.get', [
                    'access_token' => $accessToken,
                    'v' => '5.199',
                    'videos' => "{$ownerId}_{$videoId}",
                    'count' => 1
                ]);

                $data = $response->json();

                if (isset($data['response']['items'][0]['player'])) {
                    $playerUrl = $data['response']['items'][0]['player'];
                    if (preg_match('/hash=([a-f0-9]+)/', $playerUrl, $m)) {
                        $newHash = $m[1];
                        echo "Found correct hash: $newHash\n";

                        $rec->vk_access_key = $newHash;
                        $rec->save();
                        echo "Updated.\n";
                    } else {
                        echo "Could not extract hex hash from player URL: $playerUrl\n";
                    }
                } else {
                    echo "No 'player' field in response.\n";
                    print_r($data);
                }

            } catch (\Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }

            // Sleep to avoid rate limits
            sleep(1);
        }
    }
}

echo "Done.\n";
