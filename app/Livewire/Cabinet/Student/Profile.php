<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\MeetingSession;
use App\Models\User;
use App\Services\StudentProfileService;
use App\Services\StudentTeachersService;
use App\Support\HumanDate;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Профиль ученика. Макеты: «Ученик · Профиль», «Отзыв: оставить / изменить / состояния» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Профиль', 'active' => null])]
class Profile extends Component
{
    use WithFileUploads;

    // Личные данные — поля и правила как в старом кабинете (Filament Student\Pages\Profile)
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $grade = '';
    public string $password = '';
    public $photo = null;

    /** Показать «Изменения сохранены» у кнопки. */
    public bool $saved = false;

    // Окно отзыва
    public ?int $reviewTeacherId = null;
    public int $rating = 5;
    public string $reviewText = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user?->role === User::ROLE_STUDENT, 403);

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
        $this->grade = (string) StudentProfileService::gradeForForm($user->grade);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['name', 'email', 'phone', 'grade', 'password', 'photo'], true)) {
            $this->saved = false;
        }

        if ($property === 'photo') {
            $this->validateOnly('photo');
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore(auth()->id())],
            'phone' => ['nullable', 'string', 'max:255', 'regex:/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/'],
            'grade' => ['nullable', Rule::in(array_keys(StudentProfileService::GRADES))],
            'password' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'имя',
            'email' => 'почта',
            'phone' => 'телефон',
            'grade' => 'класс',
            'password' => 'пароль',
            'photo' => 'фото',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'grade' => StudentProfileService::gradeForStorage($this->grade),
            'password' => $this->password,
        ];

        if ($this->photo) {
            $data['avatar'] = $this->photo;
        }

        app(StudentProfileService::class)->update(auth()->user(), $data);

        $this->reset('password', 'photo');
        $this->saved = true;
    }

    public function openReview(int $teacherId): void
    {
        [$teacher] = $this->reviewable($teacherId);

        $review = $this->teachersService()->review(auth()->id(), $teacher->id);
        $this->rating = $review?->rating ?? 5;
        $this->reviewText = (string) ($review?->text ?? '');
        $this->reviewTeacherId = $teacher->id;
        $this->resetValidation(['rating', 'reviewText']);
    }

    public function closeReview(): void
    {
        $this->reviewTeacherId = null;
        $this->resetValidation(['rating', 'reviewText']);
    }

    public function saveReview(): void
    {
        [$teacher] = $this->reviewable((int) $this->reviewTeacherId);

        $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'reviewText' => ['required', 'string'],
        ], [
            'reviewText.required' => 'Напишите хотя бы пару предложений',
        ]);

        $isNew = ! $this->teachersService()->review(auth()->id(), $teacher->id);
        $this->teachersService()->saveReview(auth()->user(), $teacher, $this->rating, trim($this->reviewText));

        $this->reviewTeacherId = null;
        $this->dispatch('toast', message: $isNew ? 'Спасибо! Отзыв опубликован' : 'Отзыв обновлён');
    }

    public function render()
    {
        $user = auth()->user();
        $teachers = $this->teacherRows($user->id);

        return view('livewire.cabinet.student.profile', [
            'user' => $user,
            'since' => HumanDate::month($user->created_at ?? now(), genitive: true),
            'grades' => StudentProfileService::GRADES,
            'teachers' => $teachers,
            'reviewing' => $this->reviewTeacherId ? $teachers->firstWhere('id', $this->reviewTeacherId) : null,
            'stars' => ['', '1 — очень плохо', '2 — плохо', '3 — нормально', '4 — хорошо', '5 — отлично'],
        ]);
    }

    /** Текущие, затем бывшие учителя — как в виджетах старого кабинета. */
    private function teacherRows(int $studentId): Collection
    {
        $svc = $this->teachersService();

        $current = $svc->currentTeachers($studentId)->with('subjects:id,name')->orderBy('name')->get()
            ->map(fn (User $t) => $this->teacherRow($studentId, $t, $svc->lessonsWithCurrentTeacher($studentId, $t), true));
        $former = $svc->formerTeachers($studentId)->with('subjects:id,name')->orderBy('name')->get()
            ->map(fn (User $t) => $this->teacherRow($studentId, $t, $svc->lessonsWithFormerTeacher($studentId, $t), false));

        return $current->concat($former)->values();
    }

    private function teacherRow(int $studentId, User $teacher, Collection $lessons, bool $current): array
    {
        $svc = $this->teachersService();
        $review = $svc->review($studentId, $teacher->id);
        $count = $lessons->count();
        $dates = $lessons->map(fn (MeetingSession $s) => $s->started_at ?? $s->ended_at ?? $s->created_at)->filter()->sort();

        $facts = match (true) {
            ! $current => $dates->isNotEmpty() ? 'занимались до ' . HumanDate::month($dates->last(), genitive: true) : null,
            $count > 0 => plural_ru($count, 'занятие', 'занятия', 'занятий') . ($dates->isNotEmpty() ? ' с ' . HumanDate::month($dates->first(), genitive: true) : ''),
            default => 'занятий пока не было',
        };

        return [
            'id' => $teacher->id,
            'name' => $teacher->name,
            'teacher' => $teacher,
            'current' => $current,
            'sub' => implode(' · ', array_filter([$teacher->subjects->pluck('name')->join(', '), $facts])),
            'review' => $review,
            'rejected' => $svc->hasRejectedReview($studentId, $teacher->id),
            'canReview' => $svc->canReview($studentId, $teacher->id, $count),
            'lessons' => $count,
            'chat' => $current ? $svc->chatUrl($studentId, $teacher->id) : null,
            'publicUrl' => $teacher->is_active && $teacher->username ? route('tutors.show', ['username' => $teacher->username]) : null,
        ];
    }

    /** Учитель, которому ученик может оставить отзыв; иначе — 403. Возвращает [учитель, занятий]. */
    private function reviewable(int $teacherId): array
    {
        $studentId = (int) auth()->id();
        $svc = $this->teachersService();

        if ($teacher = $svc->currentTeachers($studentId)->find($teacherId)) {
            $count = $svc->lessonsWithCurrentTeacher($studentId, $teacher)->count();
        } elseif ($teacher = $svc->formerTeachers($studentId)->find($teacherId)) {
            $count = $svc->lessonsWithFormerTeacher($studentId, $teacher)->count();
        } else {
            abort(403);
        }

        abort_unless($svc->canReview($studentId, $teacher->id, $count), 403);

        return [$teacher, $count];
    }

    private function teachersService(): StudentTeachersService
    {
        return app(StudentTeachersService::class);
    }
}
