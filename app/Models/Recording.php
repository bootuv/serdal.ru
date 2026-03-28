<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Recording extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'meeting_id',
        'record_id',
        'name',
        'published',
        'start_time',
        'end_time',
        'participants',
        'url',
        'raw_data',
        's3_url',
        's3_uploaded_at',
    ];

    protected $casts = [
        'published' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'raw_data' => 'array',
        's3_uploaded_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class, 'meeting_id', 'meeting_id');
    }

    protected static function booted()
    {
        static::deleted(function ($recording) {
            // Delete from S3 if URL exists
            if (!empty($recording->s3_url)) {
                try {
                    $storageService = app(\App\Services\RecordingStorageService::class);
                    $storageService->deleteFromS3($recording->s3_url);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to delete S3 recording on model deletion', [
                        'recording_id' => $recording->id,
                        's3_url' => $recording->s3_url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
