<?php
$r = \App\Models\Recording::whereNotNull('vk_video_id')->orderBy('id', 'desc')->first();
if (!$r)
    die("No recording found\n");

if (preg_match('/video(-?\d+)_(\d+)/', $r->vk_video_url, $m)) {
    $token = \App\Models\Setting::where('key', 'vk_access_token')->value('value');
    $videos = $m[1] . '_' . $m[2];

    $client = new \GuzzleHttp\Client();
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
        echo "Privacy View: " . json_encode($item['privacy_view'] ?? 'KEY_MISSING') . "\n";
        echo "Access Key: " . ($item['access_key'] ?? 'None') . "\n";
        echo "Link: https://vk.com/video_ext.php?oid={$m[1]}&id={$m[2]}&hash=" . ($item['access_key'] ?? '') . "\n";
    }
}
