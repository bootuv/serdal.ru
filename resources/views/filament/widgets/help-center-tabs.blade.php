{{-- -mb-4 компенсирует стандартный gap-y-8 страницы: табы ближе к таблице --}}
<x-page-tabs class="-mb-4" :tabs="[
    [
        'label' => 'Инструкции',
        'href' => \App\Filament\Resources\HelpArticleResource::getUrl(),
        'active' => request()->routeIs('filament.admin.resources.help-articles.*'),
        'icon' => 'heroicon-o-book-open',
    ],
    [
        'label' => 'Категории',
        'href' => \App\Filament\Resources\HelpCategoryResource::getUrl(),
        'active' => request()->routeIs('filament.admin.resources.help-categories.*'),
        'icon' => 'heroicon-o-folder-open',
    ],
]" />
