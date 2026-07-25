<?php

namespace App\Filament\Resources\HelpArticleResource\Widgets;

use Filament\Widgets\Widget;

/**
 * Табы «Инструкции / Категории» на страницах базы знаний в админке
 */
class HelpCenterTabs extends Widget
{
    protected static string $view = 'filament.widgets.help-center-tabs';

    // Не лениво: при lazy-рендере Livewire request() уже не видит роут страницы,
    // и активный таб определяется неверно
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
}
