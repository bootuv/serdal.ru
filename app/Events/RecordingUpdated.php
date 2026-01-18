<?php

namespace App\Events;

use App\Models\Recording;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecordingUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $recordingId;
    public string $meetingId;
    public string $status;
    public ?string $vkVideoUrl;

    public function __construct(Recording $recording)
    {
        $this->recordingId = $recording->id;
        $this->meetingId = $recording->meeting_id;
        $this->vkVideoUrl = $recording->vk_video_url;

        // Determine status
        if (!empty($recording->vk_video_url)) {
            $this->status = 'ready';
        } elseif (!empty($recording->url)) {
            $this->status = 'uploading';
        } else {
            $this->status = 'processing';
        }
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('recordings.' . $this->meetingId),
            new Channel('recordings'), // Public channel for list page updates
        ];
    }

    public function broadcastAs(): string
    {
        return 'recording.updated';
    }
}
