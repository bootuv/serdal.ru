<?php

namespace App\Demo;

use Illuminate\Http\Request;

/**
 * Экран демо-кабинета: настоящий шаблон Livewire-экрана (view) + выдуманные данные (data).
 *
 * Шаблон рендерится как обычный Blade внутри настоящей раскладки, поэтому выглядит ровно как кабинет.
 * Livewire в демо нет: клики wire:click / $wire.… перехватывает public/js/demo-cabinet.js
 * и выполняет действие из actions(), а состояние экрана (открытое окно, вкладка, фильтр)
 * живёт в адресной строке — data() читает его через state().
 *
 * Действие (actions(): метод Livewire => действие) — массив из любых ключей:
 *   set    — ['open' => 'plan', 'planKind' => '{0}'] — поменять параметры адреса ({0}, {1} — аргументы вызова; null — убрать);
 *   toggle / add / remove — 'planDays' — переключить / добавить / убрать {0} в списке через запятую;
 *   go     — '/demo/teacher/students/{0}' — перейти по адресу;
 *   toast  — 'Готово' (+ tone: ok | danger) — показать тост (после перехода тоже);
 *   close  — true — закрыть окна: убрать параметры из modalParams().
 * Метод без действия: close…/cancel… закрывают окно, остальные — тост «в демо не сохраняется».
 */
abstract class Screen
{
    /** Шаблон экрана, например 'livewire.cabinet.teacher.today'. */
    public string $view;

    public string $title;

    /** Активный пункт меню (ключ из раскладки). */
    public ?string $active = null;

    /** Экран сам управляет отступами и высотой (как #[Layout(..., ['bare' => true])]). */
    public bool $bare = false;

    public function __construct(protected Request $request, protected array $params = [])
    {
    }

    /** Все переменные шаблона: то, что отдаёт render() компонента, и публичные свойства компонента. */
    abstract public function data(): array;

    public function actions(): array
    {
        return [];
    }

    /** Параметры адреса, которые описывают открытые окна: их убирает закрытие окна. */
    public function modalParams(): array
    {
        return ['open'];
    }

    /** Значения $wire.свойство, которые читает Alpine в шаблоне (x-data="{ a: $wire.a }" и т. п.). */
    public function props(): array
    {
        return [];
    }

    /** Тост при открытии экрана (например, «в демо видеосвязь не запускается»), null — без тоста. */
    public function notice(): ?string
    {
        return null;
    }

    /** Подменные Livewire-компоненты внутри шаблона: alias => [view, data] (null — ничего не выводить). */
    public function components(): array
    {
        return [];
    }

    /** Параметр маршрута ({id} в Routes::TEACHER). */
    protected function param(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    /** Состояние экрана из адреса с приведением к типу значения по умолчанию. */
    protected function state(string $key, mixed $default = null): mixed
    {
        $value = $this->request->query($key);

        if ($value === null) {
            return $default;
        }

        return match (true) {
            is_bool($default) => in_array($value, ['1', 'true'], true),
            is_int($default) => (int) $value,
            is_array($default) => $value === '' ? [] : explode(',', (string) $value),
            default => (string) $value,
        };
    }

    /** Список чисел из адреса («1,3,5»). */
    protected function stateInts(string $key, array $default = []): array
    {
        return array_values(array_map('intval', $this->state($key, array_map('strval', $default))));
    }

    /** Адрес настоящего кабинета — в ответе он заменяется на демо (DemoController::rewrite). */
    protected function cabinet(string $route, array $params = []): string
    {
        return route($route, $params);
    }
}
