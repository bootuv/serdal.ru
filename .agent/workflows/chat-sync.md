---
description: Sync changes between RoomChat and SupportChatComponent
---

# Chat Sync Workflow

When making changes to the chat functionality, always apply the same changes to both components:

## Files to Keep in Sync

### PHP Components
- `app/Livewire/RoomChat.php` ↔ `app/Livewire/SupportChatComponent.php`

### Blade Views
- `resources/views/livewire/room-chat.blade.php` ↔ `resources/views/livewire/support-chat-component.blade.php`

### Broadcast Events
- `app/Events/MessageSent.php` ↔ `app/Events/SupportMessageSent.php`

### Channels
- `routes/channels.php` - contains authorization for both `room.{id}` and `support-chat.{id}`

## Checklist for Chat Changes

1. [ ] Apply change to RoomChat component
2. [ ] Apply same change to SupportChatComponent
3. [ ] If broadcasting-related, update both MessageSent and SupportMessageSent events
4. [ ] Test both chats to verify functionality
