<?php

namespace App\Models;

use App\Observers\TeacherMaterialObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([TeacherMaterialObserver::class])]
class TeacherMaterial extends Model
{
    // Видимость материала
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_ROOMS = 'rooms';
    const VISIBILITY_ALL = 'all_students';

    protected $fillable = [
        'teacher_id',
        'folder_id',
        'title',
        'description',
        'file_path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'file_size',
        'visibility',
        'sort_order',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * Все варианты видимости
     */
    public static function getVisibilityOptions(): array
    {
        return [
            self::VISIBILITY_PRIVATE => 'Приватно',
            self::VISIBILITY_ROOMS => 'Для группы',
            self::VISIBILITY_ALL => 'Для всех учеников',
        ];
    }

    public function getVisibilityLabelAttribute(): string
    {
        return self::getVisibilityOptions()[$this->visibility] ?? $this->visibility;
    }

    public function getVisibilityColorAttribute(): string
    {
        return match ($this->visibility) {
            self::VISIBILITY_PRIVATE => 'gray',
            self::VISIBILITY_ROOMS => 'warning',
            self::VISIBILITY_ALL => 'success',
            default => 'gray',
        };
    }

    public function getVisibilityIconAttribute(): string
    {
        return match ($this->visibility) {
            self::VISIBILITY_PRIVATE => 'heroicon-m-lock-closed',
            self::VISIBILITY_ROOMS => 'heroicon-m-user-group',
            self::VISIBILITY_ALL => 'heroicon-m-globe-alt',
            default => 'heroicon-m-lock-closed',
        };
    }

    /**
     * Учитель — владелец материала
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Папка (опционально)
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MaterialFolder::class, 'folder_id');
    }

    /**
     * Комнаты (группы), которым доступен материал при visibility=rooms
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'material_room', 'material_id', 'room_id');
    }

    /**
     * Публичный URL файла на CDN
     */
    public function getFileUrlAttribute(): string
    {
        return Storage::disk('s3')->url($this->file_path);
    }

    /**
     * URL миниатюры (если есть)
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? Storage::disk('s3')->url($this->thumbnail_path) : null;
    }

    /**
     * Тип файла для отображения (иконка/цвет карточки)
     */
    public function getFileKindAttribute(): string
    {
        $mime = $this->mime_type ?? '';
        $extension = strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            $mime === 'application/pdf' || $extension === 'pdf' => 'pdf',
            in_array($extension, ['doc', 'docx', 'rtf', 'odt', 'txt']) => 'document',
            in_array($extension, ['xls', 'xlsx', 'ods', 'csv']) => 'spreadsheet',
            in_array($extension, ['ppt', 'pptx', 'odp']) => 'presentation',
            in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz']) => 'archive',
            default => 'other',
        };
    }

    public function getFileKindIconAttribute(): string
    {
        return match ($this->file_kind) {
            'image' => 'heroicon-o-photo',
            'video' => 'heroicon-o-film',
            'audio' => 'heroicon-o-musical-note',
            'pdf' => 'heroicon-o-document-text',
            'document' => 'heroicon-o-document',
            'spreadsheet' => 'heroicon-o-table-cells',
            'presentation' => 'heroicon-o-presentation-chart-bar',
            'archive' => 'heroicon-o-archive-box',
            default => 'heroicon-o-paper-clip',
        };
    }

    /**
     * Человекочитаемый размер файла
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size ?? 0;

        if ($bytes <= 0) {
            return '—';
        }

        $units = ['Б', 'КБ', 'МБ', 'ГБ'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), $power > 1 ? 1 : 0) . ' ' . $units[$power];
    }

    /**
     * Материалы, доступные ученику: «для всех» от его учителей
     * плюс «для группы» из занятий, где он участник
     */
    public function scopeVisibleToStudent($query, User $student)
    {
        $teacherIds = $student->teachers()->pluck('users.id');
        $roomIds = $student->assignedRooms()->pluck('rooms.id');

        return $query->where(function ($q) use ($teacherIds, $roomIds) {
            $q->where(function ($q2) use ($teacherIds) {
                $q2->where('visibility', self::VISIBILITY_ALL)
                    ->whereIn('teacher_id', $teacherIds);
            })->orWhere(function ($q2) use ($roomIds) {
                $q2->where('visibility', self::VISIBILITY_ROOMS)
                    ->whereHas('rooms', fn ($r) => $r->whereIn('rooms.id', $roomIds));
            });
        });
    }

    /**
     * Доступен ли материал ученику
     */
    public function isVisibleTo(User $student): bool
    {
        return match ($this->visibility) {
            self::VISIBILITY_ALL => $student->teachers()->where('users.id', $this->teacher_id)->exists(),
            self::VISIBILITY_ROOMS => $this->rooms()
                ->whereHas('participants', fn ($q) => $q->where('users.id', $student->id))
                ->exists(),
            default => false,
        };
    }
}
