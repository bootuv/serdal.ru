<?php

namespace App\Livewire;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Auth\Events\Registered;

class RegisterInvitedStudent extends Component
{
    public $first_name;
    public $last_name;
    public $middle_name;
    public $email;
    public $phone;
    public $password;
    public $password_confirmation;
    public $teacher_id;

    public function mount()
    {
        if (!request()->hasValidSignature()) {
            abort(403, 'Ссылка приглашения недействительна или устарела.');
        }

        $this->teacher_id = request()->query('teacher');
    }

    public function register()
    {
        $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Мы не передаем name и username, так как они генерируются автоматически в User::booted()
        $user = User::create([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => Hash::make($this->password),
            'role' => 'student',
        ]);

        // Привязываем ученика к учителю
        if ($this->teacher_id) {
            $teacher = User::find($this->teacher_id);
            if ($teacher) {
                // Используем syncWithoutDetaching чтобы случайно не удалить другие связи, если они есть
                // Предполагаем, что есть отношение students() у учителя (belongsToMany)
                $teacher->students()->syncWithoutDetaching([$user->id]);
            }
        }

        event(new Registered($user));

        auth()->guard('web')->login($user);
        session()->regenerate();

        return redirect()->to('/student'); // Перенаправляем в панель ученика
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        return view('livewire.register-invited-student');
    }
}
