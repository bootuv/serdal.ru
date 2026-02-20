<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LessonTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'lessonTypes';

    protected static ?string $title = 'Цены';
    protected static ?string $icon = 'heroicon-o-banknotes';
    protected static ?string $modelLabel = 'Цена';
    protected static ?string $pluralModelLabel = 'Цены';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Тип урока')
                    ->options([
                        \App\Models\LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                        \App\Models\LessonType::TYPE_GROUP => 'Групповой',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                        if ($state === \App\Models\LessonType::TYPE_INDIVIDUAL) {
                            $set('payment_type', 'per_lesson');
                        } elseif ($state === \App\Models\LessonType::TYPE_GROUP) {
                            $set('payment_type', 'monthly');
                        }
                    }),
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Цена за урок')
                            ->numeric()
                            ->required()
                            ->prefix('₽'),
                        Forms\Components\Select::make('payment_type')
                            ->label('Тип оплаты')
                            ->options([
                                'per_lesson' => 'Поурочная оплата',
                                'monthly' => 'Помесячная оплата',
                            ])
                            ->default('per_lesson')
                            ->required()
                            ->selectablePlaceholder(false),
                    ]),
                Forms\Components\TextInput::make('duration')
                    ->label('Длительность (мин)')
                    ->numeric()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->modelLabel('Цена')
            ->pluralModelLabel('Цены')
            ->emptyStateHeading('Цены не добавлены')
            ->emptyStateDescription('Добавьте хотя бы одну цену для старта.')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип урока')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        \App\Models\LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                        \App\Models\LessonType::TYPE_GROUP => 'Групповой',
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
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Добавить')
                    ->createAnother(false)
                    ->modalHeading('Добавить цену')
                    ->visible(fn() => $this->getOwnerRecord()->lessonTypes()->count() < 2)
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Тип урока')
                            ->options(function () {
                                $existingTypes = $this->getOwnerRecord()->lessonTypes()->pluck('type')->toArray();

                                $types = [
                                    \App\Models\LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                                    \App\Models\LessonType::TYPE_GROUP => 'Групповой',
                                ];

                                return array_diff_key($types, array_flip($existingTypes));
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                if ($state === \App\Models\LessonType::TYPE_INDIVIDUAL) {
                                    $set('payment_type', 'per_lesson');
                                } elseif ($state === \App\Models\LessonType::TYPE_GROUP) {
                                    $set('payment_type', 'monthly');
                                }
                            }),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Цена за урок')
                                    ->numeric()
                                    ->required()
                                    ->prefix('₽'),
                                Forms\Components\Select::make('payment_type')
                                    ->label('Тип оплаты')
                                    ->options([
                                        'per_lesson' => 'Поурочная оплата',
                                        'monthly' => 'Помесячная оплата',
                                    ])
                                    ->default('per_lesson')
                                    ->required()
                                    ->selectablePlaceholder(false),
                            ]),
                        Forms\Components\TextInput::make('duration')
                            ->label('Длительность (мин)')
                            ->numeric()
                            ->required(),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
