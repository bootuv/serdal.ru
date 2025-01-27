<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Subject;
use App\Models\Direct;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Str;
use Illuminate\Database\Eloquent\Builder;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;

class User extends Authenticatable implements FilamentUser
{
    const ROLE_MENTOR = 'mentor';
    
    const ROLE_TUTOR = 'tutor';

    const ROLE_STUDENT = 'student';

    const ROLE_ADMIN = 'admin';

    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
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

                $school = array_filter($this->grade, function($item) {
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
                return match($this->role) {
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
            get: fn () => $this->avatar ? Storage::url($this->avatar) : asset('images/default-avatar.png'),
        );
    }

    public function scopeIsSpecialist(Builder $query): Builder
    {
        return $query->whereIn('role', [User::ROLE_MENTOR, User::ROLE_TUTOR]);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    protected static function booted()
    {
        static::deleting(function ($user) {
            $user->reviews()->delete();
        });
    }

    /**
     * Скоуп для фильтрации по типу пользователя.
     */
    public function scopeFilterUserType($query, $types)
    {
        if (!empty($types)) {
            $query->whereIn('user_type', $types);
        }
    }

    /**
     * Скоуп для фильтрации по направлениям.
     */
    public function scopeFilterDirects($query, $directs)
    {
        if (!empty($directs)) {
            $query->whereHas('directs', function($q) use ($directs) {
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
            $query->whereHas('subjects', function($q) use ($subjects) {
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
            $query->whereIn('grade', $grades);
        }
    }
}
