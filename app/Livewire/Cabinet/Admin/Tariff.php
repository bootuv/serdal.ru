<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\Tariff as TariffModel;
use App\Services\ReferralService;
use App\Services\TariffService;
use App\Support\Money;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Карточка тарифа (макет AdminTariffEdit): основное, лимиты, описание для сайта, партнёрская программа,
 * превью «Так тариф видят учителя». «Скрыть тариф» — пропадает с сайта, действующие подписки доживают свой срок.
 * {tariff} — id или new. Слаг не показываем: создаётся из названия.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Тариф', 'active' => 'tariffs'])]
class Tariff extends Component
{
    use AdminScreen;

    public ?int $tariffId = null;

    public string $name = '';
    public string $periodDays = '30';
    public string $price = '';
    public string $yearlyPrice = '';
    public bool $isActive = true;
    public bool $isPopular = false;

    public string $lessons = '';
    public bool $lessonsUnlimited = false;
    public string $participants = '2';
    public string $duration = '';
    public bool $durationUnlimited = false;
    public string $recording = '';

    public string $referralBonus = '';

    public string $shortDescription = '';
    public string $description = '';
    public array $features = [];
    public array $extras = [];
    public string $newFeature = '';
    public string $newExtra = '';

    /** Окно «Скрыть тариф». */
    public bool $hiding = false;

    /** Тост «Тариф скрыт с сайта» с «Отменить». */
    public bool $undoHide = false;

    public function mount(string $tariff): void
    {
        $this->authorizeAdmin();

        if ($tariff === 'new') {
            return;
        }

        abort_unless(ctype_digit($tariff), 404);
        $t = TariffModel::findOrFail((int) $tariff);

        $this->tariffId = $t->id;
        $this->name = $t->name;
        $this->periodDays = (string) $t->period_days;
        $this->price = (string) (int) $t->price;
        $this->yearlyPrice = $t->yearly_price ? (string) (int) $t->yearly_price : '';
        $this->isActive = (bool) $t->is_active;
        $this->isPopular = (bool) $t->is_popular;
        $this->lessons = (string) ($t->lessons_per_month ?? '');
        $this->lessonsUnlimited = $t->lessons_per_month === null;
        $this->participants = (string) $t->max_participants;
        $this->duration = (string) ($t->max_duration_minutes ?? '');
        $this->durationUnlimited = $t->max_duration_minutes === null;
        $this->recording = (string) ($t->recording_retention_days ?? '');
        $this->referralBonus = $t->referral_bonus === null ? '' : (string) $t->referral_bonus;
        $this->shortDescription = (string) $t->short_description;
        $this->description = (string) $t->description;
        $this->features = array_values($t->features ?? []);
        $this->extras = array_values($t->extra_features ?? []);
    }

    private function model(): ?TariffModel
    {
        return $this->tariffId ? TariffModel::findOrFail($this->tariffId) : null;
    }

    private function service(): TariffService
    {
        return app(TariffService::class);
    }

    /* ---------- Пункты «Что входит» и «Дополнительные сервисы» ---------- */

    public function addFeature(): void
    {
        $text = trim($this->newFeature);
        if ($text !== '' && ! in_array($text, $this->features, true)) {
            $this->features[] = Str::limit($text, 120, '');
        }
        $this->newFeature = '';
    }

    public function removeFeature(int $i): void
    {
        unset($this->features[$i]);
        $this->features = array_values($this->features);
    }

    public function addExtra(): void
    {
        $text = trim($this->newExtra);
        if ($text !== '' && ! in_array($text, $this->extras, true)) {
            $this->extras[] = Str::limit($text, 120, '');
        }
        $this->newExtra = '';
    }

    public function removeExtra(int $i): void
    {
        unset($this->extras[$i]);
        $this->extras = array_values($this->extras);
    }

    /* ---------- Показ на сайте ---------- */

    public function toggleSite(): void
    {
        if (! $this->tariffId) {
            $this->isActive = ! $this->isActive;

            return;
        }

        $this->isActive ? $this->hiding = true : $this->unhide();
    }

    public function openHide(): void
    {
        $this->hiding = (bool) $this->tariffId && $this->isActive;
    }

    public function closeHide(): void
    {
        $this->hiding = false;
    }

    public function hide(): void
    {
        $this->service()->setVisible($this->model(), false);
        $this->isActive = false;
        $this->hiding = false;
        $this->undoHide = true;
    }

    public function unhide(): void
    {
        $this->service()->setVisible($this->model(), true);
        $this->isActive = true;
        if ($this->undoHide) {
            $this->undoHide = false;

            return;
        }
        $this->dispatch('toast', message: 'Тариф снова на сайте');
    }

    public function dismissUndo(): void
    {
        $this->undoHide = false;
    }

    /* ---------- Сохранение ---------- */

    public function save()
    {
        $this->addFeature();
        $this->addExtra();
        foreach (['periodDays', 'price', 'yearlyPrice', 'lessons', 'participants', 'duration', 'recording', 'referralBonus'] as $f) {
            $this->{$f} = preg_replace('/\D+/', '', $this->{$f});
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'periodDays' => ['required', 'integer', 'min:1', 'max:3650'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'yearlyPrice' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'lessons' => [$this->lessonsUnlimited ? 'nullable' : 'required', 'integer', 'min:1', 'max:100000'],
            'participants' => ['required', 'integer', 'min:2', 'max:1000'],
            'duration' => [$this->durationUnlimited ? 'nullable' : 'required', 'integer', 'min:15', 'max:1440'],
            'recording' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'referralBonus' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'shortDescription' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], [
            'name.required' => 'Укажите название',
            'price.required' => 'Укажите цену, 0 — бесплатный тариф',
            'lessons.required' => 'Укажите число или выберите «Без лимита»',
            'duration.required' => 'Укажите минуты или выберите «Без лимита»',
            'duration.min' => 'Не меньше 15 минут',
            'participants.min' => 'Не меньше 2 — вместе с учителем',
            'yearlyPrice.min' => 'Пусто — за год не продаётся',
        ], [
            'name' => 'название', 'periodDays' => 'период', 'price' => 'цена', 'yearlyPrice' => 'цена за год', 'lessons' => 'занятий в месяц',
            'participants' => 'участников', 'duration' => 'длительность', 'recording' => 'хранение записей', 'referralBonus' => 'бонус',
            'shortDescription' => 'короткое описание', 'description' => 'подробное описание',
        ]);

        $n = fn (string $v) => $v === '' ? null : (int) $v;
        $tariff = $this->service()->save($this->model(), [
            'name' => trim($this->name),
            'price' => (int) $this->price,
            'yearly_price' => (int) $this->price > 0 ? $n($this->yearlyPrice) : null,
            'period_days' => (int) $this->periodDays,
            'lessons_per_month' => $this->lessonsUnlimited ? null : $n($this->lessons),
            'max_participants' => (int) $this->participants,
            'max_duration_minutes' => $this->durationUnlimited ? null : $n($this->duration),
            'recording_retention_days' => $n($this->recording),
            'referral_bonus' => $n($this->referralBonus),
            'short_description' => trim($this->shortDescription) ?: null,
            'description' => trim($this->description) ?: null,
            'features' => $this->features,
            'extra_features' => $this->extras,
            'is_active' => $this->isActive,
            'is_popular' => $this->isPopular,
        ]);

        if (! $this->tariffId) {
            session()->flash('toast', 'Тариф добавлен');

            return $this->redirectRoute('cabinet.admin.tariff', ['tariff' => $tariff->id], navigate: false);
        }

        $this->dispatch('toast', message: 'Тариф сохранён');
    }

    public function render()
    {
        $service = $this->service();
        $model = $this->model();
        $num = fn (string $v) => (int) preg_replace('/\D+/', '', $v);
        $price = $num($this->price);
        $year = $num($this->yearlyPrice);
        $days = max(1, $num($this->periodDays));
        $name = trim($this->name) ?: ($this->tariffId ? 'Без названия' : 'Новый тариф');

        $c = $model ? $service->consequences($model) : null;
        $subsText = $c ? plural_ru($c['active'], 'активная подписка', 'активные подписки', 'активных подписок') : null;

        $per = $days === 30 ? ' в месяц' : ' за ' . plural_ru($days, 'день', 'дня', 'дней');

        return view('livewire.cabinet.admin.tariff', [
            'title' => $name,
            'factLine' => match (true) {
                ! $c => null,
                $this->isActive => 'На сайте · ' . $subsText . ($c['yearly'] ? ', из них ' . $c['yearly'] . ' на год' : ''),
                default => 'Скрыт с сайта · ' . $subsText . ($c['active'] ? ' продолжают действовать' : ''),
            },
            'consequences' => $c,
            'yearHint' => $service->yearHint($price, $year, $days),
            'siteText' => $this->isActive ? 'Учителя видят тариф и могут его подключить' : 'Подключить нельзя, действующие подписки доживут свой срок',
            'pvName' => $name,
            'pvPrice' => $price ? Money::format($price) : 'Бесплатно',
            'pvPer' => $price ? $per : '',
            'pvYear' => $price && $year ? 'или ' . Money::format($year) . ' за год' : null,
            'pvLimits' => [
                $this->lessonsUnlimited ? 'Без лимита занятий' : plural_ru(max(0, $num($this->lessons)), 'занятие', 'занятия', 'занятий') . ' в месяц',
                'До ' . plural_ru(max(0, $num($this->participants)), 'участника', 'участников', 'участников') . ' в занятии',
                $this->durationUnlimited ? 'Длительность без ограничений' : 'Занятие до ' . plural_ru(max(0, $num($this->duration)), 'минуты', 'минут', 'минут'),
                $num($this->recording) ? 'Записи хранятся ' . plural_ru($num($this->recording), 'день', 'дня', 'дней') : 'Без записей занятий',
            ],
            'pvExtras' => $this->extras ? 'А ещё: ' . mb_strtolower(implode(', ', $this->extras)) : null,
            'bonusPlaceholder' => ReferralService::referrerBonus() . ', как в программе',
            'referralsUrl' => \Illuminate\Support\Facades\Route::has('cabinet.admin.referrals') ? route('cabinet.admin.referrals') : '#',
            'freeWarning' => $c && $c['freeWarning'] ? \App\Services\TariffService::FREE_WARNING : null,
        ])->title($name);
    }
}
