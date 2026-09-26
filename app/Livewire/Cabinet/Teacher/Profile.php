<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Direct;
use App\Models\LessonType;
use App\Models\Review;
use App\Models\Subject;
use App\Models\User;
use App\Services\PaymentRecordService;
use App\Services\TeacherProfileService;
use App\Support\RichText;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Профиль и цены учителя. Макет: «Учитель · Профиль и цены» (docs/design/BRAND.md).
 * Профиль — поля и правила как в старом кабинете (Filament App\Pages\EditProfile),
 * цены — как LessonTypeResource, уведомления — PushNotificationToggle.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Профиль и цены', 'active' => null])]
class Profile extends Component
{
    use TeacherScreen;
    use WithFileUploads;

    private const TEL = 'regex:/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/';

    #[Url(except: 'profile')]
    public string $tab = 'profile';

    // Профиль
    public $photo = null;
    public bool $removePhoto = false;
    public string $last_name = '';
    public string $first_name = '';
    public string $middle_name = '';
    public string $password = '';
    public array $subjects = [];
    public array $directs = [];
    public array $grades = [];
    public string $about = '';
    public string $extra_info = '';
    public string $phone = '';
    public string $whatsup = '';
    public string $telegram = '';
    public string $addSubject = '';
    public string $addDirect = '';
    public bool $saved = false;

    // Цены: окно добавления/изменения и подтверждение удаления
    public bool $priceOpen = false;
    public ?int $priceId = null;
    public string $priceType = '';
    public string $pricePayment = LessonType::PAYMENT_PER_LESSON;
    public $price = '';
    public $priceDuration = 60;
    public $priceCount = '';
    public $priceDueDays = 3;
    public $priceDueDay = 5;
    public ?int $deletePriceId = null;

    public function mount(): void
    {
        $user = $this->authorizeTeacher();

        $this->last_name = (string) $user->last_name;
        $this->first_name = (string) $user->first_name;
        $this->middle_name = (string) $user->middle_name;
        $this->subjects = $user->subjects()->pluck('subjects.id')->map(fn ($id) => (int) $id)->all();
        $this->directs = $user->directs()->pluck('directs.id')->map(fn ($id) => (int) $id)->all();
        $this->grades = TeacherProfileService::gradesForForm($user->grade);
        $this->about = (string) $user->about;
        $this->extra_info = (string) $user->extra_info;
        $this->phone = (string) $user->phone;
        $this->whatsup = (string) $user->whatsup;
        $this->telegram = (string) $user->telegram;

        if (! in_array($this->tab, ['profile', 'prices', 'notify'], true)) {
            $this->tab = 'profile';
        }
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['tab', 'saved', 'addSubject', 'addDirect'], true) && ! str_starts_with($property, 'price') && $property !== 'deletePriceId') {
            $this->saved = false;
        }

        if ($property === 'photo') {
            $this->removePhoto = false;
            $this->validateOnly('photo');
        }
    }

    public function updatedAddSubject(string $id): void
    {
        if ($id !== '' && Subject::whereKey((int) $id)->exists() && ! in_array((int) $id, $this->subjects, true)) {
            $this->subjects[] = (int) $id;
            $this->saved = false;
        }
        $this->addSubject = '';
    }

    public function updatedAddDirect(string $id): void
    {
        if ($id !== '' && Direct::whereKey((int) $id)->exists() && ! in_array((int) $id, $this->directs, true)) {
            $this->directs[] = (int) $id;
            $this->saved = false;
        }
        $this->addDirect = '';
    }

    public function removeSubject(int $id): void
    {
        $this->subjects = array_values(array_diff($this->subjects, [$id]));
        $this->saved = false;
    }

    public function removeDirect(int $id): void
    {
        $this->directs = array_values(array_diff($this->directs, [$id]));
        $this->saved = false;
    }

    public function toggleGrade(string $grade): void
    {
        if (! array_key_exists($grade, TeacherProfileService::GRADES)) {
            return;
        }

        $this->grades = in_array($grade, $this->grades, true)
            ? array_values(array_diff($this->grades, [$grade]))
            : TeacherProfileService::gradesForForm([...$this->grades, $grade]);
        $this->saved = false;
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
            'photo' => ['nullable', 'image'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'subjects' => ['array'],
            'subjects.*' => ['integer', Rule::exists('subjects', 'id')],
            'directs' => ['array'],
            'directs.*' => ['integer', Rule::exists('directs', 'id')],
            'grades' => ['array'],
            'grades.*' => [Rule::in(array_map('strval', array_keys(TeacherProfileService::GRADES)))],
            'about' => ['nullable', 'string'],
            'extra_info' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255', self::TEL],
            'whatsup' => ['nullable', 'string', 'max:255', self::TEL],
            'telegram' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'photo' => 'фото',
            'last_name' => 'фамилия',
            'first_name' => 'имя',
            'middle_name' => 'отчество',
            'password' => 'пароль',
            'phone' => 'телефон',
            'whatsup' => 'WhatsApp',
            'telegram' => 'Telegram',
            'price' => 'цена',
            'priceDuration' => 'длительность',
            'priceCount' => 'занятий в неделю',
            'priceDueDays' => 'срок оплаты',
            'priceDueDay' => 'число месяца',
            'priceType' => 'тип занятий',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $user = auth()->user();
        $data = [
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name !== '' ? $this->middle_name : null,
            'password' => $this->password,
            'subjects' => $this->subjects,
            'directs' => $this->directs,
            'grade' => $this->grades,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'whatsup' => $this->whatsup !== '' ? $this->whatsup : null,
            // На публичной странице ник выводится с «@» — храним без него
            'telegram' => ltrim(trim($this->telegram), '@') !== '' ? ltrim(trim($this->telegram), '@') : null,
        ];

        // «Обо мне» и «Образование и опыт» — HTML из редактора
        $data['about'] = RichText::clean($this->about);
        $data['extra_info'] = RichText::clean($this->extra_info);

        if ($this->photo) {
            $data['avatar'] = $this->photo;
        } elseif ($this->removePhoto) {
            $data['avatar'] = null;
        }

        app(TeacherProfileService::class)->update($user, $data);

        $this->reset('password', 'photo', 'removePhoto');
        $this->saved = true;
    }

    /*
     | Цены на занятия (как LessonTypeResource: по одной цене на тип занятий)
     */

    public function createPrice(): void
    {
        abort_unless(LessonType::canCreateFor(auth()->id()), 403);

        $available = LessonType::availableTypesFor(auth()->id());
        $this->resetValidation();
        $this->priceId = null;
        $this->priceType = (string) array_key_first($available);
        $this->pricePayment = $this->priceType === LessonType::TYPE_GROUP ? LessonType::PAYMENT_MONTHLY : LessonType::PAYMENT_PER_LESSON;
        $this->price = '';
        $this->priceDuration = 60;
        $this->priceCount = '';
        $this->priceDueDays = 3;
        $this->priceDueDay = 5;
        $this->priceOpen = true;
    }

    public function editPrice(int $id): void
    {
        $lt = $this->ownPrice($id);

        $this->resetValidation();
        $this->priceId = $lt->id;
        $this->priceType = $lt->type;
        $this->pricePayment = $lt->payment_type ?: LessonType::PAYMENT_PER_LESSON;
        $this->price = $lt->price !== null ? (string) (int) $lt->price : '';
        $this->priceDuration = $lt->duration;
        $this->priceCount = $lt->count_per_week ?? '';
        $this->priceDueDays = $lt->payment_due_days ?? 3;
        $this->priceDueDay = $lt->payment_due_day ?? 5;
        $this->priceOpen = true;
    }

    /** Как в старом кабинете: индивидуальные — оплата за занятие, групповые — за месяц. */
    public function updatedPriceType(string $type): void
    {
        $this->pricePayment = $type === LessonType::TYPE_GROUP ? LessonType::PAYMENT_MONTHLY : LessonType::PAYMENT_PER_LESSON;
    }

    public function closePrice(): void
    {
        $this->priceOpen = false;
        $this->resetValidation();
    }

    public function savePrice(): void
    {
        $userId = (int) auth()->id();
        $lt = $this->priceId ? $this->ownPrice($this->priceId) : null;
        abort_if(! $lt && ! LessonType::canCreateFor($userId), 403);

        $monthly = $this->pricePayment === LessonType::PAYMENT_MONTHLY;
        $data = $this->validate([
            'priceType' => ['required', Rule::in(array_keys(LessonType::availableTypesFor($userId, $lt?->id)))],
            'pricePayment' => ['required', Rule::in([LessonType::PAYMENT_PER_LESSON, LessonType::PAYMENT_MONTHLY])],
            'price' => ['required', 'numeric', 'min:0'],
            'priceDuration' => ['required', 'numeric'],
            'priceCount' => [$monthly ? 'required' : 'nullable', 'numeric'],
            'priceDueDays' => [$monthly ? 'nullable' : 'required', 'numeric', 'min:1', 'max:30'],
            'priceDueDay' => [$monthly ? 'required' : 'nullable', 'numeric', 'min:1', 'max:28'],
        ]);

        $values = [
            'type' => $data['priceType'],
            'payment_type' => $data['pricePayment'],
            'price' => $data['price'],
            'duration' => $data['priceDuration'],
            'count_per_week' => $monthly ? $data['priceCount'] : null,
        ];
        // Срок оплаты — только для выбранного способа (второе поле остаётся со значением по умолчанию, как в старом кабинете)
        $values[$monthly ? 'payment_due_day' : 'payment_due_days'] = $monthly ? $data['priceDueDay'] : $data['priceDueDays'];

        if ($lt) {
            $lt->update($values);
        } else {
            LessonType::create($values + ['user_id' => $userId]);
        }

        $this->priceOpen = false;
        $this->dispatch('toast', message: $lt ? 'Цена сохранена' : 'Цена добавлена');
    }

    public function confirmDeletePrice(): void
    {
        $this->deletePriceId = $this->ownPrice((int) $this->priceId)->id;
        $this->priceOpen = false;
    }

    public function deletePrice(): void
    {
        $this->ownPrice((int) $this->deletePriceId)->delete();
        $this->deletePriceId = null;
        $this->dispatch('toast', message: 'Цена удалена');
    }

    private function ownPrice(int $id): LessonType
    {
        return LessonType::where('user_id', auth()->id())->findOrFail($id);
    }

    public function render()
    {
        $user = auth()->user();
        $subjects = Subject::orderBy('name')->pluck('name', 'id');
        $directs = Direct::orderBy('name')->pluck('name', 'id');

        return view('livewire.cabinet.teacher.profile', [
            'user' => $user,
            'subjectOptions' => $subjects,
            'directOptions' => $directs,
            'gradeOptions' => TeacherProfileService::GRADES,
            'preview' => $this->preview($user, $subjects, $directs),
            'prices' => $user->lessonTypes()->orderByRaw("type = 'group'")->get(),
            'canAddPrice' => LessonType::canCreateFor($user->id),
            'priceTypes' => $this->priceOpen ? LessonType::availableTypesFor($user->id, $this->priceId) : [],
            'blockAfter' => PaymentRecordService::BLOCK_AFTER_LESSONS,
            'groupLimit' => $user->activeSubscription()?->tariff,
            'subscriptionUrl' => Route::has('cabinet.teacher.subscription') ? route('cabinet.teacher.subscription') : url('/tutor/subscription'),
        ]);
    }

    /** Карточка каталога (partials/specialist-item) по текущим значениям формы. */
    private function preview(User $user, $subjects, $directs): array
    {
        $published = Review::where('teacher_id', $user->id)
            ->where('is_rejected', false)
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_STUDENT));
        $reviews = (clone $published)->count();

        $user->loadMissing('lessonTypes');
        $cheapest = $user->cheapestLesson();

        // Как User::subjectsList и displayGrade, но по несохранённой форме
        $subjectNames = collect($this->subjects)->map(fn ($id) => $subjects[$id] ?? null)->filter()->values()
            ->map(fn ($name, $i) => $i > 0 ? mb_strtolower($name) : $name)->implode(', ');
        $gradeUser = new User(['grade' => $this->grades]);

        return [
            'name' => implode(' ', array_filter([$this->last_name, $this->first_name, $this->middle_name])) ?: $user->name,
            'directs' => collect($this->directs)->map(fn ($id) => $directs[$id] ?? null)->filter()->values(),
            'rating' => $reviews > 0 ? number_format((float) (clone $published)->avg('rating'), 1, ',', '') : null,
            'reviews' => $reviews,
            'price' => $cheapest ? ($cheapest->isMonthly() ? '≈ ' : 'от ') . number_format($cheapest->pricePerLesson(), 0, ',', ' ') . ' ₽' : null,
            'subjects' => $subjectNames,
            'grades' => $gradeUser->displayGrade,
            'url' => $user->is_active && $user->username ? route('tutors.show', ['username' => $user->username]) : null,
        ];
    }
}
