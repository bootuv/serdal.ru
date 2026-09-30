<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\User;
use App\Services\StudentProfileService;
use App\Support\HumanDate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Профиль ученика. Макет: «Ученик · Профиль» (docs/design/BRAND.md). Учителя и отзывы — на главной, почта и пароль — Cabinet\Account. */
#[Layout('components.layouts.cabinet', ['title' => 'Профиль', 'active' => null])]
class Profile extends Component
{
    use WithFileUploads;

    // Личные данные. Имя — тремя полями, как при регистрации: полное имя хранится как «Фамилия Имя Отчество»
    public string $last_name = '';
    public string $first_name = '';
    public string $middle_name = '';
    public string $phone = '';
    public string $grade = '';
    public $photo = null;
    public bool $removePhoto = false;

    /** Показать «Изменения сохранены» у кнопки. */
    public bool $saved = false;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user?->role === User::ROLE_STUDENT, 403);

        $this->last_name = (string) $user->last_name;
        $this->first_name = (string) $user->first_name;
        $this->middle_name = (string) $user->middle_name;
        // Старые аккаунты без частей имени — берём полное имя как есть («Фамилия Имя»)
        if ($this->last_name === '' && $this->first_name === '' && $user->name) {
            [$this->last_name, $this->first_name] = array_pad(preg_split('/\s+/u', trim($user->name), 2), 2, '');
        }
        $this->phone = (string) $user->phone;
        $this->grade = (string) StudentProfileService::gradeForForm($user->grade);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['last_name', 'first_name', 'middle_name', 'phone', 'grade', 'photo'], true)) {
            $this->saved = false;
        }

        if ($property === 'photo') {
            $this->removePhoto = false;
            $this->validateOnly('photo');
        }
    }

    public function deletePhoto(): void
    {
        $this->photo = null;
        $this->removePhoto = true;
        $this->saved = false;
    }

    protected function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255', 'regex:/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/'],
            'grade' => ['nullable', Rule::in(array_keys(StudentProfileService::GRADES))],
            'photo' => ['nullable', 'image'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'last_name' => 'фамилия',
            'first_name' => 'имя',
            'middle_name' => 'отчество',
            'phone' => 'телефон',
            'grade' => 'класс',
            'photo' => 'фото',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'last_name' => trim($this->last_name),
            'first_name' => trim($this->first_name),
            'middle_name' => trim($this->middle_name) !== '' ? trim($this->middle_name) : null,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'grade' => StudentProfileService::gradeForStorage($this->grade),
        ];

        if ($this->photo) {
            $data['avatar'] = $this->photo;
        } elseif ($this->removePhoto) {
            $data['avatar'] = null;
        }

        app(StudentProfileService::class)->update(auth()->user(), $data);

        $this->reset('photo', 'removePhoto');
        $this->saved = true;
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.cabinet.student.profile', [
            'user' => $user,
            'since' => HumanDate::month($user->created_at ?? now(), genitive: true),
            'grades' => StudentProfileService::GRADES,
        ]);
    }
}
