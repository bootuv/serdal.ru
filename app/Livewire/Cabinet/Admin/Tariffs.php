<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\Tariff;
use App\Services\TariffService;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Тарифы по порядку на сайте (макет AdminTariffs): перетаскивание за ручку меняет порядок сразу,
 * тост «Порядок сохранён» с «Отменить». Без мыши: нажать ручку, затем строку, перед которой поставить тариф.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Тарифы', 'active' => 'tariffs'])]
class Tariffs extends Component
{
    use AdminScreen;

    /** Тариф, который переставляем нажатием (без перетаскивания). */
    public ?int $moving = null;

    /** Порядок до последней перестановки — для «Отменить». */
    public ?array $undoOrder = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function pick(int $id): void
    {
        $this->moving = $this->moving === $id ? null : $id;
    }

    public function cancelMove(): void
    {
        $this->moving = null;
    }

    /** Поставить выбранный тариф перед $target (null — в конец). */
    public function dropBefore(?int $target = null): void
    {
        if ($this->moving) {
            $this->move($this->moving, $target, true);
        }
        $this->moving = null;
    }

    /** Перетаскивание: $id — перед/после $target; $target = null — в конец. */
    public function move(int $id, ?int $target = null, bool $before = true): void
    {
        $order = $this->order();
        if (! in_array($id, $order, true) || $id === $target) {
            return;
        }

        $rest = array_values(array_filter($order, fn ($x) => $x !== $id));
        $pos = $target !== null && in_array($target, $rest, true) ? array_search($target, $rest, true) + ($before ? 0 : 1) : count($rest);
        array_splice($rest, $pos, 0, [$id]);

        if ($rest === $order) {
            return;
        }

        $this->undoOrder = app(TariffService::class)->reorder($rest);
    }

    public function undo(): void
    {
        if ($this->undoOrder) {
            app(TariffService::class)->reorder($this->undoOrder);
        }
        $this->undoOrder = null;
    }

    public function dismissUndo(): void
    {
        $this->undoOrder = null;
    }

    private function order(): array
    {
        return Tariff::orderBy('sort')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function render()
    {
        $service = app(TariffService::class);
        $tariffs = $service->list();
        $visible = $tariffs->where('is_active', true)->count();
        $hidden = $tariffs->count() - $visible;
        $subs = (int) $tariffs->sum('active_subscriptions_count');

        return view('livewire.cabinet.admin.tariffs', [
            'factLine' => implode(' · ', array_filter([
                'На сайте ' . plural_ru($visible, 'тариф', 'тарифа', 'тарифов'),
                $hidden ? plural_ru($hidden, 'скрыт', 'скрыты', 'скрыты') : null,
                plural_ru($subs, 'активная подписка', 'активные подписки', 'активных подписок'),
            ])),
            'rows' => $tariffs->map(function (Tariff $t) use ($service) {
                [$price, $priceSub] = $service->priceLines($t);

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'popular' => (bool) $t->is_popular,
                    'hidden' => ! $t->is_active,
                    'limits' => $service->limitsLine($t),
                    'price' => $price,
                    'priceSub' => $priceSub,
                    'subs' => (int) $t->active_subscriptions_count,
                    'url' => route('cabinet.admin.tariff', ['tariff' => $t->id]),
                ];
            }),
            'movingName' => $this->moving ? $tariffs->firstWhere('id', $this->moving)?->name : null,
            'newUrl' => Route::has('cabinet.admin.tariff') ? route('cabinet.admin.tariff', ['tariff' => 'new']) : '#',
        ]);
    }
}
