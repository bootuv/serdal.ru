<?php

namespace App\Filament\Resources\RecordingResource\Pages;

use App\Filament\Resources\RecordingResource;
use App\Models\Recording;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ViewRecording extends Page
{
    protected static string $resource = RecordingResource::class;

    protected static string $view = 'filament.app.resources.recording-resource.pages.view-recording';

    public Recording $record;

    public function mount(Recording $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name ?? 'Запись урока';
    }

    public function getBreadcrumbs(): array
    {
        return [
            RecordingResource::getUrl() => 'Записи',
            '#' => $this->record->name ?? 'Запись урока',
        ];
    }

    /**
     * Get VK embed URL from the video URL
     */
    public function getVkEmbedUrl(): ?string
    {
        if (empty($this->record->vk_video_url)) {
            return null;
        }

        // Parse URL like https://vk.com/video-235411509_456239023
        if (preg_match('/video(-?\d+)_(\d+)/', $this->record->vk_video_url, $matches)) {
            $ownerId = $matches[1];
            $videoId = $matches[2];

            $embedUrl = "https://vk.com/video_ext.php?oid={$ownerId}&id={$videoId}&hd=2&autoplay=0";

            // Add access key for private videos
            if (!empty($this->record->vk_access_key)) {
                $embedUrl .= "&hash={$this->record->vk_access_key}";
            }

            return $embedUrl;
        }

        return null;
    }
}
