<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tariff;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Тарифы учителей в админке: порядок на сайте, показ/скрытие, сохранение формы, тексты последствий.
 * Скрытый тариф (is_active = false) пропадает с сайта и из выбора у учителей; действующие подписки доживают свой срок.
 */
class TariffService
{
    /** Тарифы по порядку на сайте с числом активных подписок. */
    public function list(): Collection
    {
        return Tariff::orderBy('sort')->orderBy('id')
            ->withCount(['subscriptions as active_subscriptions_count' => fn ($q) => $q->active()])
            ->get();
    }

    /** Новый порядок: $ids — id тарифов сверху вниз. Возвращает прежний порядок (для «Отменить»). */
    public function reorder(array $ids): array
    {
        $before = Tariff::orderBy('sort')->orderBy('id')->pluck('id')->all();
        $ids = array_values(array_unique(array_map('intval', $ids)));
        // Тарифы, которых нет в списке, — в конец, в прежнем порядке
        $ids = array_merge(array_values(array_intersect($ids, $before)), array_values(array_diff($before, $ids)));

        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $id) {
                Tariff::whereKey($id)->update(['sort' => ($i + 1) * 10]);
            }
        });

        return $before;
    }

    public function setVisible(Tariff $tariff, bool $visible): void
    {
        $tariff->update(['is_active' => $visible]);
    }

    /** Последствия скрытия: активные подписки (из них годовых) и предупреждение про бесплатный тариф. */
    public function consequences(Tariff $tariff): array
    {
        $active = $tariff->subscriptions()->active();

        return [
            'active' => (clone $active)->count(),
            'yearly' => (clone $active)->whereHas('payments', fn ($q) => $q->where('status', 'paid')->where('period_days', '>=', 365))->count(),
            'freeWarning' => $tariff->isFree() && $tariff->is_active,
        ];
    }

    /** Текст предупреждения о бесплатном тарифе. */
    public const FREE_WARNING = 'Это бесплатный тариф: без него новым учителям после первых шагов тариф назначаться не будет.';

    /** Лимиты одной строкой: «8 занятий в месяц · до 2 участников · занятие до 60 минут · без записей». */
    public function limitsLine(Tariff $t): string
    {
        return implode(' · ', [
            $t->lessons_per_month ? plural_ru((int) $t->lessons_per_month, 'занятие', 'занятия', 'занятий') . ' в месяц' : 'без лимита занятий',
            'до ' . plural_ru((int) $t->max_participants, 'участника', 'участников', 'участников'),
            $t->max_duration_minutes ? 'занятие до ' . plural_ru((int) $t->max_duration_minutes, 'минуты', 'минут', 'минут') : 'любая длительность',
            $t->recording_retention_days ? 'записи ' . plural_ru((int) $t->recording_retention_days, 'день', 'дня', 'дней') : 'без записей',
        ]);
    }

    /** Цена: «1 490 ₽ за 30 дней» / «Бесплатно» и строка про год. */
    public function priceLines(Tariff $t): array
    {
        if ($t->isFree()) {
            return ['Бесплатно', 'без срока'];
        }

        return [
            Money::format((int) $t->price) . ' за ' . plural_ru((int) $t->period_days, 'день', 'дня', 'дней'),
            $t->hasYearly()
                ? Money::format((int) $t->yearly_price) . ' за год' . ($t->yearlyDiscountPercent() > 0 ? ', скидка ' . $t->yearlyDiscountPercent() . '%' : '')
                : 'за год не продаётся',
        ];
    }

    /** Подсказка под «Цена за год»: сколько стоят оплаты по периоду за год и какая выходит скидка. */
    public function yearHint(int $price, int $year, int $days): string
    {
        $periods = $days > 0 ? (int) round(365 / $days) : 0;
        $full = $price * $periods;

        return match (true) {
            $year <= 0 => 'Пусто — за год не продаётся',
            $full <= 0 => 'Сначала укажите цену за период',
            $year >= $full => 'Не дешевле ' . plural_ru($periods, 'оплаты', 'оплат', 'оплат') . ' по периоду (' . Money::format($full) . ') — скидки нет',
            default => plural_ru($periods, 'оплата', 'оплаты', 'оплат') . ' по периоду — ' . Money::format($full) . ', скидка ' . (int) round((1 - $year / $full) * 100) . '%',
        };
    }

    /** Уникальный слаг из названия (латиницей); занятые, в том числе у скрытых и удалённых тарифов, пропускаем. */
    public function uniqueSlug(string $name): string
    {
        $base = Str::slug(Str::transliterate($name)) ?: 'tariff';
        $slug = $base;
        $i = 2;
        while (Tariff::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /** Создаёт или обновляет тариф. Новый — в конец списка, слаг из названия. */
    public function save(?Tariff $tariff, array $data): Tariff
    {
        $fields = collect($data)->only([
            'name', 'price', 'yearly_price', 'period_days', 'lessons_per_month', 'max_participants', 'max_duration_minutes',
            'recording_retention_days', 'referral_bonus', 'short_description', 'description', 'features', 'extra_features',
            'is_active', 'is_popular',
        ])->all();

        if ($tariff) {
            $tariff->update($fields);

            return $tariff;
        }

        return Tariff::create($fields + [
            'slug' => $this->uniqueSlug($fields['name']),
            'sort' => (int) Tariff::withTrashed()->max('sort') + 10,
        ]);
    }

    /** Активные подписки на тарифе — для строки фактов. */
    public function activeCount(Tariff $tariff): int
    {
        return Subscription::active()->where('tariff_id', $tariff->id)->count();
    }
}
