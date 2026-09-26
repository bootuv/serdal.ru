<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\Direct;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Пользователи: вкладки «Учителя / Ученики / Администраторы», поиск, фильтры, «Показать ещё» и окно «Добавить пользователя».
 * Макеты AdminUsers, AdminUsersAdd. Логика — AdminUserService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Пользователи', 'active' => 'users'])]
class Users extends Component
{
    use AdminScreen;

    private const PAGE = 20;

    #[Url(except: 'teachers')]
    public string $tab = 'teachers';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    // Фильтры учителей
    public string $subject = '';
    public string $direct = '';
    public string $tariff = '';
    public array $grades = [];
    public bool $onboarding = false;

    // Фильтр учеников
    public bool $noTeacher = false;

    public int $limit = self::PAGE;

    // Окно «Добавить пользователя»
    public bool $adding = false;
    public string $role = 'teacher';
    public string $lastName = '';
    public string $firstName = '';
    public string $middleName = '';
    public string $email = '';
    public string $mode = 'link';
    public string $password = '';

    /** Тост после добавления: текст и ссылка на карточку. */
    public ?string $toast = null;

    public ?string $toastUrl = null;

    public function mount(): void
    {
        $this->authorizeAdmin();

        if (! array_key_exists($this->tab, AdminUserService::TABS)) {
            $this->tab = 'teachers';
        }
    }

    public function updated(string $property): void
    {
        // Сменили вкладку, запрос или фильтр — снова первая страница
        if (in_array($property, ['tab', 'search', 'subject', 'direct', 'tariff', 'onboarding', 'noTeacher'], true)) {
            $this->limit = self::PAGE;
        }
        if ($property === 'tab' && ! array_key_exists($this->tab, AdminUserService::TABS)) {
            $this->tab = 'teachers';
        }
        if ($property === 'email') {
            $this->resetValidation('email');
        }
    }

    public function toggleGrade(string $grade): void
    {
        if (! array_key_exists($grade, AdminUserService::GRADES)) {
            return;
        }
        $this->grades = in_array($grade, $this->grades, true)
            ? array_values(array_diff($this->grades, [$grade]))
            : array_values(array_filter(array_map('strval', array_keys(AdminUserService::GRADES)), fn ($g) => in_array($g, [...$this->grades, $grade], true)));
        $this->limit = self::PAGE;
    }

    public function clearGrades(): void
    {
        $this->grades = [];
    }

    public function resetFilters(): void
    {
        $this->reset('subject', 'direct', 'tariff', 'grades', 'onboarding', 'noTeacher', 'search', 'limit');
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    /*
     | Добавить пользователя
     */

    public function openAdd(): void
    {
        $this->resetValidation();
        $this->reset('lastName', 'firstName', 'middleName', 'email', 'password');
        $this->mode = 'link';
        $this->role = match ($this->tab) {
            'students' => 'student',
            'admins' => 'admin',
            default => 'teacher',
        };
        $this->adding = true;
    }

    public function closeAdd(): void
    {
        $this->adding = false;
        $this->resetValidation();
    }

    public function add(AdminUserService $service): void
    {
        $this->email = trim($this->email);
        $this->validate([
            'role' => ['required', Rule::in(['teacher', 'student', 'admin'])],
            'lastName' => ['required', 'string', 'max:255'],
            'firstName' => ['required', 'string', 'max:255'],
            'middleName' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'mode' => ['required', Rule::in(['link', 'pass'])],
            'password' => [$this->mode === 'pass' ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
        ], [
            'email.required' => 'Укажите почту',
            'email.email' => 'Проверьте почту — не хватает @ или адреса после неё',
            'lastName.required' => 'Укажите фамилию',
            'firstName.required' => 'Укажите имя',
            'password.required' => 'Придумайте пароль',
            'password.min' => 'Не короче 8 символов',
        ]);

        $taken = User::whereRaw('lower(email) = ?', [mb_strtolower($this->email)])->first();
        if ($taken) {
            $this->addError('email', 'Эта почта уже занята: ' . $taken->name);

            return;
        }

        $role = ['teacher' => User::ROLE_TUTOR, 'student' => User::ROLE_STUDENT, 'admin' => User::ROLE_ADMIN][$this->role];
        $user = $service->create($role, $this->lastName, $this->firstName, $this->middleName, $this->email, $this->mode === 'link', $this->password);

        $this->adding = false;
        $this->reset('password');
        $this->tab = array_search($role, AdminUserService::TABS, true);
        $who = ['teacher' => 'Учитель добавлен', 'student' => 'Ученик добавлен', 'admin' => 'Администратор добавлен'][$this->role];
        $this->toast = $who . ($this->mode === 'link' ? ', ссылка для входа отправлена' : '');
        $this->toastUrl = self::url($user);
    }

    public function hideToast(): void
    {
        $this->toast = null;
        $this->toastUrl = null;
    }

    public function render(AdminUserService $service)
    {
        $filters = [
            'subject' => $this->subject, 'direct' => $this->direct, 'tariff' => $this->tariff,
            'grades' => $this->grades, 'onboarding' => $this->onboarding, 'noTeacher' => $this->noTeacher,
        ];
        $query = $service->query($this->tab, $this->search, $filters);
        $found = (clone $query)->count();
        $users = $query->limit($this->limit)->get();
        $me = auth()->user();

        $rows = match ($this->tab) {
            'students' => $service->studentRows($users),
            'admins' => $service->adminRows($users, $me),
            default => $service->teacherRows($users),
        };

        $counts = $service->counts();
        $filtered = $this->search !== '' || $this->anyFilter();
        $new = $service->newThisWeek();

        return view('livewire.cabinet.admin.users', [
            'counts' => $counts,
            'sub' => $new ? plural_ru($new, 'новый', 'новых', 'новых') . ' за неделю' : null,
            'rows' => $rows,
            'found' => $found,
            'filtered' => $filtered,
            'anyFilter' => $this->anyFilter(),
            'canMore' => $users->count() < $found,
            'foot' => $filtered
                ? 'Найдено: ' . $found
                : 'Показаны ' . $users->count() . ' из ' . $found . ' · сначала новые',
            'subjects' => $this->tab === 'teachers' ? Subject::orderBy('name')->pluck('name', 'id')->all() : [],
            'directs' => $this->tab === 'teachers' ? Direct::orderBy('name')->pluck('name', 'id')->all() : [],
            'tariffs' => $this->tab === 'teachers' ? $service->tariffOptions() : [],
            'gradeOptions' => AdminUserService::GRADES,
            'roleNote' => ['teacher' => 'Тариф назначите в карточке учителя', 'student' => 'Учитель добавит ученика к себе по почте', 'admin' => 'Получит доступ ко всей админке'][$this->role] ?? null,
        ]);
    }

    private function anyFilter(): bool
    {
        return match ($this->tab) {
            'teachers' => $this->subject !== '' || $this->direct !== '' || $this->tariff !== '' || $this->grades !== [] || $this->onboarding,
            'students' => $this->noTeacher,
            default => false,
        };
    }

    /** Ссылка на карточку пользователя. */
    public static function url(User $user): string
    {
        return route('cabinet.admin.user', ['user' => $user->id]);
    }
}
