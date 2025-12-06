<?php

namespace App\Filament\App\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class Integrations extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Интеграции';

    protected static ?string $title = 'Настройки интеграций';

    protected static string $view = 'filament.app.pages.integrations';

    public static function canAccess(): bool
    {
        return auth()->user()->role === \App\Models\User::ROLE_ADMIN;
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth()->user()->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('BigBlueButton')
                    ->description('Настройки подключения к вашему серверу видеоконференций.')
                    ->schema([
                        Forms\Components\TextInput::make('bbb_url')
                            ->label('URL сервера (Server Base URL)')
                            ->placeholder('https://bbb.example.com/bigbluebutton/')
                            ->helperText('Слэш в конце обязателен.')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bbb_secret')
                            ->label('Секретный ключ (Shared Secret)')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        auth()->user()->update([
            'bbb_url' => $data['bbb_url'],
            'bbb_secret' => $data['bbb_secret'],
        ]);

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
