<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImageProcessor
{
    private const MAX_WIDTH = 1920;
    private const MAX_HEIGHT = 1080;
    private const QUALITY = 85;

    /**
     * Process uploaded file - resize image if needed
     * 
     * @param TemporaryUploadedFile|string $file
     * @param string $directory
     * @return string Path to stored file
     */
    public static function processAndStore($file, string $directory = 'attachments'): string
    {
        // Handle already stored file path
        if (is_string($file)) {
            return $file;
        }

        $mimeType = $file->getMimeType();
        $originalName = $file->getClientOriginalName();

        // Process images (except GIF)
        if (str_starts_with($mimeType, 'image/') && !str_contains($mimeType, 'gif')) {
            try {
                $imageContent = $file->get();

                if ($imageContent) {
                    $image = Image::read($imageContent);
                    $image->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT);

                    $extension = $file->getClientOriginalExtension() ?: 'jpg';
                    $filename = $directory . '/' . uniqid() . '_' . time() . '.' . $extension;

                    $encodedImage = $image->encodeByExtension($extension, quality: self::QUALITY);
                    Storage::disk('s3')->put($filename, (string) $encodedImage, 'public');

                    return $filename;
                }
            } catch (\Exception $e) {
                \Log::error('Image resize failed: ' . $e->getMessage());
            }
        }

        // For non-images or on error - store as is
        return $file->storePublicly($directory, 's3');
    }

    /**
     * Process multiple files
     * 
     * @param array $files
     * @param string $directory
     * @return array Array of stored file paths
     */
    public static function processMultiple(array $files, string $directory = 'attachments'): array
    {
        $paths = [];
        foreach ($files as $file) {
            $paths[] = self::processAndStore($file, $directory);
        }
        return $paths;
    }
}
