<?php

namespace App\Filament\App\Pages;

use App\Models\LessonType;
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
            'instagram' => $user->instagram,
            'telegram' => $user->telegram,
        ];

        // Уже добавленные цены (возврат в онбординг) — иначе репитер покажет
        // одну пустую строку по умолчанию
        $existing = $user->lessonTypes()
            ->get(['type', 'payment_type', 'price', 'count_per_week', 'duration'])
            ->map->only(['type', 'payment_type', 'price', 'count_per_week', 'duration'])
            ->all();

        if ($existing) {
            $state['lesson_types'] = $existing;
        }

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

                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\TextInput::make('whatsup')->label('WhatsApp')->placeholder('+7...'),
                                    Forms\Components\TextInput::make('instagram')->label('Instagram')->prefix('@'),
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

                    Forms\Components\Wizard\Step::make('Готово')
                        ->description('Проверьте и завершите')
                        ->icon('heroicon-o-rocket-launch')
                        ->schema([
                            Forms\Components\Placeholder::make('summary')
                                ->hiddenLabel()
                                ->content(function (\Filament\Forms\Get $get) {
                                    $prices = collect($get('lesson_types') ?? [])
                                        ->filter(fn($item) => !empty($item['price']))
                                        ->map(function ($item) {
                                            $type = ($item['type'] ?? null) === LessonType::TYPE_GROUP ? 'Групповой' : 'Индивидуальный';
                                            $unit = ($item['payment_type'] ?? null) === 'monthly' ? '₽/мес' : '₽/урок';

                                            return $type . ' — ' . number_format((float) $item['price'], 0, ',', ' ') . ' ' . $unit;
                                        });

                                    $desired = auth()->user()->desired_tariff_id
                                        ? \App\Models\Tariff::active()->find(auth()->user()->desired_tariff_id)
                                        : null;

                                    $next = $desired && !$desired->isFree()
                                        ? 'После завершения вы перейдёте к оплате выбранного тарифа «' . $desired->name . '».'
                                        : 'После завершения вы получите доступ ко всем функциям платформы.';

                                    return new HtmlString(
                                        '<div class="text-sm leading-6">'
                                        . '<p class="font-medium">Ваши цены для учеников:</p>'
                                        . '<ul class="list-disc ps-5">' . $prices->map(fn($p) => '<li>' . e($p) . '</li>')->implode('') . '</ul>'
                                        . '<p class="mt-3 text-gray-500 dark:text-gray-400">' . e($next) . '</p>'
                                        . '</div>'
                                    );
                                }),
                        ]),
                ])
                    ->nextAction(fn(\Filament\Forms\Components\Actions\Action $action) => $action->label('Далее'))
                    ->previousAction(fn(\Filament\Forms\Components\Actions\Action $action) => $action->label('Назад'))
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
            'instagram' => $data['instagram'],
            'telegram' => $data['telegram'],
            'is_profile_completed' => true,
        ]);

        // Если тариф не был выбран при регистрации — подключаем бесплатный «Старт»
        if (!$user->activeSubscription()) {
            $freeTariff = \App\Models\Tariff::active()->where('price', 0)->first();

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

        // Платный тариф, выбранный на публичной странице тарифов, — ведём
        // сразу на страницу подписки с открытой формой оплаты
        $desired = $user->desired_tariff_id
            ? \App\Models\Tariff::active()->find($user->desired_tariff_id)
            : null;

        if ($desired && !$desired->isFree()) {
            return redirect(ManageSubscription::getUrl(['pay' => $desired->id], panel: 'app'));
        }

        return redirect()->route('filament.app.pages.dashboard');
    }
}
