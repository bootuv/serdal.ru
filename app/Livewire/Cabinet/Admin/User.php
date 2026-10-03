<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\Direct;
use App\Models\LessonGrant;
use App\Models\LessonType;
use App\Models\MeetingSession;
use App\Models\Review;
use App\Models\Room;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User as UserModel;
use App\Services\AdminUserService;
use App\Services\StudentPerformanceService;
use App\Services\StudentProfileService;
use App\Services\StudentScheduleService;
use App\Services\SubscriptionService;
use App\Services\TeacherProfileService;
use App\Services\TeacherScheduleService;
use App\Support\HumanDate;
use App\Support\Money;
use App\Support\RichText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Карточка пользователя в админке: учитель (обзор с тарифом, профиль, цены, ученики и занятия),
 * ученик (учителя и занятия, активность, контакты, профиль), администратор (профиль, последний вход).
 * Макеты AdminUserTeacher, AdminUserTeacherTariff, AdminUserStudent. Действия — AdminUserService,
 * тариф — SubscriptionService::assignByAdmin.
 * Оплату занятий ученик → учитель здесь не показываем (решение владельца).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Пользователь', 'active' => 'users'])]
class User extends Component
{
    use AdminScreen;
    use WithFileUploads;

    private const TEL = 'regex:/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/';

    /** Сроки в окне «Назначить тариф» → дней (null — бессрочно, custom — своё число). */
    private const TERMS = ['month' => 30, 'q' => 90, 'year' => 365, 'custom' => null, 'forever' => null];

    #[Locked]
    public UserModel $person;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    /** Ученик: какие занятия показать в «Учителя и занятия». */
    public string $view = 'next';

    /** Открытое окно: tariff | lessons | block | delete | price. */
    public ?string $modal = null;

    // Окно «Назначить тариф»
    public ?int $tariffId = null;
    public string $term = 'month';
    public $customDays = 45;
    public bool $free = false;
    public string $note = '';

    // Окно «Добавить занятия»
    public $grantCount = 1;
    public string $grantNote = '';

    // Профиль
    public $photo = null;
    public bool $removePhoto = false;
    public string $last_name = '';
    public string $first_name = '';
    public string $middle_name = '';
    public string $email = '';
    public string $phone = '';
    public string $whatsup = '';
    public string $telegram = '';
    public array $subjects = [];
    public array $directs = [];
    public array $grades = [];
    public string $grade = '';
    public string $about = '';
    public string $extra_info = '';
    public string $addSubject = '';
    public string $addDirect = '';

    // Окно цены
    public ?int $priceId = null;
    public string $pricePayment = LessonType::PAYMENT_PER_LESSON;
    public $price = '';
    public $priceDuration = 60;
    public $priceCount = '';

    /** {user} — id пользователя (у старых ссылок — username). */
    public function mount(UserModel|string|int $user): void
    {
        $this->authorizeAdmin();

        if (! $user instanceof UserModel) {
            $key = (string) $user;
            $user = (ctype_digit($key) ? UserModel::find((int) $key) : null) ?? UserModel::where('username', $key)->first();
        }
        abort_unless($user, 404);

        $this->person = $user;
        $this->fillProfile();

        if (! in_array($this->tab, $this->tabs(), true)) {
            $this->tab = 'overview';
        }
    }

    private function tabs(): array
    {
        return match ($this->person->role) {
            UserModel::ROLE_TUTOR => ['overview', 'profile', 'prices', 'people'],
            UserModel::ROLE_STUDENT => ['overview', 'profile'],
            default => ['overview'],
        };
    }

    private function fillProfile(): void
    {
        $u = $this->person;
        $this->last_name = (string) $u->last_name;
        $this->first_name = (string) $u->first_name;
        $this->middle_name = (string) $u->middle_name;
        $this->email = (string) $u->email;
        $this->phone = (string) $u->phone;
        $this->whatsup = (string) $u->whatsup;
        $this->telegram = (string) $u->telegram;
        $this->about = (string) $u->about;
        $this->extra_info = (string) $u->extra_info;

        if ($u->role === UserModel::ROLE_TUTOR) {
            $this->subjects = $u->subjects()->pluck('subjects.id')->map(fn ($id) => (int) $id)->all();
            $this->directs = $u->directs()->pluck('directs.id')->map(fn ($id) => (int) $id)->all();
            $this->grades = TeacherProfileService::gradesForForm($u->grade);
        } elseif ($u->role === UserModel::ROLE_STUDENT) {
            $this->grade = (string) StudentProfileService::gradeForForm($u->grade);
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'tab' && ! in_array($this->tab, $this->tabs(), true)) {
            $this->tab = 'overview';
        }
        if ($property === 'photo') {
            $this->removePhoto = false;
            $this->validateOnly('photo', ['photo' => ['nullable', 'image']]);
        }
    }

    private function service(): AdminUserService
    {
        return app(AdminUserService::class);
    }

    private function isTeacher(): bool
    {
        return $this->person->role === UserModel::ROLE_TUTOR;
    }

    private function isSelf(): bool
    {
        return $this->person->id === auth()->id();
    }

    public function closeModal(): void
    {
        $this->modal = null;
        $this->resetValidation();
    }

    /*
     | «⋯»: скрыть из каталога, заблокировать, удалить
     */

    public function toggleHidden(): void
    {
        abort_unless($this->isTeacher(), 404);
        $hide = (bool) $this->person->is_active;
        $this->service()->setHidden($this->person, $hide);
        $this->dispatch('toast', message: $hide ? 'Страница учителя скрыта из каталога' : 'Страница учителя снова в каталоге');
    }

    public function openBlock(): void
    {
        abort_if($this->isSelf(), 403);
        $this->modal = 'block';
    }

    public function block(): void
    {
        $this->service()->setBlocked($this->person, true, auth()->user());
        $this->modal = null;
        $this->dispatch('toast', message: $this->person->name . ' — вход закрыт');
    }

    public function unblock(): void
    {
        $this->service()->setBlocked($this->person, false, auth()->user());
        $this->dispatch('toast', message: $this->person->name . ' — вход снова открыт');
    }

    public function openDelete(): void
    {
        abort_if($this->isSelf(), 403);
        $this->modal = 'delete';
    }

    public function delete()
    {
        $name = $this->person->name;
        $this->service()->delete($this->person, auth()->user());
        session()->flash('toast', $name . ' — пользователь удалён');

        return $this->redirect(route('cabinet.admin.users', $this->backTab()), navigate: false);
    }

    private function backTab(): array
    {
        return match ($this->person->role) {
            UserModel::ROLE_STUDENT => ['tab' => 'students'],
            UserModel::ROLE_ADMIN => ['tab' => 'admins'],
            default => [],
        };
    }

    /*
     | Тариф учителя
     */

    public function openTariff(): void
    {
        abort_unless($this->isTeacher(), 404);
        $current = $this->person->activeSubscription();
        $this->resetValidation();
        // Без тарифа — предлагаем тариф, который учитель выбрал в заявке, иначе первый платный
        $this->tariffId = $current?->tariff_id
            ?? Tariff::active()->whereKey($this->person->desired_tariff_id)->value('id')
            ?? Tariff::active()->where('price', '>', 0)->value('id')
            ?? Tariff::active()->value('id');
        $this->term = $current && ! $current->ends_at && ! $current->tariff->isFree() ? 'forever' : 'month';
        $this->customDays = 45;
        $this->free = (bool) $current?->isComplimentary();
        $this->note = '';
        $this->modal = 'tariff';
    }

    public function assignTariff(): void
    {
        abort_unless($this->isTeacher(), 404);
        $this->validate([
            'tariffId' => ['required', Rule::exists('tariffs', 'id')->whereNull('deleted_at')],
            'term' => ['required', Rule::in(array_keys(self::TERMS))],
            'customDays' => [$this->term === 'custom' ? 'required' : 'nullable', 'integer', 'min:1', 'max:3650'],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['tariffId.required' => 'Выберите тариф', 'customDays.required' => 'Укажите срок в днях'], ['customDays' => 'срок']);

        $tariff = Tariff::findOrFail($this->tariffId);
        $days = $this->term === 'custom' ? (int) $this->customDays : self::TERMS[$this->term];

        SubscriptionService::assignByAdmin($this->person, $tariff, auth()->user(),
            days: $days, unlimited: $this->term === 'forever', free: $this->free, note: $this->note);

        $this->modal = null;
        $this->tab = 'overview';
        $this->dispatch('toast', message: 'Тариф «' . $tariff->name . '» назначен. Учитель получит уведомление');
    }

    /*
     | Занятия от администрации — на баланс, работают и без действующего тарифа
     */

    public function openLessons(): void
    {
        abort_unless($this->isTeacher(), 404);
        $this->resetValidation();
        $this->grantCount = 1;
        $this->grantNote = '';
        $this->modal = 'lessons';
    }

    public function grantLessons(): void
    {
        abort_unless($this->isTeacher(), 404);
        $this->validate([
            'grantCount' => ['required', 'integer', 'min:1', 'max:100'],
            'grantNote' => ['nullable', 'string', 'max:200'],
        ], ['grantCount.required' => 'Укажите, сколько занятий добавить'], ['grantCount' => 'количество', 'grantNote' => 'комментарий']);

        $n = (int) $this->grantCount;
        SubscriptionService::grantLessonsByAdmin($this->person, $n, auth()->user(), $this->grantNote);
        $this->person->refresh();

        $this->modal = null;
        $this->dispatch('toast', message: '+' . plural_ru($n, 'занятие', 'занятия', 'занятий') . ' на балансе учителя. Учитель получит уведомление');
    }

    /*
     | Профиль
     */

    public function updatedAddSubject(string $id): void
    {
        if ($id !== '' && Subject::whereKey((int) $id)->exists() && ! in_array((int) $id, $this->subjects, true)) {
            $this->subjects[] = (int) $id;
        }
        $this->addSubject = '';
    }

    public function updatedAddDirect(string $id): void
    {
        if ($id !== '' && Direct::whereKey((int) $id)->exists() && ! in_array((int) $id, $this->directs, true)) {
            $this->directs[] = (int) $id;
        }
        $this->addDirect = '';
    }

    public function removeSubject(int $id): void
    {
        $this->subjects = array_values(array_diff($this->subjects, [$id]));
    }

    public function removeDirect(int $id): void
    {
        $this->directs = array_values(array_diff($this->directs, [$id]));
    }

    public function toggleGrade(string $grade): void
    {
        if (! array_key_exists($grade, TeacherProfileService::GRADES)) {
            return;
        }
        $this->grades = in_array($grade, $this->grades, true)
            ? array_values(array_diff($this->grades, [$grade]))
            : TeacherProfileService::gradesForForm([...$this->grades, $grade]);
    }

    public function deletePhoto(): void
    {
        $this->photo = null;
        $this->removePhoto = true;
    }

    public function saveProfile(): void
    {
        $u = $this->person;
        $rules = [
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($u->id)],
            'phone' => ['nullable', 'string', 'max:255', self::TEL],
        ];
        if ($u->role === UserModel::ROLE_TUTOR) {
            $rules += [
                'photo' => ['nullable', 'image'],
                'whatsup' => ['nullable', 'string', 'max:255', self::TEL],
                'telegram' => ['nullable', 'string', 'max:255'],
                'subjects.*' => ['integer', Rule::exists('subjects', 'id')],
                'directs.*' => ['integer', Rule::exists('directs', 'id')],
                'grades.*' => [Rule::in(array_map('strval', array_keys(TeacherProfileService::GRADES)))],
                'about' => ['nullable', 'string'],
                'extra_info' => ['nullable', 'string'],
            ];
        }
        if ($u->role === UserModel::ROLE_STUDENT) {
            $rules['grade'] = ['nullable', Rule::in(array_map('strval', array_keys(StudentProfileService::GRADES)))];
        }
        $this->email = trim($this->email);
        $this->validate($rules, ['email.unique' => 'Эта почта уже занята'], [
            'last_name' => 'фамилия', 'first_name' => 'имя', 'middle_name' => 'отчество', 'email' => 'почта',
            'phone' => 'телефон', 'whatsup' => 'WhatsApp', 'telegram' => 'Telegram', 'photo' => 'фото',
        ]);

        $data = [
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name !== '' ? $this->middle_name : null,
            'email' => $this->email,
            'phone' => $this->phone !== '' ? $this->phone : null,
        ];

        if ($u->role === UserModel::ROLE_TUTOR) {
            $data += [
                'subjects' => $this->subjects,
                'directs' => $this->directs,
                'grade' => $this->grades,
                'whatsup' => $this->whatsup !== '' ? $this->whatsup : null,
                'telegram' => ltrim(trim($this->telegram), '@') !== '' ? ltrim(trim($this->telegram), '@') : null,
                'about' => RichText::clean($this->about),
                'extra_info' => RichText::clean($this->extra_info),
            ];
            if ($this->photo) {
                $data['avatar'] = $this->photo;
            } elseif ($this->removePhoto) {
                $data['avatar'] = null;
            }
            app(TeacherProfileService::class)->update($u, $data);
            $this->reset('photo', 'removePhoto');
        } elseif ($u->role === UserModel::ROLE_STUDENT) {
            $data['grade'] = StudentProfileService::gradeForStorage($this->grade);
            app(StudentProfileService::class)->update($u, $data);
        } else {
            $u->update($data);
        }

        $this->person->refresh();
        $this->dispatch('toast', message: 'Профиль сохранён');
    }

    public function sendReset(): void
    {
        $sent = $this->service()->sendPasswordLink($this->person);
        $sent
            ? $this->dispatch('toast', message: 'Ссылка отправлена на ' . $this->person->email)
            : $this->dispatch('toast', message: 'Ссылку уже отправили — повторить можно через минуту', tone: 'danger');
    }

    /*
     | Цены учителя (базовые, как в профиле учителя, без условий оплаты)
     */

    public function editPrice(int $id): void
    {
        $lt = $this->ownPrice($id);
        $this->resetValidation();
        $this->priceId = $lt->id;
        $this->pricePayment = $lt->payment_type ?: LessonType::PAYMENT_PER_LESSON;
        $this->price = $lt->price !== null ? (string) (int) $lt->price : '';
        $this->priceDuration = $lt->duration;
        $this->priceCount = $lt->count_per_week ?? 2;
        $this->modal = 'price';
    }

    public function savePrice(): void
    {
        $lt = $this->ownPrice((int) $this->priceId);
        $monthly = $this->pricePayment === LessonType::PAYMENT_MONTHLY;
        $data = $this->validate([
            'pricePayment' => ['required', Rule::in([LessonType::PAYMENT_PER_LESSON, LessonType::PAYMENT_MONTHLY])],
            'price' => ['required', 'numeric', 'min:0'],
            'priceDuration' => ['required', 'integer', 'min:1'],
            'priceCount' => [$monthly ? 'required' : 'nullable', 'integer', 'min:1'],
        ], [], ['price' => 'цена', 'priceDuration' => 'длительность', 'priceCount' => 'занятий в неделю']);

        $lt->update([
            'payment_type' => $data['pricePayment'],
            'price' => $data['price'],
            'duration' => $data['priceDuration'],
            'count_per_week' => $monthly ? $data['priceCount'] : null,
        ]);

        $this->modal = null;
        $this->dispatch('toast', message: 'Цены сохранены');
    }

    private function ownPrice(int $id): LessonType
    {
        return LessonType::where('user_id', $this->person->id)->findOrFail($id);
    }

    /*
     | Экран
     */

    public function render()
    {
        $u = $this->person;
        $data = [
            'u' => $u,
            'isSelf' => $this->isSelf(),
            'since' => 'на Serdal с ' . HumanDate::date($u->created_at),
            'backUrl' => route('cabinet.admin.users', $this->backTab()),
            'impact' => $this->modal === 'delete' ? $this->service()->deletionImpact($u) : [],
            'seen' => $u->last_login_at ? HumanDate::at($u->last_login_at) : 'ещё не входил',
        ];

        $data += match ($u->role) {
            UserModel::ROLE_TUTOR => $this->teacherData($u),
            UserModel::ROLE_STUDENT => $this->studentData($u),
            default => [],
        };

        return view('livewire.cabinet.admin.user', $data)->title($u->name);
    }

    private function link(string $name, array $params = []): ?string
    {
        return Route::has('cabinet.admin.' . $name) ? route('cabinet.admin.' . $name, $params) : null;
    }

    /** «Написать»: чат поддержки с этим человеком (создаём, если его ещё не было). До готовности новой поддержки — старая админка. */
    public function write()
    {
        abort_if($this->person->role === UserModel::ROLE_ADMIN, 404);
        $chat = \App\Models\SupportChat::getOrCreateForUser($this->person);

        return $this->redirect(route('cabinet.admin.support', ['chat' => $chat->id]));
    }

    private function teacherData(UserModel $u): array
    {
        $studentsCount = DB::table('teacher_student')->where('teacher_id', $u->id)->count();
        $data = [
            'catalogUrl' => $u->username ? route('tutors.show', ['username' => $u->username]) : null,
            'studentsCount' => $studentsCount,
            'tabItems' => ['overview' => 'Обзор', 'profile' => 'Профиль', 'prices' => 'Цены', 'people' => 'Ученики и занятия'],
            'todayLessons' => $this->modal === 'block' ? app(TeacherScheduleService::class)->events($u->id, now(), now()->endOfDay())
                ->filter(fn ($e) => $e['end']->isFuture())->map(fn ($e) => $e['start']->format('H:i'))->values()->all() : [],
        ];

        if ($this->tab === 'overview') {
            $data += $this->teacherOverview($u, $studentsCount);
        } elseif ($this->tab === 'profile') {
            $data += [
                'subjectOptions' => Subject::orderBy('name')->pluck('name', 'id'),
                'directOptions' => Direct::orderBy('name')->pluck('name', 'id'),
                'gradeOptions' => TeacherProfileService::GRADES,
            ];
        } elseif ($this->tab === 'prices') {
            $data['prices'] = $u->lessonTypes()->orderByRaw("type = 'group'")->get();
        } else {
            $data += $this->teacherPeople($u, $studentsCount);
        }

        if ($this->modal === 'tariff') {
            $data['tariffs'] = Tariff::active()->get();
            $data['current'] = $u->activeSubscription();
        }

        return $data;
    }

    private function teacherOverview(UserModel $u, int $studentsCount): array
    {
        $sub = $u->activeSubscription();
        $tariff = $sub?->tariff;
        $summary = $tariff ? SubscriptionService::teacherSummary($u) : null;
        $free = $tariff?->isFree();
        $complimentary = $sub?->isComplimentary();

        $tar = null;
        if ($tariff) {
            $daysLeft = $sub->ends_at ? (int) max(0, ceil(now()->diffInHours($sub->ends_at, false) / 24)) : null;
            $tar = [
                'name' => $tariff->name,
                'term' => match (true) {
                    $free || ! $sub->ends_at => 'Бессрочно' . ($complimentary ? ' · предоставлен бесплатно' : ''),
                    default => ($complimentary ? 'Действует' : 'Оплачен') . ' до ' . HumanDate::date($sub->ends_at) . ' · осталось ' . plural_ru($daysLeft, 'день', 'дня', 'дней')
                        . ($complimentary ? ' · предоставлен бесплатно' : ''),
                },
                'price' => $free || $complimentary ? 'Бесплатно' : Money::format((int) $sub->price),
                'per' => $free || $complimentary ? null : ((int) ($sub->payments()->latest()->value('period_days') ?? 30) >= 365 ? 'в год' : 'в месяц'),
                'limit' => $summary['limit'],
                'used' => $summary['limit'] !== null ? $summary['used'] : SubscriptionService::lessonsUsedThisPeriod($u),
                'resets' => $summary['resets'],
                'people' => $tariff->max_participants ? 'до ' . $tariff->max_participants : 'без ограничений',
                'length' => $tariff->max_duration_minutes ? 'до ' . plural_ru($tariff->max_duration_minutes, 'минуты', 'минут', 'минут') : 'без ограничений',
                'rec' => $tariff->recording_retention_days ? 'хранятся ' . plural_ru($tariff->recording_retention_days, 'день', 'дня', 'дней') : 'не сохраняются',
                'renew' => match (true) {
                    $free || $complimentary || ! $sub->ends_at => 'не нужно',
                    $u->auto_renew && $u->yookassa_payment_method_id => 'автоматически' . ($u->payment_method_title ? ', ' . $u->payment_method_title : ''),
                    default => 'учитель оплатит сам',
                },
                'note' => $sub->comment ? $sub->comment . ' · ' . HumanDate::day($sub->starts_at) : null,
            ];
        }
        $lastEnded = $tariff ? null : $u->subscriptions()->whereNotNull('ends_at')->where('ends_at', '<=', now())->latest('ends_at')->first();

        $payments = SubscriptionPayment::where('user_id', $u->id)->with('tariff')->latest()->take(5)->get()
            ->map(function (SubscriptionPayment $p) {
                $binding = ! empty($p->meta['card_binding']);

                return [
                    'id' => $p->id,
                    'title' => $binding ? 'Привязка карты' : ($p->isExtraLessons() ? 'Дополнительные занятия' : 'Тариф «' . $p->tariff?->name . '»'),
                    'meta' => HumanDate::date($p->created_at) . ($binding ? ' · проверочный платёж' : ($p->isExtraLessons() ? ' · ' . plural_ru((int) $p->extra_lessons, 'занятие', 'занятия', 'занятий') : '')),
                    'badge' => match ($p->status) {
                        SubscriptionPayment::STATUS_FAILED => ['Не прошёл', 'danger'],
                        SubscriptionPayment::STATUS_REFUNDED => ['Вернули', 'neutral'],
                        SubscriptionPayment::STATUS_PENDING => ['Ждёт оплаты', 'neutral'],
                        default => null,
                    },
                    'muted' => $p->status === SubscriptionPayment::STATUS_FAILED,
                    'amount' => Money::format((int) $p->amount),
                ];
            });

        // Докупленные и начисленные администрацией — одним списком, новые сверху
        $extras = SubscriptionPayment::where('user_id', $u->id)->where('status', SubscriptionPayment::STATUS_PAID)
            ->where('extra_lessons', '>', 0)->latest()->take(5)->get()
            ->map(fn (SubscriptionPayment $p) => [
                'at' => $p->paid_at ?? $p->created_at,
                'title' => plural_ru((int) $p->extra_lessons, 'занятие', 'занятия', 'занятий'),
                'meta' => HumanDate::date($p->paid_at ?? $p->created_at),
                'amount' => Money::format((int) $p->amount),
            ])
            ->concat(LessonGrant::where('user_id', $u->id)->with('admin:id,name')->latest()->take(5)->get()
                ->map(fn (LessonGrant $g) => [
                    'at' => $g->created_at,
                    'title' => plural_ru($g->lessons, 'занятие', 'занятия', 'занятий'),
                    'meta' => HumanDate::date($g->created_at) . ' · добавил ' . ($g->admin?->name ?? 'администратор') . ($g->note ? ' · ' . $g->note : ''),
                    'amount' => 'Бесплатно',
                ]))
            ->sortByDesc('at')->take(5)->values();

        $reviews = Review::where('teacher_id', $u->id)->where('is_rejected', false)->whereHas('user', fn ($q) => $q->where('role', UserModel::ROLE_STUDENT));
        $reviewsCount = (clone $reviews)->count();
        $groups = Room::where('user_id', $u->id)->where('type', 'group')->pluck('name');
        $referrals = $u->referrals()->count();

        return [
            'tar' => $tar,
            'noTariffNote' => $lastEnded ? 'Тариф «' . $lastEnded->tariff?->name . '» закончился ' . HumanDate::date($lastEnded->ends_at) : 'Тариф ещё не назначали',
            'payments' => $payments,
            'paymentsUrl' => $this->link('payments', ['q' => $u->email, 'period' => 'all']),
            'extraBalance' => (int) $u->extra_lessons_balance,
            'extras' => $extras,
            'activity' => array_values(array_filter([
                ['Последний вход', $u->last_login_at ? HumanDate::at($u->last_login_at) : 'ещё не входил', null],
                ['Ученики', $studentsCount ? $studentsCount . ($groups->isNotEmpty() ? ' · ' . ($groups->count() === 1 ? 'группа «' . $groups->first() . '»' : plural_ru($groups->count(), 'группа', 'группы', 'групп')) : '') : 'пока нет', null],
                ['Занятий за 30 дней', (string) MeetingSession::where('user_id', $u->id)->where('status', 'completed')->where('started_at', '>=', now()->subDays(30))->count(), null],
                ['Отзывы', $reviewsCount ? number_format((float) (clone $reviews)->avg('rating'), 1, ',', '') . ' · ' . plural_ru($reviewsCount, 'отзыв', 'отзыва', 'отзывов') : 'пока нет', null],
                $referrals ? ['Пригласил учителей', (string) $referrals, $this->link('referrals', ['q' => $u->email])] : null,
            ])),
        ];
    }

    private function teacherPeople(UserModel $u, int $studentsCount): array
    {
        $rooms = Room::where('user_id', $u->id)->with('participants:id,name,avatar')->get();
        $pivot = DB::table('teacher_student')->where('teacher_id', $u->id)->pluck('created_at', 'student_id');
        $students = $u->students()->orderBy('name')->get()->map(function (UserModel $s) use ($rooms, $pivot) {
            $own = $rooms->filter(fn (Room $r) => $r->participants->contains('id', $s->id));
            $parts = $own->map(fn (Room $r) => $r->type === 'group' || $r->participants->count() > 1 ? 'группа «' . $r->name . '»' : $r->name)->unique()->take(2)->implode(', ');

            return [
                'user' => $s,
                'sub' => $parts !== '' ? Str::ucfirst($parts) : 'с ' . HumanDate::date(\Illuminate\Support\Carbon::parse($pivot[$s->id] ?? $s->created_at)) . ' · занятий пока нет',
            ];
        });

        $next = app(TeacherScheduleService::class)->events($u->id, now(), now()->addDays(30))
            ->filter(fn ($e) => $e['end']->isFuture())->take(4)
            ->map(function ($e) use ($rooms) {
                $room = $rooms->firstWhere('id', $e['room_id']);
                $n = $room?->participants->count() ?? 0;

                return [
                    'when' => Str::ucfirst(HumanDate::at($e['start'])),
                    'sub' => $room && $n > 1 ? '«' . $room->name . '» · ' . plural_ru($n, 'ученик', 'ученика', 'учеников') : ($room && $n === 1 ? $room->participants->first()->name . ' · ' . mb_strtolower($room->name) : $e['title']),
                    'href' => $this->link('lesson', ['room' => $e['room_id']]),
                ];
            })->values();

        $sessions = MeetingSession::where('user_id', $u->id)->where('status', 'completed')->where('started_at', '>=', now()->subDays(30))->get();
        $missed = $sessions->sum(function (MeetingSession $s) {
            $a = TeacherScheduleService::attendance($s);

            return max(0, $a['total'] - $a['attended']);
        });
        $cancelled = app(TeacherScheduleService::class)->cancelled($u->id, now()->subDays(30))->count();

        return [
            'students' => $students,
            'peopleSub' => $studentsCount ? plural_ru($studentsCount, 'ученик', 'ученика', 'учеников') : null,
            'next' => $next,
            'lessonsUrl' => $this->link('lessons', ['teacher' => $u->id]),
            'sessionsUrl' => $this->link('lessons', ['tab' => 'sessions', 'teacher' => $u->id]),
            'past' => [
                ['За 30 дней', plural_ru($sessions->count(), 'занятие', 'занятия', 'занятий')],
                ['Ученики не пришли', $missed ? plural_ru($missed, 'раз', 'раза', 'раз') : 'ни разу'],
                ['Отменено', plural_ru($cancelled, 'занятие', 'занятия', 'занятий')],
            ],
        ];
    }

    private function studentData(UserModel $u): array
    {
        $teachers = $u->teachers()->withPivot('created_at')->orderBy('name')->get();
        $data = [
            'teachers' => $teachers,
            'facts' => array_values(array_filter([
                AdminUserService::studentGrade($u),
                $teachers->isNotEmpty() ? plural_ru($teachers->count(), 'учитель', 'учителя', 'учителей') : 'без учителя',
            ])),
            'tabItems' => ['overview' => 'Обзор', 'profile' => 'Профиль'],
        ];

        if ($this->tab === 'overview') {
            $data['groups'] = $teachers->map(fn (UserModel $t) => $this->studentTeacherGroup($u, $t));
            $data['lessonsUrl'] = $this->link('lessons', ['q' => $u->name]);

            $perf = app(StudentPerformanceService::class);
            $stats = $teachers->map(fn (UserModel $t) => $perf->stats($u, $t->id));
            $lessonsTotal = $stats->sum('lessons_total');
            $homeworkTotal = $stats->sum('homework_total');
            $month = MeetingSession::where('status', 'completed')->where('started_at', '>=', now()->subDays(30))
                ->whereHas('room', fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('users.id', $u->id)))->count();

            $data['activity'] = [
                ['Последний вход', $u->last_login_at ? HumanDate::at($u->last_login_at) : 'ещё не входил', null],
                ['Занятий за 30 дней', (string) $month, null],
                ['Посещаемость', $lessonsTotal ? $stats->sum('lessons_attended') . ' из ' . plural_ru($lessonsTotal, 'занятия', 'занятий', 'занятий') : 'занятий ещё не было', null],
                ['Задания в срок', $homeworkTotal ? $stats->sum('homework_on_time') . ' из ' . $homeworkTotal : 'заданий ещё не было', null],
            ];
            $data['contacts'] = array_values(array_filter([
                ['Почта', $u->email, null],
                $u->phone ? ['Телефон', $u->phone, null] : null,
                $u->referrer ? ['Пригласил', $u->referrer->name, route('cabinet.admin.user', ['user' => $u->referrer->id])] : null,
            ]));
        } else {
            $data['gradeOptions'] = StudentProfileService::GRADES;
        }

        return $data;
    }

    /** Учитель ученика и его занятия с ним: ближайшие или прошедшие (с «Не пришёл» и «Отменено»). */
    private function studentTeacherGroup(UserModel $student, UserModel $teacher): array
    {
        $rooms = Room::where('user_id', $teacher->id)->whereHas('participants', fn ($q) => $q->where('users.id', $student->id))
            ->with('participants:id')->get();
        $roomIds = $rooms->pluck('id');
        $label = fn (?Room $r) => $r ? ($r->type === 'group' || $r->participants->count() > 1 ? 'группа «' . $r->name . '»' : $r->name . ' · лично') : '';

        if ($this->view === 'past') {
            $held = MeetingSession::whereIn('room_id', $roomIds)->where('status', 'completed')->whereNotNull('started_at')
                ->latest('started_at')->take(3)->get()
                ->map(fn (MeetingSession $s) => [
                    'at' => $s->started_at,
                    'title' => Str::ucfirst(HumanDate::at($s->started_at)),
                    'sub' => Str::ucfirst($label($rooms->firstWhere('id', $s->room_id))),
                    'badge' => $s->attendedBy($student->id) ? null : ['Не пришёл', 'danger'],
                    'href' => $this->link('session', ['session' => $s->id]),
                ]);
            $cancelled = app(TeacherScheduleService::class)->cancelled($teacher->id, now()->subDays(90))
                ->filter(fn ($e) => $roomIds->contains($e->room_id))->take(3)
                ->map(fn ($e) => [
                    'at' => $e->original_starts_at,
                    'title' => Str::ucfirst(HumanDate::at($e->original_starts_at)),
                    'sub' => Str::ucfirst($label($rooms->firstWhere('id', $e->room_id))),
                    'badge' => ['Отменено', 'neutral'],
                    'href' => $this->link('lesson', ['room' => $e->room_id]),
                ]);
            $rows = $held->concat($cancelled)->sortByDesc(fn ($r) => $r['at']->timestamp)->take(3)->values();
        } else {
            $rows = app(StudentScheduleService::class)->events($student->id, now(), now()->addDays(60))
                ->filter(fn ($e) => $e['teacher_id'] === $teacher->id && $e['end']->isFuture())->take(3)
                ->map(fn ($e) => [
                    'title' => Str::ucfirst(HumanDate::at($e['start'])),
                    'sub' => Str::ucfirst($label($rooms->firstWhere('id', $e['room_id'])) ?: $e['title']),
                    'badge' => null,
                    'href' => $this->link('lesson', ['room' => $e['room_id']]),
                ])->values();
        }

        $subjects = $rooms->map(fn (Room $r) => $r->type === 'group' || $r->participants->count() > 1 ? 'в группе «' . $r->name . '»' : null)->filter()->unique();
        $since = $teacher->pivot?->created_at ? 'с ' . HumanDate::date(\Illuminate\Support\Carbon::parse($teacher->pivot->created_at)) : null;

        return [
            'teacher' => $teacher,
            'terms' => implode(' · ', array_filter([
                $rooms->isEmpty() ? 'занятий пока нет' : ($subjects->isNotEmpty() ? ($rooms->count() > $subjects->count() ? 'лично и ' : '') . $subjects->implode(', ') : 'лично'),
                $since,
            ])),
            'rows' => $rows,
            'empty' => $this->view === 'past' ? 'Прошедших занятий пока нет' : 'Ближайших занятий нет',
        ];
    }
}
