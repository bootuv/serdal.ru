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

class User extends Authenticatable
{
    const ROLE_MENTOR = 'mentor';
    
    const ROLE_TUTOR = 'tutor';

    const ROLE_STUDENT = 'student';

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
                $result = [];

                if ($this->grade['preschool']) {
                    $result[] = 'дошкольники';
                }

                if ($this->grade['school']) {
                    $result[] = format_grade_range($this->grade['school']);
                }

                if ($this->grade['adults']) {
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
                };
            },
        );
    }

    public function scopeIsSpecialist(Builder $query): Builder
    {
        return $query->where('role', '!=', User::ROLE_STUDENT);
    }

}
