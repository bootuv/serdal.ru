<?php

namespace App\Filament\App\Widgets;

use App\Models\LessonType;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LessonTypesTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Типы уроков';

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

            ->modelLabel('Тип урока')
            ->pluralModelLabel('Типы уроков')
            ->emptyStateHeading('Типы уроков не добавлены')
            ->emptyStateDescription('Добавьте хотя бы один тип урока для старта.')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
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
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        \Filament\Forms\Components\Select::make('type')
                            ->label('Тип')
                            ->options([
                                LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                                LessonType::TYPE_GROUP => 'Групповой',
                            ])
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
                                    ->label('Цена за урок')
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
                    ->modalHeading('Добавить тип урока')
                    ->visible(fn() => LessonType::where('user_id', auth()->id())->count() < 2)
                    ->form([
                        \Filament\Forms\Components\Select::make('type')
                            ->label('Тип')
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
                        \Filament\Forms\Components\Grid::make(2)
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('price')
                                    ->label('Цена за урок')
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
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        return $data;
                    }),
            ]);
    }
}
