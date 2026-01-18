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
            'vk_access_token' => Setting::where('key', 'vk_access_token')->value('value'),
            'vk_group_id' => Setting::where('key', 'vk_group_id')->value('value'),
            'vk_auto_upload' => Setting::where('key', 'vk_auto_upload')->value('value') === '1',
            'vk_delete_after_upload' => Setting::where('key', 'vk_delete_after_upload')->value('value') === '1',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
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
                Section::make('VK Video')
                    ->description('Автоматический экспорт записей в VK Video')
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('vk_auto_upload')
                            ->label('Автозагрузка в VK')
                            ->helperText('Автоматически загружать записи в VK Video'),
                        \Filament\Forms\Components\Toggle::make('vk_delete_after_upload')
                            ->label('Удалять с BBB после загрузки')
                            ->helperText('Удалять оригинал записи с сервера BBB после успешной загрузки в VK'),
                        TextInput::make('vk_access_token')
                            ->label('VK Access Token')
                            ->password()
                            ->revealable()
                            ->maxLength(500)
                            ->helperText('Токен с правами video'),
                        TextInput::make('vk_group_id')
                            ->label('ID группы VK')
                            ->numeric()
                            ->helperText('ID закрытой группы для хранения записей'),
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

        // VK Video settings
        Setting::updateOrCreate(['key' => 'vk_access_token'], ['value' => $data['vk_access_token']]);
        Setting::updateOrCreate(['key' => 'vk_group_id'], ['value' => $data['vk_group_id']]);
        Setting::updateOrCreate(['key' => 'vk_auto_upload'], ['value' => $data['vk_auto_upload'] ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'vk_delete_after_upload'], ['value' => $data['vk_delete_after_upload'] ? '1' : '0']);

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
