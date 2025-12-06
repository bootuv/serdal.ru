<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class ManageBigBlueButton extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static ?string $navigationLabel = 'Настройки BBB';

    protected static ?string $title = 'Настройки BigBlueButton';

    protected static string $view = 'filament.pages.manage-big-blue-button';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'bbb_url' => Setting::where('key', 'bbb_url')->value('value'),
            'bbb_secret' => Setting::where('key', 'bbb_secret')->value('value'),
            'record' => Setting::where('key', 'bbb_record')->value('value') === '1',
            'auto_start_recording' => Setting::where('key', 'bbb_auto_start_recording')->value('value') === '1',
            'allow_start_stop_recording' => Setting::where('key', 'bbb_allow_start_stop_recording')->value('value') !== '0',
            'mute_on_start' => Setting::where('key', 'bbb_mute_on_start')->value('value') === '1',
            'webcams_only_for_moderator' => Setting::where('key', 'bbb_webcams_only_for_moderator')->value('value') === '1',
            'max_participants' => Setting::where('key', 'bbb_max_participants')->value('value') ?? 0,
            'duration' => Setting::where('key', 'bbb_duration')->value('value') ?? 0,
            'logout_url' => Setting::where('key', 'bbb_logout_url')->value('value'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Global Configuration')
                    ->description('Эти настройки будут использоваться по умолчанию, если у пользователя не указаны собственные.')
                    ->schema([
                        TextInput::make('bbb_url')
                            ->label('URL сервера (Server Base URL)')
                            ->placeholder('https://bbb.example.com/bigbluebutton/')
                            ->helperText('Слэш в конце обязателен.')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('bbb_secret')
                            ->label('Секретный ключ (Shared Secret)')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ]),
                Section::make('Advanced Settings')
                    ->description('Настройки по умолчанию для всех вебинаров.')
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('record')
                            ->label('Record Meeting')
                            ->default(true)
                            ->helperText('Записывать встречи по умолчанию'),
                        \Filament\Forms\Components\Toggle::make('auto_start_recording')
                            ->label('Auto-start Recording')
                            ->default(true)
                            ->helperText('Автоматически начинать запись'),
                        \Filament\Forms\Components\Toggle::make('allow_start_stop_recording')
                            ->label('Allow Start/Stop Recording')
                            ->default(true)
                            ->helperText('Разрешить участникам управлять записью'),
                        \Filament\Forms\Components\Toggle::make('mute_on_start')
                            ->label('Mute Users on Start')
                            ->helperText('Отключить микрофоны при входе'),
                        \Filament\Forms\Components\Toggle::make('webcams_only_for_moderator')
                            ->label('Webcams Only for Moderator')
                            ->helperText('Только модератор может включать камеру'),
                        TextInput::make('max_participants')
                            ->label('Max Participants')
                            ->numeric()
                            ->default(0)
                            ->helperText('0 = неограничено'),
                        TextInput::make('duration')
                            ->label('Duration (minutes)')
                            ->numeric()
                            ->default(0)
                            ->helperText('0 = неограничено'),
                        TextInput::make('logout_url')
                            ->label('Logout URL')
                            ->url()
                            ->helperText('URL для перенаправления после выхода'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        Setting::updateOrCreate(
            ['key' => 'bbb_url'],
            ['value' => $data['bbb_url']]
        );

        Setting::updateOrCreate(
            ['key' => 'bbb_secret'],
            ['value' => $data['bbb_secret']]
        );

        // Save advanced settings
        Setting::updateOrCreate(['key' => 'bbb_record'], ['value' => $data['record'] ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'bbb_auto_start_recording'], ['value' => $data['auto_start_recording'] ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'bbb_allow_start_stop_recording'], ['value' => $data['allow_start_stop_recording'] ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'bbb_mute_on_start'], ['value' => $data['mute_on_start'] ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'bbb_webcams_only_for_moderator'], ['value' => $data['webcams_only_for_moderator'] ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'bbb_max_participants'], ['value' => $data['max_participants'] ?? 0]);
        Setting::updateOrCreate(['key' => 'bbb_duration'], ['value' => $data['duration'] ?? 0]);
        Setting::updateOrCreate(['key' => 'bbb_logout_url'], ['value' => $data['logout_url'] ?? '']);

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
