<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\OfferSettings;
use App\Support\SeoSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
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
        $this->form->fill($this->settingsState());
    }

    /**
     * Текущие значения настроек для заполнения формы.
     */
    protected function settingsState(): array
    {
        return [
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
            'yookassa_test_shop_id' => Setting::where('key', 'yookassa_test_shop_id')->value('value'),
            'yookassa_test_secret_key' => Setting::where('key', 'yookassa_test_secret_key')->value('value'),
            'yookassa_test_mode' => Setting::where('key', 'yookassa_test_mode')->value('value') === '1',
            'yookassa_recurring_enabled' => Setting::where('key', 'yookassa_recurring_enabled')->value('value') === '1',
            'extra_lesson_price' => \App\Services\SubscriptionService::extraLessonPrice(),
            'extra_lessons_max' => \App\Services\SubscriptionService::extraLessonsMax(),
        ] + $this->seoState();
    }

    /** Значения SEO-настроек для формы: тексты с дефолтами, файлы — только загруженные. */
    protected function seoState(): array
    {
        $state = [];
        foreach (array_keys(SeoSettings::DEFAULTS) as $key) {
            $value = SeoSettings::get($key);
            $state[$key] = match (true) {
                in_array($key, SeoSettings::FILE_KEYS, true) => $value !== '' ? $value : null,
                in_array($key, ['seo_indexing_enabled', 'seo_ai_crawlers_enabled'], true) => $value !== '0',
                default => $value,
            };
        }

        return $state;
    }

    /** Поле загрузки картинки для SEO-настроек: хранится на s3 в папке seo. */
    protected function seoImageUpload(string $key, string $label): FileUpload
    {
        return FileUpload::make($key)
            ->label($label)
            ->disk('s3')
            ->directory('seo')
            ->visibility('public')
            ->fetchFileInformation(false)
            ->image()
            ->imagePreviewHeight('120')
            ->maxSize(2048);
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
                        Tabs\Tab::make('SEO')
                            ->badge(fn() => SeoSettings::enabled('seo_indexing_enabled') ? null : 'noindex')
                            ->badgeColor('danger')
                            ->schema([
                                Section::make('Заголовки и описания')
                                    ->description('Используются в <title>, meta description и превью ссылок в соцсетях и мессенджерах. Страницы со своими заголовками (репетиторы, тарифы, центр помощи) эти значения не переопределяют. Оптимальная длина: заголовок до 60 символов, описание 120–160.')
                                    ->schema([
                                        TextInput::make('seo_site_name')
                                            ->label('Название сайта')
                                            ->helperText('Подпись og:site_name и название организации в разметке schema.org.')
                                            ->required()
                                            ->maxLength(100),
                                        TextInput::make('seo_default_title')
                                            ->label('Заголовок по умолчанию')
                                            ->helperText('Для страниц без собственного заголовка.')
                                            ->required()
                                            ->maxLength(120),
                                        Textarea::make('seo_default_description')
                                            ->label('Описание по умолчанию')
                                            ->rows(3)
                                            ->required()
                                            ->maxLength(300)
                                            ->columnSpanFull(),
                                        TextInput::make('seo_home_title')
                                            ->label('Заголовок главной страницы')
                                            ->required()
                                            ->maxLength(120)
                                            ->columnSpanFull(),
                                        Textarea::make('seo_home_description')
                                            ->label('Описание главной страницы')
                                            ->rows(3)
                                            ->required()
                                            ->maxLength(300)
                                            ->columnSpanFull(),
                                    ])->columns(2),
                                Section::make('Картинки')
                                    ->description('Если файл не загружен, используется стандартный из папки проекта. Файлы хранятся на CDN.')
                                    ->schema([
                                        $this->seoImageUpload('seo_og_image', 'Картинка для соцсетей (og:image)')
                                            ->helperText('Показывается в превью ссылки в Telegram, WhatsApp, VK и др. Рекомендуемый размер 1200×630, JPG или PNG до 2 МБ.')
                                            ->imageCropAspectRatio('1200:630')
                                            ->imageResizeTargetWidth('1200')
                                            ->imageResizeTargetHeight('630')
                                            ->imageResizeMode('cover'),
                                        $this->seoImageUpload('seo_logo', 'Логотип для поисковиков')
                                            ->helperText('Логотип организации в разметке schema.org: поисковики показывают его в карточке компании. Квадратный или горизонтальный PNG на прозрачном фоне.'),
                                        $this->seoImageUpload('seo_apple_touch_icon', 'Иконка для iOS и Android')
                                            ->helperText('Показывается при добавлении сайта на главный экран телефона. PNG 180×180 или больше.')
                                            ->imageCropAspectRatio('1:1'),
                                    ])->columns(2),
                                Section::make('Индексация')
                                    ->description('Управляет robots.txt и meta robots на всех публичных страницах.')
                                    ->schema([
                                        Toggle::make('seo_indexing_enabled')
                                            ->label('Разрешить индексацию сайта')
                                            ->helperText('Выключайте только на тестовом стенде: все страницы получат noindex, а robots.txt закроет сайт целиком.')
                                            ->live()
                                            ->columnSpanFull(),
                                        Toggle::make('seo_ai_crawlers_enabled')
                                            ->label('Разрешить ИИ-краулеры')
                                            ->helperText('Включено: ChatGPT, Claude, Perplexity и другие ИИ-ассистенты могут читать публичные страницы и находить сайт, в robots.txt есть ссылка на llms.txt. Выключено: боты, собирающие данные для обучения моделей, получают запрет.')
                                            ->columnSpanFull(),
                                        TextInput::make('seo_yandex_verification')
                                            ->label('Код подтверждения Яндекс Вебмастера')
                                            ->helperText('Значение content из мета-тега yandex-verification.')
                                            ->maxLength(100),
                                        TextInput::make('seo_google_verification')
                                            ->label('Код подтверждения Google Search Console')
                                            ->helperText('Значение content из мета-тега google-site-verification.')
                                            ->maxLength(100),
                                    ])->columns(2),
                                Section::make('Дополнительно')
                                    ->schema([
                                        Textarea::make('seo_social_links')
                                            ->label('Ссылки на соцсети и каталоги')
                                            ->helperText('По одной ссылке в строке: Telegram, VK, YouTube, Дзен, отзовики. Попадают в разметку организации (sameAs) — поисковики связывают их с сайтом.')
                                            ->rows(4)
                                            ->placeholder("https://t.me/serdal\nhttps://vk.com/serdal"),
                                        Textarea::make('seo_llms_description')
                                            ->label('Описание сайта для ИИ (llms.txt)')
                                            ->helperText('Один абзац о том, что это за сайт и для кого. Отдаётся ИИ-ассистентам по адресу /llms.txt вместе со ссылками на разделы, тарифами и списком преподавателей.')
                                            ->rows(5)
                                            ->maxLength(1000),
                                        Textarea::make('seo_head_extra')
                                            ->label('Дополнительный код в <head>')
                                            ->helperText('Счётчики Яндекс Метрики, Google Analytics, пиксели и другие теги. Вставляется на все публичные страницы как есть — проверяйте корректность HTML.')
                                            ->rows(6)
                                            ->columnSpanFull(),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Эквайринг')
                            ->badge(fn() => \App\Services\YooKassaService::isTestMode() ? 'тест' : null)
                            ->badgeColor('warning')
                            ->schema([
                                Section::make('ЮKassa')
                                    ->description('Ключи из личного кабинета ЮKassa: Интеграция → Ключи API. Боевые и тестовые ключи хранятся отдельно — какие используются, определяет переключатель «Тестовый режим». Там же настройте HTTP-уведомления (payment.succeeded и payment.canceled) на адрес /payments/yookassa/callback.')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('yookassa_test_mode')
                                            ->label('Тестовый режим')
                                            ->helperText('Платежи идут через тестовый магазин ЮKassa: деньги не списываются, подходят тестовые карты. Сохранённые способы оплаты привязаны к магазину, поэтому после переключения режима привязки из другого магазина работать не будут. Быстро переключить режим можно кнопкой в шапке страницы.')
                                            ->live()
                                            ->columnSpanFull(),
                                        \Filament\Forms\Components\Fieldset::make('Боевой магазин')
                                            ->schema([
                                                TextInput::make('yookassa_shop_id')
                                                    ->label('shopId (идентификатор магазина)')
                                                    ->maxLength(255),
                                                TextInput::make('yookassa_secret_key')
                                                    ->label('Секретный ключ')
                                                    ->password()
                                                    ->revealable()
                                                    ->maxLength(255),
                                            ]),
                                        \Filament\Forms\Components\Fieldset::make('Тестовый магазин')
                                            ->schema([
                                                TextInput::make('yookassa_test_shop_id')
                                                    ->label('shopId тестового магазина')
                                                    ->maxLength(255),
                                                TextInput::make('yookassa_test_secret_key')
                                                    ->label('Секретный ключ тестового магазина')
                                                    ->password()
                                                    ->revealable()
                                                    ->maxLength(255),
                                            ]),
                                        \Filament\Forms\Components\Toggle::make('yookassa_recurring_enabled')
                                            ->label('Автоплатежи включены в ЮKassa')
                                            ->helperText('Относится к боевому магазину. Включайте после того, как менеджер ЮKassa активирует магазину автоплатежи (сохранение способа оплаты, автопродление). Сохраняются карта, счёт СБП, SberPay, T-Pay и ЮMoney. До этого галочки сохранения скрыты от пользователей — иначе платежи с сохранением завершаются ошибкой. В тестовом режиме автоплатежи доступны всегда.')
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

    /**
     * Кнопка в шапке: быстрое переключение боевого/тестового режима ЮKassa
     * без сохранения остальной формы.
     */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('toggleYooKassaTestMode')
                ->label(fn() => \App\Services\YooKassaService::isTestMode() ? 'ЮKassa: тестовый режим' : 'ЮKassa: боевой режим')
                ->icon(fn() => \App\Services\YooKassaService::isTestMode() ? 'heroicon-o-beaker' : 'heroicon-o-banknotes')
                ->color(fn() => \App\Services\YooKassaService::isTestMode() ? 'warning' : 'gray')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading(fn() => \App\Services\YooKassaService::isTestMode() ? 'Переключить ЮKassa в боевой режим?' : 'Переключить ЮKassa в тестовый режим?')
                ->modalDescription(fn() => \App\Services\YooKassaService::isTestMode()
                    ? 'Платежи пойдут через боевой магазин с реальным списанием денег.'
                    : 'Платежи пойдут через тестовый магазин: деньги не списываются, подходят тестовые карты ЮKassa. Несохранённые изменения в форме будут сброшены.')
                ->modalSubmitActionLabel('Переключить')
                ->modalCancelActionLabel('Отмена')
                ->action(function () {
                    $enable = !\App\Services\YooKassaService::isTestMode();

                    Setting::updateOrCreate(['key' => 'yookassa_test_mode'], ['value' => $enable ? '1' : '0']);
                    $this->form->fill($this->settingsState());

                    Notification::make()
                        ->title($enable ? 'ЮKassa переключена в тестовый режим' : 'ЮKassa переключена в боевой режим')
                        ->body($enable && !\App\Services\YooKassaService::isConfigured()
                            ? 'Ключи тестового магазина не заполнены — платежи будут недоступны, пока вы их не укажете.'
                            : null)
                        ->{$enable ? 'warning' : 'success'}()
                        ->send();
                }),
        ];
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
        Setting::updateOrCreate(['key' => 'yookassa_test_shop_id'], ['value' => $data['yookassa_test_shop_id'] ?? '']);
        Setting::updateOrCreate(['key' => 'yookassa_test_secret_key'], ['value' => $data['yookassa_test_secret_key'] ?? '']);
        Setting::updateOrCreate(['key' => 'yookassa_test_mode'], ['value' => !empty($data['yookassa_test_mode']) ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'yookassa_recurring_enabled'], ['value' => !empty($data['yookassa_recurring_enabled']) ? '1' : '0']);

        // Extra lessons
        Setting::updateOrCreate(['key' => 'extra_lesson_price'], ['value' => (string) max(1, (int) ($data['extra_lesson_price'] ?? 0))]);
        Setting::updateOrCreate(['key' => 'extra_lessons_max'], ['value' => (string) max(1, (int) ($data['extra_lessons_max'] ?? 0))]);

        // SEO
        foreach (array_keys(SeoSettings::DEFAULTS) as $key) {
            $value = $data[$key] ?? null;
            if (in_array($key, ['seo_indexing_enabled', 'seo_ai_crawlers_enabled'], true)) {
                $value = !empty($value) ? '1' : '0';
            } elseif (is_array($value)) {
                $value = (string) (reset($value) ?: '');
            } else {
                $value = trim((string) $value);
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        SeoSettings::flush();

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
