<?php

namespace App\Filament\Student\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class Profile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.student.pages.profile';

    protected static ?string $navigationLabel = 'Профиль';

    protected static ?string $title = 'Мой профиль';

    protected static ?int $navigationSort = 999;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth()->user()->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('avatar')
                    ->label('Аватар')
                    ->image()
                    ->avatar()
                    ->directory('avatars')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('name')
                    ->label('ФИО')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('password')
                    ->label('Пароль')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->dehydrated(fn($state) => filled($state))
                    ->dehydrateStateUsing(fn($state) => bcrypt($state))
                    ->helperText('Оставьте пустым, если не хотите менять пароль'),

                Forms\Components\TextInput::make('phone')
                    ->label('Телефон')
                    ->tel()
                    ->maxLength(255),

                Forms\Components\Select::make('grade')
                    ->label('Класс')
                    ->searchable(false)
                    ->options([
                        'preschool' => 'Дошкольник',
                        '1' => '1 класс',
                        '2' => '2 класс',
                        '3' => '3 класс',
                        '4' => '4 класс',
                        '5' => '5 класс',
                        '6' => '6 класс',
                        '7' => '7 класс',
                        '8' => '8 класс',
                        '9' => '9 класс',
                        '10' => '10 класс',
                        '11' => '11 класс',
                        'adults' => 'Взрослый',
                    ])
                    ->dehydrateStateUsing(function ($state) {
                        // Convert single value to array for database storage
                        if (empty($state)) {
                            return [];
                        }
                        $value = is_numeric($state) ? (int) $state : $state;
                        return [$value];
                    })
                    ->afterStateHydrated(function ($component, $state) {
                        // Convert array to single value for form display
                        if (!is_array($state) || empty($state)) {
                            $component->state(null);
                            return;
                        }
                        $firstValue = $state[0];
                        $component->state(is_int($firstValue) ? (string) $firstValue : $firstValue);
                    }),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();

        // Remove password if empty
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        Notification::make()
            ->success()
            ->title('Профиль обновлен')
            ->send();
    }
}
