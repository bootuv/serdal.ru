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
            'legal_name' => Setting::where('key', 'legal_name')->value('value'),
            'legal_inn' => Setting::where('key', 'legal_inn')->value('value'),
            'legal_ogrn' => Setting::where('key', 'legal_ogrn')->value('value'),
            'legal_address' => Setting::where('key', 'legal_address')->value('value'),
            'legal_email' => Setting::where('key', 'legal_email')->value('value') ?? 'info@serdal.ru',
            'legal_phone' => Setting::where('key', 'legal_phone')->value('value'),
            'alfabank_username' => Setting::where('key', 'alfabank_username')->value('value'),
            'alfabank_password' => Setting::where('key', 'alfabank_password')->value('value'),
            'alfabank_gateway_url' => Setting::where('key', 'alfabank_gateway_url')->value('value'),
            'alfabank_test_mode' => Setting::where('key', 'alfabank_test_mode')->value('value') === '1',
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
                        Tabs\Tab::make('Реквизиты')
                            ->schema([
                                Section::make('Реквизиты юридического лица / ИП')
                                    ->description('Отображаются в подвале сайта и на странице оферты. Обязательное требование банка для интернет-эквайринга.')
                                    ->schema([
                                        TextInput::make('legal_name')
                                            ->label('Наименование юр. лица / ИП')
                                            ->placeholder('ИП Иванов Иван Иванович')
                                            ->maxLength(255),
                                        TextInput::make('legal_inn')
                                            ->label('ИНН')
                                            ->maxLength(12),
                                        TextInput::make('legal_ogrn')
                                            ->label('ОГРН / ОГРНИП')
                                            ->maxLength(15),
                                        TextInput::make('legal_address')
                                            ->label('Адрес (юридический)')
                                            ->maxLength(255),
                                        TextInput::make('legal_email')
                                            ->label('E-mail для связи')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('legal_phone')
                                            ->label('Телефон')
                                            ->tel()
                                            ->maxLength(32),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Эквайринг')
                            ->schema([
                                Section::make('Интернет-эквайринг Альфа-Банка')
                                    ->description('Учётные данные API платёжного шлюза. Выдаются банком после одобрения заявки на подключение.')
                                    ->schema([
                                        TextInput::make('alfabank_username')
                                            ->label('Логин API (userName)')
                                            ->maxLength(255),
                                        TextInput::make('alfabank_password')
                                            ->label('Пароль API')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(255),
                                        TextInput::make('alfabank_gateway_url')
                                            ->label('URL шлюза (необязательно)')
                                            ->placeholder('https://pay.alfabank.ru/payment/rest/')
                                            ->helperText('Пусто = стандартный адрес: боевой или тестовый в зависимости от переключателя ниже.')
                                            ->url()
                                            ->maxLength(255),
                                        \Filament\Forms\Components\Toggle::make('alfabank_test_mode')
                                            ->label('Тестовый режим')
                                            ->helperText('Платежи идут через тестовый контур банка (alfa.rbsuat.com), деньги не списываются.'),
                                    ]),
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

        // Legal requisites
        foreach (['legal_name', 'legal_inn', 'legal_ogrn', 'legal_address', 'legal_email', 'legal_phone'] as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $data[$key] ?? '']);
        }

        // Acquiring (Alfa-Bank)
        Setting::updateOrCreate(['key' => 'alfabank_username'], ['value' => $data['alfabank_username'] ?? '']);
        Setting::updateOrCreate(['key' => 'alfabank_password'], ['value' => $data['alfabank_password'] ?? '']);
        Setting::updateOrCreate(['key' => 'alfabank_gateway_url'], ['value' => $data['alfabank_gateway_url'] ?? '']);
        Setting::updateOrCreate(['key' => 'alfabank_test_mode'], ['value' => !empty($data['alfabank_test_mode']) ? '1' : '0']);

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
