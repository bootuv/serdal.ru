<?php
$r = \App\Models\Recording::whereNotNull('vk_video_id')->orderBy('id', 'desc')->first();
if (!$r)
    die("No record\n");

if (preg_match('/video(-?\d+)_(\d+)/', $r->vk_video_url, $m)) {
    $token = \App\Models\Setting::where('key', 'vk_access_token')->value('value');
    $videos = $m[1] . '_' . $m[2];

    $client = new \GuzzleHttp\Client();
    $resp = $client->get('https://api.vk.com/method/video.get', [
        'query' => [
            'access_token' => $token,
            'v' => '5.199',
            'videos' => $videos,
            'extended' => 1 // Extended info + privacy
        ]
    ]);
    $json = json_decode($resp->getBody(), true);
    $item = $json['response']['items'][0] ?? null;

    if ($item) {
        $priv = $item['privacy_view'] ?? [];
        echo "Privacy View Raw: " . json_encode($priv) . "\n";

        // Check if it is "link" (category 3? or string 'by_link'?)
        // In API v5+: 
        // type: all, friends, friends_of_friends, only_me, some_users, some_lists, no_one
        // But for groups?

        echo "Access Key: " . ($item['access_key'] ?? 'None') . "\n";
        echo "Player: " . ($item['player'] ?? 'None') . "\n";
    }
}
