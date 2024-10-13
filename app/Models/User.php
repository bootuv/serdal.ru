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

class User extends Authenticatable
{
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
}
