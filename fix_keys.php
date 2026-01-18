<?php

echo "Starting Key Fix...\n";

$token = \App\Models\Setting::where('key', 'vk_access_token')->value('value');
if (!$token) {
    die("No VK Access Token found.\n");
}

$recs = \App\Models\Recording::whereNotNull('vk_video_id')->whereNull('vk_access_key')->get();
echo "Found " . $recs->count() . " recordings without access_key.\n";

$client = new \GuzzleHttp\Client();

foreach ($recs as $r) {
    if (!$r->vk_video_url)
        continue;

    if (preg_match('/video(-?\d+)_(\d+)/', $r->vk_video_url, $m)) {
        $oid = $m[1];
        $vid = $m[2];
        $videos = "{$oid}_{$vid}";

        try {
            $resp = $client->get('https://api.vk.com/method/video.get', [
                'query' => [
                    'access_token' => $token,
                    'v' => '5.199',
                    'videos' => $videos,
                    'count' => 1
                ]
            ]);
            $json = json_decode($resp->getBody(), true);

            // Log full response for debugging if needed
            // echo json_encode($json) . "\n";

            $key = $json['response']['items'][0]['access_key'] ?? null;

            if ($key) {
                $r->update(['vk_access_key' => $key]);
                echo "SUCCESS: Fixed Recording #{$r->id} -> Key: $key\n";
            } else {
                echo "WARNING: No key in response for #{$r->id} ({$videos}). Error: " . ($json['error']['error_msg'] ?? 'Unknown') . "\n";
            }
        } catch (\Exception $e) {
            echo "ERROR for #{$r->id}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "WARNING: Could not parse video URL: {$r->vk_video_url}\n";
    }
}
echo "Fix complete.\n";
