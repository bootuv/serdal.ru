<?php
echo "Starting Hash Fix...\n";
$token = \App\Models\Setting::where('key', 'vk_access_token')->value('value');
$recs = \App\Models\Recording::whereNotNull('vk_video_id')->get();

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
            $item = $json['response']['items'][0] ?? null;

            if ($item && isset($item['player'])) {
                $playerUrl = $item['player'];
                if (preg_match('/hash=([a-f0-9]+)/', $playerUrl, $h)) {
                    $embedHash = $h[1];
                    if ($r->vk_access_key !== $embedHash) {
                        echo "Fixing #{$r->id}: Old({$r->vk_access_key}) -> New({$embedHash})\n";
                        $r->update(['vk_access_key' => $embedHash]);
                    }
                }
            }
        } catch (\Exception $e) {
        }
        usleep(300000);
    }
}
echo "Done.\n";
