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

    protected static ?string $title = 'Типы уроков';
    protected static ?string $modelLabel = 'Тип урока';
    protected static ?string $pluralModelLabel = 'Типы уроков';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Тип')
                    ->options([
                        \App\Models\LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                        \App\Models\LessonType::TYPE_GROUP => 'Групповой',
                    ])
                    ->required(),
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
            ->modelLabel('Тип урока')
            ->pluralModelLabel('Типы уроков')
            ->emptyStateHeading('Типы уроков не добавлены')
            ->emptyStateDescription('Добавьте хотя бы один тип урока для старта.')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        \App\Models\LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                        \App\Models\LessonType::TYPE_GROUP => 'Групповой',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('rub'),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Длительность')
                    ->suffix(' мин'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Добавить')
                    ->createAnother(false)
                    ->modalHeading('Добавить тип урока')
                    ->visible(fn() => $this->getOwnerRecord()->lessonTypes()->count() < 2)
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Тип')
                            ->options(function () {
                                $existingTypes = $this->getOwnerRecord()->lessonTypes()->pluck('type')->toArray();

                                $types = [
                                    \App\Models\LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                                    \App\Models\LessonType::TYPE_GROUP => 'Групповой',
                                ];

                                return array_diff_key($types, array_flip($existingTypes));
                            })
                            ->required(),
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
