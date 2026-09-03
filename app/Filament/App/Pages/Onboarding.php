<?php

namespace App\Filament\App\Pages;

use App\Models\LessonType;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Services\YooKassaService;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use App\Models\User;

class Onboarding extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.onboarding';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Добро пожаловать!';

    public ?array $data = [];

    public function mount()
    {
        $user = Auth::user();

        // Safety check: redirect if already completed
        if ($user && $user->is_profile_completed) {
            return redirect()->route('filament.app.pages.dashboard');
        }

        $state = [
            'avatar' => $user->avatar,
            'whatsup' => $user->whatsup,
            'telegram' => $user->telegram,
        ];

        // fill() с массивом не применяет дефолты полей, поэтому первую строку
        // цен подставляем сами: шаг «Цены» сразу показывает открытую форму
        $existing = $user->lessonTypes()
            ->get(['type', 'payment_type', 'price', 'count_per_week', 'duration'])
            ->map->only(['type', 'payment_type', 'price', 'count_per_week', 'duration'])
            ->all();

        $state['lesson_types'] = $existing ?: [[
            'type' => LessonType::TYPE_INDIVIDUAL,
            'payment_type' => 'per_lesson',
            'price' => null,
            'count_per_week' => null,
            'duration' => 60,
        ]];

        // Предвыбор тарифа: выбранный на публичной странице при подаче заявки,
        // иначе бесплатный
        $desired = $user->desired_tariff_id ? Tariff::active()->find($user->desired_tariff_id) : null;
        $state['tariff_id'] = $desired?->id ?? Tariff::active()->where('price', 0)->value('id');
        $state['payment_method'] = 'sbp';

        $this->form->fill($state);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Профиль')
                        ->description('Фото и контакты')
                        ->icon('heroicon-o-user-circle')
                        ->schema([
                            Forms\Components\FileUpload::make('avatar')
                                ->label('Фото профиля')
                                ->helperText('Ученики увидят его в расписании и на занятиях')
                                ->disk('s3')
                                ->visibility('public')
                                // Optimization: Do not check file existence/metadata on S3 during load
                                ->fetchFileInformation(false)
                                ->image()
                                ->avatar()
                                ->imageEditor()
                                ->directory(fn() => 'avatars/' . auth()->id())
                                ->live()
                                ->deleteUploadedFileUsing(\App\Helpers\FileUploadHelper::filamentDeleteCallback()),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('whatsup')->label('WhatsApp')->placeholder('+7...'),
                                    Forms\Components\TextInput::make('telegram')->label('Telegram')->prefix('@'),
                                ]),
                        ]),

                    Forms\Components\Wizard\Step::make('Цены для учеников')
                        ->description('Сколько стоят ваши занятия')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Forms\Components\Repeater::make('lesson_types')
                                ->hiddenLabel()
                                ->addActionLabel('Добавить вторую цену (для другого типа занятий)')
                                ->defaultItems(1)
                                ->minItems(1)
                                ->maxItems(2)
                                ->reorderable(false)
                                // Единственную цену удалить нельзя — шаг не должен оставаться пустым
                                ->deletable(fn(Forms\Components\Repeater $component) => count($component->getState() ?? []) > 1)
                                ->itemLabel(fn(array $state): string => match ($state['type'] ?? null) {
                                    LessonType::TYPE_INDIVIDUAL => 'Индивидуальные занятия',
                                    LessonType::TYPE_GROUP => 'Групповые занятия',
                                    default => 'Базовая цена',
                                })
                                ->columns(2)
                                ->schema([
                                    Forms\Components\Select::make('type')
                                        ->label('Тип урока')
                                        ->options([
                                            LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                                            LessonType::TYPE_GROUP => 'Групповой',
                                        ])
                                        ->default(LessonType::TYPE_INDIVIDUAL)
                                        ->required()
                                        ->distinct()
                                        ->selectablePlaceholder(false)
                                        ->live()
                                        ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                            $set('payment_type', $state === LessonType::TYPE_GROUP ? 'monthly' : 'per_lesson');
                                        }),
                                    Forms\Components\Select::make('payment_type')
                                        ->label('Тип оплаты')
                                        ->options([
                                            'per_lesson' => 'Поурочная оплата',
                                            'monthly' => 'Помесячная оплата',
                                        ])
                                        ->default('per_lesson')
                                        ->required()
                                        ->live()
                                        ->selectablePlaceholder(false),
                                    Forms\Components\TextInput::make('price')
                                        ->label(fn(\Filament\Forms\Get $get) => $get('payment_type') === 'monthly' ? 'Цена за месяц' : 'Цена за урок')
                                        ->numeric()
                                        ->minValue(1)
                                        ->suffix('₽')
                                        ->required(),
                                    Forms\Components\TextInput::make('duration')
                                        ->label('Длительность')
                                        ->numeric()
                                        ->minValue(15)
                                        ->default(60)
                                        ->suffix('мин')
                                        ->required(),
                                    Forms\Components\TextInput::make('count_per_week')
                                        ->label('Уроков в неделю')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(fn(\Filament\Forms\Get $get) => $get('payment_type') === 'monthly')
                                        ->visible(fn(\Filament\Forms\Get $get) => $get('payment_type') === 'monthly'),
                                ]),
                        ]),

                    Forms\Components\Wizard\Step::make('Тариф')
                        ->description('Выберите подходящий план')
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Forms\Components\Radio::make('tariff_id')
                                ->hiddenLabel()
                                ->options(fn() => Tariff::active()->pluck('name', 'id')->all())
                                ->view('filament.forms.components.tariff-picker')
                                ->viewData([
                                    'tariffs' => Tariff::active()->get()->mapWithKeys(fn(Tariff $t) => [$t->id => [
                                        'title' => $t->name,
                                        'price' => $t->isFree()
                                            ? 'Бесплатно'
                                            : number_format($t->price, 0, ',', ' ') . ' ₽/мес',
                                        'subtitle' => $t->short_description ?: $t->participants_label,
                                        'popular' => $t->is_popular,
                                    ]])->all(),
                                ])
                                ->required()
                                ->live(),

                            ManageSubscription::paymentMethodField()
                                ->visible(fn(\Filament\Forms\Get $get) => YooKassaService::isConfigured()
                                    && ($t = Tariff::find($get('tariff_id'))) && !$t->isFree()),

                            Forms\Components\Placeholder::make('payment_note')
                                ->hiddenLabel()
                                ->content(function (\Filament\Forms\Get $get) {
                                    $tariff = Tariff::find($get('tariff_id'));

                                    if (!$tariff || $tariff->isFree()) {
                                        return '';
                                    }

                                    return YooKassaService::isConfigured()
                                        ? 'После нажатия «Завершить настройку» вы перейдёте на защищённую страницу оплаты ('
                                            . number_format($tariff->price, 0, ',', ' ') . ' ₽ за ' . $tariff->period_days
                                            . ' дней). Тариф включится сразу после оплаты, до этого действует бесплатный «Старт».'
                                        : 'Онлайн-оплата подключается — тариф можно будет оплатить позже в разделе «Подписка». Пока будет действовать бесплатный «Старт».';
                                })
                                ->visible(fn(\Filament\Forms\Get $get) => ($t = Tariff::find($get('tariff_id'))) && !$t->isFree()),
                        ]),
                ])
                    ->nextAction(fn(\Filament\Forms\Components\Actions\Action $action) => $action
                        ->label('Далее')
                        ->size(\Filament\Support\Enums\ActionSize::Large))
                    ->previousAction(fn(\Filament\Forms\Components\Actions\Action $action) => $action
                        ->label('Назад')
                        ->size(\Filament\Support\Enums\ActionSize::Large))
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" size="lg" icon="heroicon-m-check">Завершить настройку</x-filament::button>'
                    ))),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        $data = $this->form->getState();
        $user = Auth::user();

        /** @var User $user */
        // Пересоздаём базовые цены из шага «Цены для учеников»
        $user->lessonTypes()->delete();

        foreach ($data['lesson_types'] ?? [] as $item) {
            $user->lessonTypes()->create([
                'type' => $item['type'],
                'payment_type' => $item['payment_type'],
                'price' => $item['price'],
                'duration' => $item['duration'],
                'count_per_week' => ($item['payment_type'] ?? null) === 'monthly' ? ($item['count_per_week'] ?? null) : null,
            ]);
        }

        // Process Avatar
        if (isset($data['avatar'])) {
            $processed = \App\Helpers\FileUploadHelper::processFiles(
                $data['avatar'],
                'avatars',
                640,
                640
            );
            $data['avatar'] = $processed[0] ?? null;
        }

        $user->update([
            'avatar' => $data['avatar'],
            'whatsup' => $data['whatsup'],
            'telegram' => $data['telegram'],
            'is_profile_completed' => true,
        ]);

        // Бесплатный «Старт» — база до оплаты, чтобы пользователь
        // не остался без подписки, даже если передумает платить
        if (!$user->activeSubscription()) {
            $freeTariff = Tariff::active()->where('price', 0)->first();

            if ($freeTariff) {
                \App\Services\SubscriptionService::activate($user, $freeTariff);
            }
        }

        // Notify all admins about teacher completing onboarding
        $admins = User::where('role', User::ROLE_ADMIN)->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\TeacherCompletedOnboarding($user));
        }

        Notification::make()
            ->title('Профиль успешно настроен!')
            ->success()
            ->send();

        // Выбран платный тариф — сразу уводим на платёжную страницу
        $tariff = isset($data['tariff_id']) ? Tariff::active()->find($data['tariff_id']) : null;

        if ($tariff && !$tariff->isFree() && YooKassaService::isConfigured()) {
            $payment = SubscriptionPayment::create([
                'user_id' => $user->id,
                'tariff_id' => $tariff->id,
                'amount' => $tariff->price,
                'period_days' => $tariff->period_days,
                'status' => SubscriptionPayment::STATUS_PENDING,
                'gateway' => 'yookassa',
            ]);

            try {
                $url = YooKassaService::createPayment(
                    $payment,
                    route('subscription.payment.return', $payment),
                    methodType: $data['payment_method'] ?? null,
                );

                return redirect()->away($url);
            } catch (\Throwable $e) {
                $payment->update(['status' => SubscriptionPayment::STATUS_FAILED]);
                Notification::make()
                    ->title('Не удалось создать платёж')
                    ->body('Оплатить тариф «' . $tariff->name . '» можно в любой момент в разделе «Подписка».')
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }

        return redirect()->route('filament.app.pages.dashboard');
    }
}
