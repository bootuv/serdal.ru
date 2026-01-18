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

// Канал для чата техподдержки - доступен владельцу чата и всем админам
Broadcast::channel('support-chat.{chatId}', function ($user, $chatId) {
    $chat = \App\Models\SupportChat::find($chatId);
    if (!$chat) {
        return false;
    }

    // Владелец чата (пользователь)
    if ($chat->user_id === $user->id) {
        return true;
    }

    // Все админы имеют доступ ко всем чатам поддержки
    return $user->role === \App\Models\User::ROLE_ADMIN;
});

// Канал для обновлений записей - доступен владельцу комнаты
Broadcast::channel('recordings.{meetingId}', function ($user, $meetingId) {
    $room = \App\Models\Room::where('meeting_id', $meetingId)->first();
    if (!$room) {
        return false;
    }
    return $room->user_id === $user->id;
});
