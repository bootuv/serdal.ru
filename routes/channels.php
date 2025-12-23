<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('rooms', function ($user) {
    return true;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Канал для мессенджера занятия - доступен владельцу и участникам
Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    $room = \App\Models\Room::find($roomId);
    if (!$room) {
        return false;
    }

    // Владелец занятия (учитель)
    if ($room->user_id === $user->id) {
        return true;
    }

    // Участники занятия (ученики)
    return $room->participants()->where('user_id', $user->id)->exists();
});
