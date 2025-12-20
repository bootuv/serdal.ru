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
            ->description('Создайте хотя бы один тип урока для продолжения.')
            ->modelLabel('Тип урока')
            ->pluralModelLabel('Типы уроков')
            ->emptyStateHeading('Типы уроков не добавлены')
            ->emptyStateDescription('Создайте свой первый тип урока для старта.')
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
                Tables\Columns\TextColumn::make('duration')
                    ->label('Длительность')
                    ->suffix(' мин'),
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
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('price')
                            ->label('Цена')
                            ->numeric()
                            ->required()
                            ->prefix('₽'),
                        \Filament\Forms\Components\TextInput::make('duration')
                            ->label('Длительность (мин)')
                            ->numeric()
                            ->required(),
                    ]),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->form([
                        \Filament\Forms\Components\Select::make('type')
                            ->label('Тип')
                            ->options([
                                LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                                LessonType::TYPE_GROUP => 'Групповой',
                            ])
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('price')
                            ->label('Цена')
                            ->numeric()
                            ->required()
                            ->prefix('₽'),
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
