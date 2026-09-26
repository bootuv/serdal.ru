<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\Direct;
use App\Models\Subject;
use App\Models\TeacherApplication;
use App\Models\User;
use App\Services\MessengerService;
use App\Services\TeacherApplicationService;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Заявки учителей: вкладки по статусу, поиск, окно заявки, «Одобрить» и «Отклонить» с причиной.
 * Макет: AdminApplications. Логика решения — TeacherApplicationService (общая с Filament).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Заявки учителей', 'active' => 'applications'])]
class Applications extends Component
{
    use AdminScreen;

    private const TABS = [
        TeacherApplication::STATUS_PENDING => 'На рассмотрении',
        TeacherApplication::STATUS_APPROVED => 'Одобренные',
        TeacherApplication::STATUS_REJECTED => 'Отклонённые',
    ];

    #[Url(except: TeacherApplication::STATUS_PENDING)]
    public string $tab = TeacherApplication::STATUS_PENDING;

    #[Url(except: '')]
    public string $q = '';

    /** Открытая заявка. */
    #[Locked]
    public ?int $openId = null;

    /** Шаг окна: view · approve · error · reject. */
    #[Locked]
    public string $step = '';

    public string $reason = '';

    /** Тост после решения: текст и ссылка на созданного учителя. */
    #[Locked]
    public ?string $toast = null;

    #[Locked]
    public ?string $toastUrl = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = TeacherApplication::STATUS_PENDING;
        }
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = TeacherApplication::STATUS_PENDING;
        }
    }

    public function open(int $id): void
    {
        $this->application($id);
        $this->openId = $id;
        $this->step = 'view';
        $this->toast = null;
    }

    public function close(): void
    {
        $this->openId = null;
        $this->step = '';
        $this->reset('reason');
        $this->resetValidation();
    }

    public function back(): void
    {
        $this->step = 'view';
        $this->resetValidation();
    }

    public function toApprove(): void
    {
        $this->pending();
        $this->step = 'approve';
    }

    public function toReject(): void
    {
        $this->pending();
        $this->reset('reason');
        $this->step = 'reject';
    }

    public function approve(): void
    {
        $application = $this->pending();
        $service = app(TeacherApplicationService::class);

        if ($service->existingUser($application)) {
            $this->step = 'error';

            return;
        }

        $user = $service->approve($application);
        $this->close();
        $this->toast = 'Заявка одобрена — пароль отправлен на почту';
        $this->toastUrl = $user ? $this->userUrl($user) : null;
    }

    public function reject(): void
    {
        $application = $this->pending();
        $this->validate(['reason' => ['nullable', 'string', 'max:2000']], ['reason.max' => 'Сократите причину до 2000 символов']);

        app(TeacherApplicationService::class)->reject($application, $this->reason);
        $this->close();
        $this->toast = 'Заявка отклонена — письмо отправлено';
        $this->toastUrl = null;
    }

    public function hideToast(): void
    {
        $this->toast = null;
        $this->toastUrl = null;
    }

    private function application(int $id): TeacherApplication
    {
        $application = TeacherApplication::with(['referrer:id,name', 'desiredTariff'])->find($id);
        abort_unless($application, 404);

        return $application;
    }

    /** Открытая заявка на рассмотрении (решение уже принято — 404). */
    private function pending(): TeacherApplication
    {
        $application = $this->application((int) $this->openId);
        abort_unless($application->status === TeacherApplication::STATUS_PENDING, 404);

        return $application;
    }

    private function userUrl(User $user): ?string
    {
        return Route::has('cabinet.admin.user') ? route('cabinet.admin.user', $user->id) : null;
    }

    public function render()
    {
        $term = trim($this->q);
        $query = TeacherApplication::query()
            ->where('status', $this->tab)
            ->with(['referrer:id,name', 'desiredTariff'])
            ->when($term !== '', function ($q) use ($term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)->orWhere('email', 'like', $like));
            });
        $query = $this->tab === TeacherApplication::STATUS_PENDING ? $query->orderBy('created_at') : $query->orderByDesc('decided_at')->orderByDesc('id');
        $applications = $query->get();

        $subjects = Subject::whereIn('id', $applications->pluck('subjects')->flatten()->filter()->unique())->pluck('name', 'id');

        $opened = $this->openId ? $this->application($this->openId) : null;
        $existing = $opened && $this->step === 'error' ? app(TeacherApplicationService::class)->existingUser($opened) : null;

        return view('livewire.cabinet.admin.applications', [
            'tabs' => self::TABS,
            'pendingCount' => TeacherApplication::where('status', TeacherApplication::STATUS_PENDING)->count(),
            'rows' => $applications->map(fn (TeacherApplication $a) => $this->row($a, $subjects->all())),
            'emptyText' => match (true) {
                $term !== '' => 'Никого не нашли — проверьте имя или почту',
                $this->tab === TeacherApplication::STATUS_PENDING => 'Новых заявок нет — они появятся здесь, когда учитель отправит форму на сайте',
                default => 'Пока пусто',
            },
            'a' => $opened ? $this->details($opened) : null,
            'existing' => $existing ? [
                'text' => $opened->email . ' — ' . mb_strtolower(MessengerService::roleLabel($existing)) . ' ' . $existing->name . '. Аккаунт учителя не создан.',
                'url' => $this->userUrl($existing),
            ] : null,
        ]);
    }

    private function name(TeacherApplication $a): string
    {
        return trim($a->first_name . ' ' . $a->last_name) ?: $a->email;
    }

    private function when(Carbon $at): string
    {
        return match (true) {
            $at->isToday(), $at->isYesterday() => Str::ucfirst(HumanDate::at($at)),
            $at->greaterThan(now()->subDays(7)) => HumanDate::day($at),
            default => HumanDate::date($at),
        };
    }

    private function decided(TeacherApplication $a): ?string
    {
        if ($a->status === TeacherApplication::STATUS_PENDING) {
            return null;
        }
        $at = $a->decided_at ?? $a->updated_at;
        $day = $at && ($at->isToday() || $at->isYesterday()) ? HumanDate::day($at) : ($at ? HumanDate::date($at) : '');

        return ($a->status === TeacherApplication::STATUS_APPROVED ? 'одобрена ' : 'отклонена ') . $day;
    }

    private function subjectNames(array $ids, array $names): string
    {
        $list = array_values(array_filter(array_map(fn ($id) => $names[$id] ?? null, $ids)));

        return $list ? Str::ucfirst(mb_strtolower(implode(', ', $list))) : '';
    }

    private function row(TeacherApplication $a, array $subjects): array
    {
        $days = (int) $a->created_at->copy()->startOfDay()->diffInDays(today());

        return [
            'id' => $a->id,
            'name' => $this->name($a),
            'email' => $a->email,
            'subjects' => $this->subjectNames($a->subjects ?? [], $subjects),
            'tariff' => $a->desiredTariff ? '«' . $a->desiredTariff->name . '»' : 'Не выбран',
            'ref' => $a->referrer?->name,
            'when' => $this->when($a->created_at),
            'waits' => $a->status === TeacherApplication::STATUS_PENDING && $days >= 2 ? 'ждёт ' . plural_ru($days, 'день', 'дня', 'дней') : null,
            'note' => $this->decided($a),
        ];
    }

    private function details(TeacherApplication $a): array
    {
        $subjects = Subject::whereIn('id', $a->subjects ?? [])->pluck('name', 'id')->all();
        $directs = Direct::whereIn('id', $a->directs ?? [])->pluck('name')->all();
        $grades = $a->grade ? (new User(['grade' => $a->grade]))->display_grade : '';
        $tariff = $a->desiredTariff;
        $when = mb_strtolower($this->when($a->created_at));
        $user = $a->status === TeacherApplication::STATUS_APPROVED ? User::where('email', $a->email)->first() : null;

        return [
            'id' => $a->id,
            'name' => $this->name($a),
            'full' => $a->full_name,
            'email' => $a->email,
            'phone' => $a->phone,
            'telegram' => $a->telegram,
            'whatsapp' => $a->whatsup,
            'about' => $a->about,
            'subjects' => $this->subjectNames($a->subjects ?? [], $subjects) ?: null,
            'directs' => $directs ? implode(', ', $directs) : null,
            'grades' => $grades ?: null,
            'tariff' => $tariff
                ? '«' . $tariff->name . '» · ' . ($tariff->isFree() ? 'бесплатно' : Money::format((int) $tariff->price) . match (true) {
                    in_array((int) $tariff->period_days, [0, 30, 31], true) => ' в месяц',
                    (int) $tariff->period_days === 365 => ' в год',
                    default => ' за ' . plural_ru((int) $tariff->period_days, 'день', 'дня', 'дней'),
                })
                : null,
            'ref' => $a->referrer?->name,
            'sub' => 'Заявка ' . $when . (($d = $this->decided($a)) ? ' · ' . $d : ''),
            'status' => $a->status,
            'reason' => $a->status === TeacherApplication::STATUS_REJECTED ? $a->reject_reason : null,
            'userUrl' => $user ? $this->userUrl($user) : null,
        ];
    }
}
