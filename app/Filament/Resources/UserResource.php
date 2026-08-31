<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Password;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Group;
use App\Filament\Resources\UserResource\RelationManagers\LessonTypesRelationManager;
use Filament\Tables\Filters\SelectFilter;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Пользователи';

    protected static ?string $modelLabel = 'Пользователь';

    protected static ?string $pluralModelLabel = 'Пользователи';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('avatar')
                    ->label('Фото профиля')
                    ->disk('s3')
                    ->visibility('public')
                    ->fetchFileInformation(false)
                    ->image()
                    ->avatar()
                    ->imageEditor()
                    ->directory(fn($record) => 'avatars/' . ($record->id ?? 'new'))
                    ->live()
                    ->deleteUploadedFileUsing(\App\Helpers\FileUploadHelper::filamentDeleteCallback()),
                Forms\Components\Group::make([
                    TextInput::make('last_name')
                        ->label('Фамилия')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('first_name')
                        ->label('Имя')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('middle_name')
                        ->label('Отчество')
                        ->maxLength(255),
                ])->columns(3)->columnSpanFull(),
                TextInput::make('status')
                    ->label('Статус')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label('Электронная почта')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
                TextInput::make('username')
                    ->label('Имя пользователя')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),

                TextInput::make('password')
                    ->label('Пароль')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->maxLength(255)
                    ->dehydrated(fn($state) => filled($state))
                    ->dehydrateStateUsing(fn($state) => bcrypt($state))
                    ->required(fn(string $operation): bool => $operation === 'create'),
                Select::make('role')
                    ->label('Роль')
                    ->options([
                        User::ROLE_ADMIN => 'Администратор',
                        User::ROLE_MENTOR => 'Ментор',
                        User::ROLE_TUTOR => 'Репетитор',
                        User::ROLE_STUDENT => 'Ученик',
                    ])
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Публичный профиль активен')
                    ->default(true)
                    ->helperText('Отключенные профили не отображаются на публичной странице')
                    ->inline(false),
                Forms\Components\Toggle::make('is_blocked')
                    ->label('Профиль заблокирован')
                    ->default(false)
                    ->helperText('Заблокированные пользователи не могут авторизоваться')
                    ->inline(false)
                    ->columnSpanFull(),
                Select::make('subjects')
                    ->label('Предметы')
                    ->multiple()
                    ->relationship('subjects', 'name')
                    ->preload()
                    ->columnSpanFull(),
                Select::make('directs')
                    ->label('Направления')
                    ->multiple()
                    ->relationship('directs', 'name')
                    ->preload()
                    ->columnSpanFull(),
                Select::make('grade')
                    ->label('Классы')
                    ->multiple()
                    ->options([
                        'preschool' => 'Дошкольники',
                        1 => 1,
                        2 => 2,
                        3 => 3,
                        4 => 4,
                        5 => 5,
                        6 => 6,
                        7 => 7,
                        8 => 8,
                        9 => 9,
                        10 => 10,
                        11 => 11,
                        'adults' => 'Взрослые',
                    ])
                    ->columnSpanFull(),
                RichEditor::make('about')
                    ->label('О себе')
                    ->columnSpan(2),
                RichEditor::make('extra_info')
                    ->label('Дополнительная информация')
                    ->columnSpan(2),

                Group::make([
                    TextInput::make('phone')->tel()->label('Телефон'),
                    TextInput::make('whatsup')->tel()->label('WhatsApp'),
                    TextInput::make('instagram')->label('Instagram'),
                    TextInput::make('telegram')->label('Telegram'),
                ])->columns(2)->columnSpanFull(),

                Forms\Components\Section::make('Финансы')
                    ->schema([
                        TextInput::make('commission_rate')
                            ->label('Индивидуальная комиссия (%)')
                            ->helperText(function () {
                                $global = \App\Models\Setting::where('key', 'teacher_commission')->value('value') ?? 10;
                                return "Если не указано, используется глобальная настройка ($global%)";
                            })
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                    ])
                    ->visible(fn(Forms\Get $get) => in_array($get('role'), [User::ROLE_TUTOR, User::ROLE_MENTOR]))
                    ->columnSpanFull(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Роль')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Публичность')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_blocked')
                    ->label('Статус')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_profile_completed')
                    ->label('Онбординг')
                    ->getStateUsing(fn($record) => in_array($record->role, [User::ROLE_TUTOR, User::ROLE_MENTOR]) ? $record->is_profile_completed : null)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->formatStateUsing(fn($state) => format_date($state))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Роль')
                    ->multiple()
                    ->options([
                        User::ROLE_ADMIN => 'Администратор',
                        User::ROLE_MENTOR => 'Ментор',
                        User::ROLE_TUTOR => 'Репетитор',
                        User::ROLE_STUDENT => 'Ученик',
                    ]),
                SelectFilter::make('direct')
                    ->label('Направление')
                    ->multiple()
                    ->relationship('directs', 'name'),
                SelectFilter::make('subject')
                    ->label('Предмет')
                    ->multiple()
                    ->relationship('subjects', 'name'),
                SelectFilter::make('grade')
                    ->label('Класс')
                    ->multiple()
                    ->options([
                        'preschool' => 'Дошкольники',
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
                        'adults' => 'Взрослые',
                    ]),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('assignSubscription')
                    // Если тариф уже назначен, кнопка показывает его название —
                    // клик открывает ту же форму для смены тарифа
                    ->label(fn(User $record) => $record->activeSubscription()?->tariff->name ?? 'Назначить подписку')
                    ->icon('heroicon-o-credit-card')
                    ->button()
                    ->color(fn(User $record) => $record->activeSubscription() ? 'gray' : 'primary')
                    ->visible(fn(User $record) => in_array($record->role, [User::ROLE_TUTOR, User::ROLE_MENTOR]))
                    ->modalHeading(fn(User $record) => ($record->activeSubscription() ? 'Изменить подписку: ' : 'Назначить подписку: ') . $record->name)
                    ->modalDescription(function (User $record) {
                        $current = $record->activeSubscription();

                        return $current
                            ? 'Сейчас: «' . $current->tariff->name . '»' . ($current->ends_at ? ' до ' . $current->ends_at->format('d.m.Y') : ' (бессрочно)') . '. Текущая подписка будет заменена.'
                            : 'У пользователя нет активной подписки.';
                    })
                    ->form(static::assignSubscriptionForm())
                    ->fillForm(fn(User $record) => static::assignSubscriptionFill($record))
                    ->action(fn(User $record, array $data) => static::assignSubscription($record, $data)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Форма ручного назначения подписки (используется в таблице и на странице пользователя).
     */
    public static function assignSubscriptionForm(): array
    {
        return [
            Forms\Components\Select::make('tariff_id')
                ->label('Тариф')
                ->options(
                    \App\Models\Tariff::active()->get()
                        ->mapWithKeys(fn($tariff) => [
                            $tariff->id => $tariff->name . ' — ' . number_format($tariff->price, 0, ',', ' ') . ' ₽/мес',
                        ])
                )
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    if ($tariff = \App\Models\Tariff::find($state)) {
                        $set('days', $tariff->period_days);
                    }
                }),
            Forms\Components\Toggle::make('unlimited')
                ->label('Бессрочно')
                ->helperText('Подписка без даты окончания — например, максимальный тариф навсегда.')
                ->live(),
            Forms\Components\Toggle::make('free')
                ->label('Без оплаты')
                ->helperText('Преподаватель увидит пометку «Предоставлен бесплатно» — без цены и кнопки оплаты.')
                ->default(false),
            Forms\Components\TextInput::make('days')
                ->label('Срок действия (дней)')
                ->numeric()
                ->minValue(1)
                ->default(30)
                ->required(fn(Forms\Get $get) => !$get('unlimited'))
                ->visible(fn(Forms\Get $get) => !$get('unlimited')),
            Forms\Components\TextInput::make('comment')
                ->label('Комментарий')
                ->placeholder('Например: выдано бесплатно за помощь с тестированием')
                ->maxLength(255),
        ];
    }

    /**
     * Предзаполнение формы значениями текущей подписки (для смены тарифа).
     */
    public static function assignSubscriptionFill(User $record): array
    {
        $current = $record->activeSubscription();

        if (!$current) {
            return [];
        }

        return [
            'tariff_id' => $current->tariff_id,
            'unlimited' => $current->ends_at === null && !$current->tariff->isFree(),
            'days' => $current->tariff->period_days,
            'free' => $current->isComplimentary(),
        ];
    }

    /**
     * Назначает подписку пользователю от имени администратора.
     */
    public static function assignSubscription(User $record, array $data): void
    {
        $tariff = \App\Models\Tariff::findOrFail($data['tariff_id']);

        \App\Services\SubscriptionService::activate(
            $record,
            $tariff,
            days: !empty($data['unlimited']) ? null : (int) $data['days'],
            unlimited: !empty($data['unlimited']),
            comment: $data['comment'] ?: 'Назначена администратором: ' . auth()->user()->name,
            price: !empty($data['free']) ? 0 : null,
        );

        \Filament\Notifications\Notification::make()
            ->title('Подписка назначена')
            ->body('«' . $tariff->name . '» для ' . $record->name . (!empty($data['unlimited']) ? ' (бессрочно)' : ' на ' . $data['days'] . ' дн.'))
            ->success()
            ->send();
    }

    public static function getRelations(): array
    {
        return [
            LessonTypesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
