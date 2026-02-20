<?php

namespace App\Filament\App\Widgets;

use App\Models\LessonType;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LessonTypesTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Базовые цены';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        // Hide from dashboard, only show when explicitly utilized (e.g. on profile page)
        return !request()->routeIs('filament.app.pages.dashboard');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(LessonType::query()->where('user_id', auth()->id()))

            ->modelLabel('Базовая цена')
            ->pluralModelLabel('Базовые цены')
            ->emptyStateHeading('Базовые цены не добавлены')
            ->emptyStateDescription('Добавьте хотя бы одну базовую цену для старта.')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип урока')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                        LessonType::TYPE_GROUP => 'Групповой',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('rub'),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Тип оплаты')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'per_lesson' => 'Поурочная',
                        'monthly' => 'Помесячная',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('count_per_week')
                    ->label('В неделю')
                    ->suffix(' раз(а)')
                    ->placeholder('-'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        \Filament\Forms\Components\Select::make('type')
                            ->label('Тип урока')
                            ->options(function (?\App\Models\LessonType $record) {
                                $existingTypesQuery = LessonType::where('user_id', auth()->id());
                                if ($record) {
                                    $existingTypesQuery->where('id', '!=', $record->id);
                                }
                                $existingTypes = $existingTypesQuery->pluck('type')->toArray();
                                $types = [
                                    LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                                    LessonType::TYPE_GROUP => 'Групповой',
                                ];
                                return array_diff_key($types, array_flip($existingTypes));
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                if ($state === LessonType::TYPE_INDIVIDUAL) {
                                    $set('payment_type', 'per_lesson');
                                } elseif ($state === LessonType::TYPE_GROUP) {
                                    $set('payment_type', 'monthly');
                                }
                            }),
                        \Filament\Forms\Components\Grid::make(2)
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('price')
                                    ->label(fn(\Filament\Forms\Get $get) => $get('payment_type') === 'monthly' ? 'Цена за месяц' : 'Цена за урок')
                                    ->numeric()
                                    ->required()
                                    ->prefix('₽'),
                                \Filament\Forms\Components\Select::make('payment_type')
                                    ->label('Тип оплаты')
                                    ->options([
                                        'per_lesson' => 'Поурочная оплата',
                                        'monthly' => 'Помесячная оплата',
                                    ])
                                    ->default('per_lesson')
                                    ->required()
                                    ->selectablePlaceholder(false),
                            ]),
                        \Filament\Forms\Components\TextInput::make('duration')
                            ->label('Длительность (мин)')
                            ->numeric()
                            ->required(),
                    ]),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Добавить')
                    ->createAnother(false)
                    ->modalHeading('Добавить базовую цену')
                    ->visible(fn() => LessonType::where('user_id', auth()->id())->count() < 2)
                    ->form([
                        \Filament\Forms\Components\Select::make('type')
                            ->label('Тип урока')
                            ->options(function () {
                                $existingTypes = LessonType::where('user_id', auth()->id())
                                    ->pluck('type')
                                    ->toArray();

                                $types = [
                                    LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                                    LessonType::TYPE_GROUP => 'Групповой',
                                ];

                                return array_diff_key($types, array_flip($existingTypes));
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                if ($state === LessonType::TYPE_INDIVIDUAL) {
                                    $set('payment_type', 'per_lesson');
                                } elseif ($state === LessonType::TYPE_GROUP) {
                                    $set('payment_type', 'monthly');
                                }
                            }),
                        \Filament\Forms\Components\Select::make('payment_type')
                            ->label('Тип оплаты')
                            ->options([
                                'per_lesson' => 'Поурочная оплата',
                                'monthly' => 'Помесячная оплата',
                            ])
                            ->default('per_lesson')
                            ->required()
                            ->live()
                            ->selectablePlaceholder(false),
                        \Filament\Forms\Components\TextInput::make('price')
                            ->label(fn(\Filament\Forms\Get $get) => $get('payment_type') === 'monthly' ? 'Цена за месяц' : 'Цена за урок')
                            ->numeric()
                            ->required()
                            ->prefix('₽'),
                        \Filament\Forms\Components\TextInput::make('count_per_week')
                            ->label('Уроков в неделю')
                            ->numeric()
                            ->required(fn(\Filament\Forms\Get $get) => $get('payment_type') === 'monthly')
                            ->visible(fn(\Filament\Forms\Get $get) => $get('payment_type') === 'monthly'),
                        \Filament\Forms\Components\TextInput::make('duration')
                            ->label('Длительность (мин)')
                            ->numeric()
                            ->required(),
                    ])->columns(1)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        return $data;
                    }),
            ]);
    }
}
