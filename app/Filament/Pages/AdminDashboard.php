<?php

namespace App\Filament\Pages;

use App\Models\User; // Use Models\User since user is defined as model
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Forms\Get;

class AdminDashboard extends Dashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('period')
                            ->label('Период')
                            ->options([
                                'day' => 'За день',
                                'week' => 'За неделю',
                                'month' => 'За месяц',
                                'quarter' => 'За квартал',
                                'year' => 'За год',
                            ])
                            ->default('week')
                            ->selectablePlaceholder(false),
                        Select::make('teacher_ids')
                            ->label('Учителя')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                \Illuminate\Support\Facades\Log::info("Searching for teachers with term: '{$search}'");
                                $query = User::where('role', User::ROLE_TUTOR)
                                    ->where(function ($query) use ($search) {
                                        $query->where('first_name', 'like', "%{$search}%")
                                            ->orWhere('last_name', 'like', "%{$search}%")
                                            ->orWhere('name', 'like', "%{$search}%");
                                    });

                                $results = $query->limit(50)->get();
                                \Illuminate\Support\Facades\Log::info("Found {$results->count()} teachers.");

                                return $results->mapWithKeys(fn($user) => [$user->id => trim("{$user->last_name} {$user->first_name}") ?: $user->name]);
                            })
                            ->getOptionLabelsUsing(fn(array $values) => User::whereIn('id', $values)
                                ->get()
                                ->mapWithKeys(fn($user) => [$user->id => trim("{$user->last_name} {$user->first_name}") ?: $user->name]))
                            ->placeholder('Все учителя'),
                    ])
                    ->columns(3)
                    ->extraAttributes(['class' => '!mb-0'])
            ])
            ->columns(3);
    }
}
