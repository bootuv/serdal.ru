<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FileUploadHelper
{
    /**
     * Process and store uploaded files to S3.
     * Compresses images and deletes temporary files.
     *
     * @param mixed $files Single file or array of files
     * @param string $directory Target directory in S3
     * @param int $maxWidth Maximum image width (0 = no resize)
     * @param int $maxHeight Maximum image height (0 = no resize)
     * @param int $quality JPEG/PNG quality (1-100)
     * @return array Array of stored file paths
     */
    public static function processFiles(
        mixed $files,
        string $directory,
        int $maxWidth = 1920,
        int $maxHeight = 1080,
        int $quality = 85
    ): array {
        if (empty($files)) {
            return [];
        }

        $processedPaths = [];

        foreach ((array) $files as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $path = self::processAndStoreFile($file, $directory, $maxWidth, $maxHeight, $quality);
                if ($path) {
                    $processedPaths[] = $path;
                }
            } elseif (is_string($file)) {
                // Keep existing file paths
                $processedPaths[] = $file;
            }
        }

        return $processedPaths;
    }

    /**
     * Process a single file: compress if image, store to S3, delete temp.
     */
    public static function processAndStoreFile(
        TemporaryUploadedFile $file,
        string $directory,
        int $maxWidth = 1920,
        int $maxHeight = 1080,
        int $quality = 85
    ): ?string {
        try {
            $mimeType = $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
            $isImage = str_starts_with($mimeType, 'image/') && !str_contains($mimeType, 'gif');

            $newPath = $directory . '/' . uniqid() . '_' . time() . '.' . $extension;

            if ($isImage && $maxWidth > 0 && $maxHeight > 0) {
                // Process image: resize and compress
                $path = self::processImage($file, $newPath, $maxWidth, $maxHeight, $quality);
            } else {
                // Store file as-is
                Storage::disk('s3')->putFileAs($directory, $file, basename($newPath), 'public');
                $path = $newPath;
            }

            // Delete temporary file
            $file->delete();

            return $path;
        } catch (\Exception $e) {
            \Log::error('FileUploadHelper: Failed to process file - ' . $e->getMessage());

            // Fallback: try to store without processing
            try {
                $path = $file->store($directory, 's3');
                $file->delete();
                return $path;
            } catch (\Exception $e2) {
                \Log::error('FileUploadHelper: Fallback storage also failed - ' . $e2->getMessage());
                return null;
            }
        }
    }

    /**
     * Process an image: resize and compress.
     */
    private static function processImage(
        TemporaryUploadedFile $file,
        string $targetPath,
        int $maxWidth,
        int $maxHeight,
        int $quality
    ): string {
        $imageContent = $file->get();
        $image = Image::read($imageContent);

        // Scale down if larger than max dimensions
        $image->scaleDown($maxWidth, $maxHeight);

        $extension = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        $encoded = $image->encodeByExtension($extension, quality: $quality);

        Storage::disk('s3')->put($targetPath, (string) $encoded, 'public');

        return $targetPath;
    }

    /**
     * Create a Filament afterStateUpdated callback for FileUpload.
     *
     * @param string $fieldName The form field name
     * @param string $directory Target directory in S3
     * @param int $maxWidth Maximum image width
     * @param int $maxHeight Maximum image height
     * @param int $quality JPEG/PNG quality
     * @return \Closure
     */
    public static function filamentCallback(
        string $fieldName,
        string $directory,
        int $maxWidth = 1920,
        int $maxHeight = 1080,
        int $quality = 85,
        bool $isMultiple = false
    ): \Closure {
        return function (\Filament\Forms\Get $get, \Filament\Forms\Set $set, $state) use ($fieldName, $directory, $maxWidth, $maxHeight, $quality, $isMultiple) {
            if (empty($state)) {
                return;
            }

            // check if there are any new files to process
            $hasNewFiles = false;
            foreach ((array) $state as $file) {
                if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                    $hasNewFiles = true;
                    break;
                }
            }

            if (!$hasNewFiles) {
                return;
            }

            $processedPaths = self::processFiles($state, $directory, $maxWidth, $maxHeight, $quality);

            if (!empty($processedPaths)) {
                // For multiple files, always return array. For single file, return string.
                if ($isMultiple) {
                    $value = $processedPaths;
                } else {
                    $value = $processedPaths[0] ?? null;
                }

                $set($fieldName, $value);
            }
        };
    }

    /**
     * Process attachments for Livewire chat components.
     * Returns array of processed attachment data.
     *
     * @param array $attachments Array of uploaded files
     * @param array $processedAttachments Already processed attachments (to skip)
     * @param string $directory Target directory in S3
     * @param int $maxWidth Maximum image width
     * @param int $maxHeight Maximum image height
     * @return array Updated processedAttachments array
     */
    public static function processChatAttachments(
        array $attachments,
        array $processedAttachments,
        string $directory,
        int $maxWidth = 1920,
        int $maxHeight = 1080
    ): array {
        foreach ($attachments as $index => $file) {
            // Skip already processed files
            if (isset($processedAttachments[$index])) {
                continue;
            }

            if (!($file instanceof TemporaryUploadedFile)) {
                continue;
            }

            $mimeType = $file->getMimeType();
            $originalName = $file->getClientOriginalName();

            // Process and store the file
            $path = self::processAndStoreFile($file, $directory, $maxWidth, $maxHeight, 85);

            if ($path) {
                $processedAttachments[$index] = [
                    'path' => $path,
                    'name' => $originalName,
                    'type' => $mimeType,
                    'size' => Storage::disk('s3')->size($path),
                    'processed' => true,
                ];
            }
        }

        return $processedAttachments;
    }
    /**
     * Create a Filament deleteUploadedFileUsing callback.
     * Deletes the file from S3 when it is removed from the form.
     *
     * @param string $disk The storage disk to delete from
     * @return \Closure
     */
    public static function filamentDeleteCallback(string $disk = 's3'): \Closure
    {
        return function ($file) use ($disk) {
            if (!$file) {
                return;
            }

            try {
                Storage::disk($disk)->delete($file);
            } catch (\Exception $e) {
                \Log::error('FileUploadHelper: Failed to delete file - ' . $e->getMessage());
            }
        };
    }

    /**
     * Delete a chat attachment from S3 given its processed array info.
     *
     * @param array $processedAttachment The processed attachment data array
     * @param string $disk The storage disk
     */
    public static function deleteChatAttachment(array $processedAttachment, string $disk = 's3'): void
    {
        if (isset($processedAttachment['path'])) {
            try {
                Storage::disk($disk)->delete($processedAttachment['path']);
            } catch (\Exception $e) {
                \Log::error('FileUploadHelper: Failed to delete chat attachment - ' . $e->getMessage());
            }
        }
    }
}
