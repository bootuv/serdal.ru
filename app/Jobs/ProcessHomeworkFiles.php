<?php

namespace App\Jobs;

use App\Models\Homework;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProcessHomeworkFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_WIDTH = 1920;
    private const MAX_HEIGHT = 1080;
    private const QUALITY = 85;

    public function __construct(
        public Homework $homework
    ) {
    }

    public function handle(): void
    {
        $attachments = $this->homework->attachments;

        if (empty($attachments)) {
            return;
        }

        $processedAttachments = [];
        $hasChanges = false;

        foreach ($attachments as $path) {
            if (!is_string($path)) {
                $processedAttachments[] = $path;
                continue;
            }

            // Check if it's an image that needs processing
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp']);
            $isGif = $extension === 'gif';

            if ($isImage && !$isGif && Storage::disk('s3')->exists($path)) {
                try {
                    $imageContent = Storage::disk('s3')->get($path);
                    $image = Image::read($imageContent);

                    // Only process if image is larger than max dimensions
                    if ($image->width() > self::MAX_WIDTH || $image->height() > self::MAX_HEIGHT) {
                        $image->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT);

                        // Generate new filename with _processed suffix
                        $newPath = preg_replace('/\.(' . $extension . ')$/i', '_processed.$1', $path);

                        // Save processed image
                        $encodedImage = $image->encodeByExtension($extension, quality: self::QUALITY);
                        Storage::disk('s3')->put($newPath, (string) $encodedImage, 'public');

                        // Delete original
                        Storage::disk('s3')->delete($path);

                        $processedAttachments[] = $newPath;
                        $hasChanges = true;

                        \Log::info("Processed homework image: {$path} -> {$newPath}");
                    } else {
                        $processedAttachments[] = $path;
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to process homework image {$path}: " . $e->getMessage());
                    $processedAttachments[] = $path;
                }
            } else {
                $processedAttachments[] = $path;
            }
        }

        // Update homework if any changes were made
        if ($hasChanges) {
            $this->homework->update([
                'attachments' => $processedAttachments,
            ]);
        }
    }
}
