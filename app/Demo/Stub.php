<?php

namespace App\Demo;

use Livewire\Component;

/**
 * Подмена вложенных Livewire-компонентов (<livewire:cabinet.news-banner /> и т. п.) в демо.
 * Что выводить — Screen::components(): alias => [view, data] | null.
 *
 * Livewire называет компонент по классу (ComponentRegistry::classToName — первое имя, под которым класс
 * зарегистрирован), поэтому у каждого имени свой класс: Stub0…Stub11 ниже, они отличаются только
 * собственной статической $alias. Объявлены здесь же: register() вызывается раньше, чем они понадобятся.
 */
class Stub extends Component
{
    public const POOL = 12;

    /** alias => [view, data] | null */
    public static array $map = [];

    public static ?string $alias = null;

    /** Регистрирует подмены под их именами: каждому имени — свой класс из пула. */
    public static function register(array $map): void
    {
        self::$map = $map;

        foreach (array_values(array_keys($map)) as $i => $alias) {
            if ($i >= self::POOL) {
                throw new \LogicException('Слишком много подменных компонентов: увеличьте Stub::POOL');
            }
            $class = __NAMESPACE__ . '\\Stub' . $i;
            $class::$alias = $alias;
            \Livewire\Livewire::component($alias, $class);
        }
    }

    public function render()
    {
        $entry = self::$map[static::$alias] ?? null;

        return $entry ? view($entry[0], $entry[1]) : '<div></div>';
    }
}

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
class Stub0 extends Stub { public static ?string $alias = null; }
class Stub1 extends Stub { public static ?string $alias = null; }
class Stub2 extends Stub { public static ?string $alias = null; }
class Stub3 extends Stub { public static ?string $alias = null; }
class Stub4 extends Stub { public static ?string $alias = null; }
class Stub5 extends Stub { public static ?string $alias = null; }
class Stub6 extends Stub { public static ?string $alias = null; }
class Stub7 extends Stub { public static ?string $alias = null; }
class Stub8 extends Stub { public static ?string $alias = null; }
class Stub9 extends Stub { public static ?string $alias = null; }
class Stub10 extends Stub { public static ?string $alias = null; }
class Stub11 extends Stub { public static ?string $alias = null; }
