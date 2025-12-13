<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('rooms', function ($user) {
    return true; // Public channel effectively, or strictly for auth users
});
