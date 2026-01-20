<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Subject;
use App\Models\Direct;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements FilamentUser
{
    const ROLE_MENTOR = 'mentor';

    const ROLE_TUTOR = 'tutor';

    const ROLE_STUDENT = 'student';

    const ROLE_ADMIN = 'admin';

    use HasFactory, Notifiable, HasPushSubscriptions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'password',
        'role',
        'grade',
        'avatar',
        'status',
        'about',
        'extra_info',
        'phone',
        'whatsup',
        'instagram',
        'telegram',
        'username',
        'is_active',
        'is_blocked',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires_at',
        'google_calendar_id',
        'is_profile_completed',
        'push_reminder_at',
        'push_reminder_count',
        'vk_album_id',
        'commission_rate',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'grade' => 'json',
            'google_token_expires_at' => 'datetime',
            'push_reminder_at' => 'datetime',
            'commission_rate' => 'integer',
        ];
    }

    public function getRouteKeyName()
    {
        return 'username';
    }

    public function directs()
    {
        return $this->belongsToMany(Direct::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class);
    }

    public function lessonTypes()
    {
        return $this->hasMany(LessonType::class);
    }

    public function subjectsList(): Attribute
    {
        return Attribute::make(
            get: function () {
                $names = [];

                foreach ($this->subjects as $index => $subject) {
                    if ($index > 0) {
                        $names[] = Str::lower($subject->name);
                    } else {
                        $names[] = $subject->name;
                    }
                }

                return ucfirst(implode(', ', $names));
            },
        );
    }

    public function displayGrade(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->grade)) {
                    return '';
                }

                $result = [];

                $school = array_filter($this->grade, function ($item) {
                    return $item !== 'preschool' && $item !== 'adults';
                });

                if (in_array('preschool', $this->grade)) {
                    $result[] = 'дошкольники';
                }

                if ($school) {
                    $result[] = format_grade_range($school);
                }

                if (in_array('adults', $this->grade)) {
                    $result[] = 'взрослые';
                }

                return Str::ucfirst(implode(', ', $result));
            },
        );
    }

    public function displayRole(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match ($this->role) {
                    User::ROLE_MENTOR => 'Ментор',
                    User::ROLE_TUTOR => 'Преподаватель',
                    User::ROLE_STUDENT => 'Ученик',
                    User::ROLE_ADMIN => 'Администратор',
                };
            },
        );
    }

    public function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->avatar ? Storage::disk('public')->url($this->avatar) : asset('images/default-avatar.png'),
        );
    }

    public function scopeIsSpecialist(Builder $query): Builder
    {
        return $query->whereIn('role', [User::ROLE_MENTOR, User::ROLE_TUTOR]);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Заблокированные пользователи не могут получить доступ
        if ($this->is_blocked) {
            return false;
        }

        if ($panel->getId() === 'admin') {
            return true;
        }

        if ($panel->getId() === 'app') {
            return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MENTOR, self::ROLE_TUTOR]);
        }

        if ($panel->getId() === 'student') {
            return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_STUDENT]);
        }

        return false;
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function assignedRooms()
    {
        return $this->belongsToMany(Room::class, 'room_user', 'user_id', 'room_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'teacher_student', 'teacher_id', 'student_id');
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'teacher_student', 'student_id', 'teacher_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function supportMessages()
    {
        return $this->hasMany(SupportMessage::class);
    }

    /**
     * Домашние задания, созданные учителем
     */
    public function homeworks()
    {
        return $this->hasMany(Homework::class, 'teacher_id');
    }

    /**
     * Домашние задания, назначенные ученику
     */
    public function assignedHomeworks()
    {
        return $this->belongsToMany(Homework::class, 'homework_student', 'student_id', 'homework_id');
    }

    /**
     * Сданные работы ученика
     */
    public function homeworkSubmissions()
    {
        return $this->hasMany(HomeworkSubmission::class, 'student_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function getAvatarTextColorAttribute(): string
    {
        $colors = [
            '#ef4444', // red-500
            '#f97316', // orange-500
            '#f59e0b', // amber-500
            '#22c55e', // green-500
            '#10b981', // emerald-500
            '#14b8a6', // teal-500
            '#06b6d4', // cyan-500
            '#0ea5e9', // sky-500
            '#3b82f6', // blue-500
            '#6366f1', // indigo-500
            '#8b5cf6', // violet-500
            '#a855f7', // purple-500
            '#d946ef', // fuchsia-500
            '#ec4899', // pink-500
            '#f43f5e', // rose-500
        ];

        return $colors[$this->id % count($colors)];
    }

    protected static function booted()
    {
        static::saving(function ($user) {
            // Формируем полное имя для совместимости
            $parts = array_filter([$user->last_name, $user->first_name, $user->middle_name]);
            if (!empty($parts)) {
                $user->name = implode(' ', $parts);
            }

            // Генерация username (транслитерация имени и фамилии)
            if (empty($user->username) && $user->first_name && $user->last_name) {
                // Если записи создаются, то username должен быть уникальным. 
                // Для обновлений проверяем, изменились ли имя/фамилия, но лучше генерировать только при создании, если пусто.
                if (!$user->exists || $user->isDirty(['first_name', 'last_name'])) {
                    $base = Str::slug(Str::transliterate($user->first_name . '-' . $user->last_name));
                    $username = $base;
                    $count = 1;
                    // Проверка уникальности (исключая текущего пользователя)
                    while (User::where('username', $username)->where('id', '!=', $user->id)->exists()) {
                        $username = $base . '-' . $count;
                        $count++;
                    }
                    $user->username = $username;
                }
            }
        });

        static::deleting(function ($user) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Удаляем комнаты (soft delete, затем force delete в обсервере комнаты, если нужно)
            // Но в ТЗ "удалять все созданные им занятия", подразумевается полное удаление
            $user->rooms()->get()->each(function ($room) {
                // Force delete to trigger Room's forceDeleting event for file cleanup
                $room->forceDelete();
            });

            $user->homeworks()->get()->each->delete();
            $user->homeworkSubmissions()->get()->each->delete();
            $user->messages()->get()->each->delete();

            // Assuming SupportMessage relation exists or will be added, if not present we need to add it or use query
            // Checking file analysis, SupportMessage has user_id, but User model doesn't have supportMessages relation yet.
            // I will add the relation and the delete logic.
            $user->supportMessages()->get()->each->delete();

            $user->reviews()->delete();
        });
    }

    /**
     * Скоуп для фильтрации по типу пользователя.
     */
    public function scopeFilterUserType($query, $types)
    {
        if (!empty($types)) {
            $query->whereIn('role', $types);
        }
    }

    /**
     * Скоуп для фильтрации по направлениям.
     */
    public function scopeFilterDirects($query, $directs)
    {
        if (!empty($directs)) {
            $query->whereHas('directs', function ($q) use ($directs) {
                $q->whereIn('directs.id', $directs);
            });
        }
    }

    /**
     * Скоуп для фильтрации по предметам.
     */
    public function scopeFilterSubjects($query, $subjects)
    {
        if (!empty($subjects)) {
            $query->whereHas('subjects', function ($q) use ($subjects) {
                $q->whereIn('subjects.id', $subjects);
            });
        }
    }

    /**
     * Скоуп для фильтрации по классам.
     */
    public function scopeFilterGrades($query, $grades)
    {
        if (!empty($grades)) {
            foreach ($grades as $grade) {
                $query->whereJsonContains('grade', $grade);
            }
        }
    }
}
