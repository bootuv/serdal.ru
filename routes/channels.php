<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('rooms', function ($user) {
    \Illuminate\Support\Facades\Log::info('Channel auth requested', ['user' => $user->id ?? 'null']);
    return true;
});
