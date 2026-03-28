<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
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

    protected static ?string $navigationLabel = 'Настройки';

    protected static ?string $title = 'Настройки';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 10;

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
            'recording_auto_upload' => Setting::where('key', 'recording_auto_upload')->value('value') === '1',
            'recording_delete_after_upload' => Setting::where('key', 'recording_delete_after_upload')->value('value') === '1',
            'teacher_commission' => Setting::where('key', 'teacher_commission')->value('value') ?? 10,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                        Tabs\Tab::make('BigBlueButton')
                            ->schema([
                                Section::make('Глобальные настройки')
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
                                Section::make('Расширенные настройки')
                                    ->description('Настройки по умолчанию для всех вебинаров.')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('record')
                                            ->label('Запись встреч')
                                            ->default(true)
                                            ->helperText('Записывать встречи по умолчанию'),
                                        \Filament\Forms\Components\Toggle::make('auto_start_recording')
                                            ->label('Автостарт записи')
                                            ->default(true)
                                            ->helperText('Автоматически начинать запись'),
                                        \Filament\Forms\Components\Toggle::make('allow_start_stop_recording')
                                            ->label('Разрешить старт/стоп записи')
                                            ->default(true)
                                            ->helperText('Разрешить участникам управлять записью'),
                                        \Filament\Forms\Components\Toggle::make('mute_on_start')
                                            ->label('Выключить микрофоны при входе')
                                            ->helperText('Отключить микрофоны при входе'),
                                        \Filament\Forms\Components\Toggle::make('webcams_only_for_moderator')
                                            ->label('Вебкамеры только у модератора')
                                            ->helperText('Только модератор может включать камеру'),
                                        TextInput::make('max_participants')
                                            ->label('Макс. участников')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('0 = неограничено'),
                                        TextInput::make('duration')
                                            ->label('Длительность (мин)')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('0 = неограничено'),
                                    ]),
                            ]),
                        Tabs\Tab::make('Хранилище записей')
                            ->schema([
                                Section::make('Хранилище записей')
                                    ->description('Автоматическое сохранение записей в облачное хранилище (Yandex S3)')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('recording_auto_upload')
                                            ->label('Автозагрузка')
                                            ->helperText('Автоматически загружать новые записи в облачное хранилище'),
                                        \Filament\Forms\Components\Toggle::make('recording_delete_after_upload')
                                            ->label('Удалять с BBB после загрузки')
                                            ->helperText('Удалять оригинал записи с сервера BBB после успешной загрузки'),
                                    ]),
                            ]),
                        Tabs\Tab::make('Финансы')
                            ->schema([
                                TextInput::make('teacher_commission')
                                    ->label('Комиссия платформы (%)')
                                    ->numeric()
                                    ->default(10)
                                    ->suffix('%')
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->helperText('Процент, который удерживается с учителей за каждый урок.'),
                            ]),
                    ])
                    ->persistTabInQueryString()
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

        // Recording storage settings
        Setting::updateOrCreate(['key' => 'recording_auto_upload'], ['value' => $data['recording_auto_upload'] ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'recording_delete_after_upload'], ['value' => $data['recording_delete_after_upload'] ? '1' : '0']);

        // Finance settings
        Setting::updateOrCreate(['key' => 'teacher_commission'], ['value' => $data['teacher_commission'] ?? 10]);

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
