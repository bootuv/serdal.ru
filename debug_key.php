<?php
$r = \App\Models\Recording::whereNotNull('vk_video_id')->orderBy('id', 'desc')->first();
if (!$r)
    die("No recording found\n");

echo "Checking Record ID: " . $r->id . "\n";
echo "Name: " . $r->name . "\n";
echo "VK URL: " . $r->vk_video_url . "\n";
echo "Stored Key: '" . $r->vk_access_key . "'\n";

if (preg_match('/video(-?\d+)_(\d+)/', $r->vk_video_url, $m)) {
    $token = \App\Models\Setting::where('key', 'vk_access_token')->value('value');
    $videos = $m[1] . '_' . $m[2];

    $client = new \GuzzleHttp\Client();
    try {
        $resp = $client->get('https://api.vk.com/method/video.get', [
            'query' => [
                'access_token' => $token,
                'v' => '5.199',
                'videos' => $videos,
                'extended' => 0
            ]
        ]);
        $json = json_decode($resp->getBody(), true);
        $item = $json['response']['items'][0] ?? null;

        if ($item) {
            $realKey = $item['access_key'] ?? null;
            echo "API Key:  '" . ($realKey ?: 'NULL') . "'\n";
            echo "Match: " . ($r->vk_access_key === $realKey ? "YES" : "NO") . "\n";

            if ($r->vk_access_key !== $realKey) {
                echo "Updating key...\n";
                $r->update(['vk_access_key' => $realKey]);
                echo "Key updated.\n";
            }
        } else {
            echo "Video not found in API response. " . json_encode($json) . "\n";
        }
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
