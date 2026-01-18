<?php
// Delete stuck recordings from BBB
echo "Deleting stuck recordings from BBB...\n";

// Get teacher with BBB credentials
$teacher = \App\Models\User::whereNotNull('bbb_url')->whereNotNull('bbb_secret')->first();
if (!$teacher) {
    // Fallback to global
    $url = \App\Models\Setting::where('key', 'bbb_url')->value('value');
    $secret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');
} else {
    $url = $teacher->bbb_url;
    $secret = $teacher->bbb_secret;
}

config([
    'bigbluebutton.BBB_SERVER_BASE_URL' => $url,
    'bigbluebutton.BBB_SECURITY_SALT' => $secret,
]);

echo "Using BBB URL: $url\n";

// Get recordings without VK that are in local DB
$recs = \App\Models\Recording::whereNull('vk_video_url')
    ->where('start_time', '>', now()->subDays(1))
    ->get();

foreach ($recs as $r) {
    echo "Deleting {$r->record_id}...\n";
    try {
        $resp = \JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton::deleteRecordings([
            'recordID' => $r->record_id
        ]);
        echo "  Response: " . json_encode($resp) . "\n";

        // Also delete from local DB
        $r->delete();
        echo "  Deleted from local DB.\n";
    } catch (\Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
