<?php
// Check state of recordings on BBB
$user = \App\Models\User::first();
if ($user->bbb_url && $user->bbb_secret) {
    config([
        'bigbluebutton.BBB_SERVER_BASE_URL' => $user->bbb_url,
        'bigbluebutton.BBB_SECURITY_SALT' => $user->bbb_secret,
    ]);
}

$recs = \App\Models\Recording::whereNull('vk_video_url')->orderBy('id', 'desc')->take(4)->get();
foreach ($recs as $r) {
    echo "Checking DB ID {$r->id} (BBB: {$r->record_id})...\n";
    try {
        $bbbRecs = \JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton::getRecordings([
            'recordID' => $r->record_id,
            'state' => 'any'
        ]);

        if ($bbbRecs && $bbbRecs->count() > 0) {
            $first = $bbbRecs->first();
            echo "  State: " . ($first['state'] ?? 'N/A') . "\n";
            echo "  Published: " . ($first['published'] ?? 'N/A') . "\n";
        } else {
            echo "  NOT FOUND on BBB\n";
        }
    } catch (\Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
