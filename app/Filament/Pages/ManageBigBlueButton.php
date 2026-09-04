<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\OfferSettings;
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
            'b2b_enabled' => Setting::where('key', 'b2b_enabled')->value('value') !== '0',
            'b2b_title' => Setting::where('key', 'b2b_title')->value('value') ?? 'Для образовательных центров (B2B)',
            'b2b_description' => Setting::where('key', 'b2b_description')->value('value') ?? 'Пакет для онлайн-школ и образовательных центров: white-label, администрирование и поддержка с SLA.',
            'b2b_price_label' => Setting::where('key', 'b2b_price_label')->value('value') ?? 'от 14 900 ₽',
            'b2b_price_note' => Setting::where('key', 'b2b_price_note')->value('value') ?? '5 рабочих мест включено',
            'b2b_features' => json_decode(Setting::where('key', 'b2b_features')->value('value') ?? '', true) ?: [
                '5 рабочих мест преподавателей включено (дополнительное место — 1 900 ₽/мес)',
                'White-label: платформа под брендом вашего центра',
                'Административная панель для управления преподавателями и учениками',
                'Приоритетная поддержка и SLA',
                'Обучение и онбординг команды',
            ],
            'b2b_email' => Setting::where('key', 'b2b_email')->value('value') ?? 'info@serdal.ru',
            'legal_name' => Setting::where('key', 'legal_name')->value('value'),
            'legal_inn' => Setting::where('key', 'legal_inn')->value('value'),
            'legal_ogrn' => Setting::where('key', 'legal_ogrn')->value('value'),
            'legal_address' => Setting::where('key', 'legal_address')->value('value'),
            'legal_email' => Setting::where('key', 'legal_email')->value('value') ?? 'info@serdal.ru',
            'legal_phone' => Setting::where('key', 'legal_phone')->value('value'),
            'offer_edition_date' => Setting::where('key', 'offer_edition_date')->value('value') ?: null,
            'offer_payment_provider' => Setting::where('key', 'offer_payment_provider')->value('value') ?: OfferSettings::OFFER_DEFAULTS['offer_payment_provider'],
            'offer_payment_methods' => Setting::where('key', 'offer_payment_methods')->value('value') ?: OfferSettings::OFFER_DEFAULTS['offer_payment_methods'],
            'offer_refund_days' => Setting::where('key', 'offer_refund_days')->value('value') ?: OfferSettings::OFFER_DEFAULTS['offer_refund_days'],
            'offer_refund_processing_days' => Setting::where('key', 'offer_refund_processing_days')->value('value') ?: OfferSettings::OFFER_DEFAULTS['offer_refund_processing_days'],
            'yookassa_shop_id' => Setting::where('key', 'yookassa_shop_id')->value('value'),
            'yookassa_secret_key' => Setting::where('key', 'yookassa_secret_key')->value('value'),
            'yookassa_recurring_enabled' => Setting::where('key', 'yookassa_recurring_enabled')->value('value') === '1',
            'extra_lesson_price' => \App\Services\SubscriptionService::extraLessonPrice(),
            'extra_lessons_max' => \App\Services\SubscriptionService::extraLessonsMax(),
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
                        Tabs\Tab::make('B2B-блок')
                            ->schema([
                                Section::make('Блок «Для образовательных центров» на странице тарифов')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('b2b_enabled')
                                            ->label('Показывать блок на сайте')
                                            ->default(true),
                                        TextInput::make('b2b_title')
                                            ->label('Заголовок')
                                            ->required()
                                            ->maxLength(255),
                                        \Filament\Forms\Components\Textarea::make('b2b_description')
                                            ->label('Описание')
                                            ->rows(2),
                                        TextInput::make('b2b_price_label')
                                            ->label('Цена (текст)')
                                            ->helperText('Например: «от 14 900 ₽» — «/мес» добавляется автоматически')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('b2b_price_note')
                                            ->label('Подпись под ценой')
                                            ->maxLength(255),
                                        \Filament\Forms\Components\TagsInput::make('b2b_features')
                                            ->label('Что входит (список)')
                                            ->placeholder('Добавьте пункт и нажмите Enter'),
                                        TextInput::make('b2b_email')
                                            ->label('Email для кнопки «Написать нам»')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),
                                    ]),
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
                        Tabs\Tab::make('Оферта')
                            ->schema([
                                Section::make('Условия публичной оферты')
                                    ->description('Подставляются в текст на страницах «Публичная оферта» и «Тарифы». Тарифы и их характеристики берутся из раздела «Тарифы», реквизиты — со вкладки «Реквизиты».')
                                    ->schema([
                                        \Filament\Forms\Components\DatePicker::make('offer_edition_date')
                                            ->label('Дата редакции оферты')
                                            ->helperText('Если не указана — строка «Редакция от …» не выводится.')
                                            ->native(false)
                                            ->displayFormat('d.m.Y'),
                                        TextInput::make('offer_payment_provider')
                                            ->label('Платёжный сервис')
                                            ->helperText('Например: ЮKassa')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('offer_payment_methods')
                                            ->label('Способы оплаты (текст)')
                                            ->helperText('Вставляется во фразу «Оплата производится … с помощью платёжного сервиса …»')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('offer_refund_days')
                                            ->label('Срок отказа от услуги (календарных дней)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required(),
                                        TextInput::make('offer_refund_processing_days')
                                            ->label('Срок возврата средств (рабочих дней)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required(),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Эквайринг')
                            ->schema([
                                Section::make('ЮKassa')
                                    ->description('Ключи из личного кабинета ЮKassa: Интеграция → Ключи API. Для тестовых платежей укажите shopId и ключ тестового магазина. Там же настройте HTTP-уведомления (payment.succeeded и payment.canceled) на адрес /payments/yookassa/callback.')
                                    ->schema([
                                        TextInput::make('yookassa_shop_id')
                                            ->label('shopId (идентификатор магазина)')
                                            ->maxLength(255),
                                        TextInput::make('yookassa_secret_key')
                                            ->label('Секретный ключ')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(255),
                                        \Filament\Forms\Components\Toggle::make('yookassa_recurring_enabled')
                                            ->label('Автоплатежи включены в ЮKassa')
                                            ->helperText('Включайте после того, как менеджер ЮKassa активирует магазину автоплатежи (сохранение карты, автопродление). До этого привязка карты скрыта от пользователей — иначе платежи с сохранением карты завершаются ошибкой. В тестовом магазине автоплатежи доступны сразу.')
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Дополнительные занятия')
                                    ->description('Учитель может докупить занятия сверх лимита тарифа. Докупленные занятия не сгорают, расходуются после лимита тарифа и переносятся при смене тарифа. Цена намеренно выше стоимости занятия внутри тарифа — это страховка, а не замена апгрейду.')
                                    ->schema([
                                        TextInput::make('extra_lesson_price')
                                            ->label('Цена одного занятия, ₽')
                                            ->numeric()
                                            ->minValue(1)
                                            ->required(),
                                        TextInput::make('extra_lessons_max')
                                            ->label('Максимум за одну покупку')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(100)
                                            ->required(),
                                    ])->columns(2),
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

        // B2B block
        Setting::updateOrCreate(['key' => 'b2b_enabled'], ['value' => !empty($data['b2b_enabled']) ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'b2b_title'], ['value' => $data['b2b_title'] ?? '']);
        Setting::updateOrCreate(['key' => 'b2b_description'], ['value' => $data['b2b_description'] ?? '']);
        Setting::updateOrCreate(['key' => 'b2b_price_label'], ['value' => $data['b2b_price_label'] ?? '']);
        Setting::updateOrCreate(['key' => 'b2b_price_note'], ['value' => $data['b2b_price_note'] ?? '']);
        Setting::updateOrCreate(['key' => 'b2b_features'], ['value' => json_encode(array_values($data['b2b_features'] ?? []), JSON_UNESCAPED_UNICODE)]);
        Setting::updateOrCreate(['key' => 'b2b_email'], ['value' => $data['b2b_email'] ?? '']);

        // Legal requisites
        foreach (['legal_name', 'legal_inn', 'legal_ogrn', 'legal_address', 'legal_email', 'legal_phone'] as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $data[$key] ?? '']);
        }

        // Offer terms
        Setting::updateOrCreate(['key' => 'offer_edition_date'], ['value' => $data['offer_edition_date'] ?? '']);
        Setting::updateOrCreate(['key' => 'offer_payment_provider'], ['value' => $data['offer_payment_provider'] ?? '']);
        Setting::updateOrCreate(['key' => 'offer_payment_methods'], ['value' => $data['offer_payment_methods'] ?? '']);
        Setting::updateOrCreate(['key' => 'offer_refund_days'], ['value' => (string) ($data['offer_refund_days'] ?? '')]);
        Setting::updateOrCreate(['key' => 'offer_refund_processing_days'], ['value' => (string) ($data['offer_refund_processing_days'] ?? '')]);

        // Acquiring (YooKassa)
        Setting::updateOrCreate(['key' => 'yookassa_shop_id'], ['value' => $data['yookassa_shop_id'] ?? '']);
        Setting::updateOrCreate(['key' => 'yookassa_secret_key'], ['value' => $data['yookassa_secret_key'] ?? '']);
        Setting::updateOrCreate(['key' => 'yookassa_recurring_enabled'], ['value' => !empty($data['yookassa_recurring_enabled']) ? '1' : '0']);

        // Extra lessons
        Setting::updateOrCreate(['key' => 'extra_lesson_price'], ['value' => (string) max(1, (int) ($data['extra_lesson_price'] ?? 0))]);
        Setting::updateOrCreate(['key' => 'extra_lessons_max'], ['value' => (string) max(1, (int) ($data['extra_lessons_max'] ?? 0))]);

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
