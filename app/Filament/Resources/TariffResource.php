<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TariffResource\Pages;
use App\Models\Tariff;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TariffResource extends Resource
{
    protected static ?string $model = Tariff::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Тарифы';

    protected static ?string $modelLabel = 'Тариф';

    protected static ?string $pluralModelLabel = 'Тарифы';

    protected static ?string $slug = 'tariffs';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основное')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Слаг (латиницей)')
                            ->helperText('Используется в URL и коде, например: basic')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('price')
                            ->label('Цена за период')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->prefix('₽')
                            ->helperText('0 = бесплатный тариф'),
                        Forms\Components\TextInput::make('period_days')
                            ->label('Период подписки (дней)')
                            ->numeric()
                            ->minValue(1)
                            ->default(30)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен')
                            ->helperText('Неактивные тарифы скрыты с сайта и недоступны для покупки')
                            ->default(true),
                        Forms\Components\Toggle::make('is_popular')
                            ->label('Популярный')
                            ->helperText('Отмечается бейджем «Популярный» на странице тарифов'),
                        Forms\Components\TextInput::make('sort')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
                Forms\Components\Section::make('Лимиты пакета')
                    ->schema([
                        Forms\Components\TextInput::make('lessons_per_month')
                            ->label('Занятий в месяц')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Пусто = без лимита'),
                        Forms\Components\TextInput::make('max_participants')
                            ->label('Макс. участников в занятии')
                            ->numeric()
                            ->minValue(2)
                            ->default(2)
                            ->required()
                            ->helperText('Вместе с преподавателем'),
                        Forms\Components\TextInput::make('max_duration_minutes')
                            ->label('Макс. длительность занятия (мин)')
                            ->numeric()
                            ->minValue(15)
                            ->helperText('Пусто = без лимита'),
                        Forms\Components\TextInput::make('recording_retention_days')
                            ->label('Хранение записей (дней)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Пусто = записи недоступны на тарифе'),
                    ])->columns(2),
                Forms\Components\Section::make('Описание для сайта')
                    ->schema([
                        Forms\Components\TextInput::make('short_description')
                            ->label('Короткое описание')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Подробное описание')
                            ->rows(4),
                        Forms\Components\TagsInput::make('features')
                            ->label('Что входит (список)')
                            ->placeholder('Добавьте пункт и нажмите Enter'),
                        Forms\Components\TagsInput::make('extra_features')
                            ->label('Доп. сервисы (список)')
                            ->placeholder('Добавьте пункт и нажмите Enter')
                            ->helperText('Выводятся на сайте под общим списком, отделены линией'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                Tables\Columns\TextColumn::make('sort')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->description(fn(Tariff $record) => $record->slug),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('rub')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lessons_per_month')
                    ->label('Занятий/мес')
                    ->placeholder('Без лимита'),
                Tables\Columns\TextColumn::make('max_participants')
                    ->label('Участников'),
                Tables\Columns\TextColumn::make('subscriptions_count')
                    ->label('Активных подписок')
                    ->counts([
                        'subscriptions' => fn($query) => $query->active(),
                    ]),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Активен'),
                Tables\Columns\IconColumn::make('is_popular')
                    ->label('Популярный')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn(Tariff $record) => $record->subscriptions()->exists()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTariffs::route('/'),
            'create' => Pages\CreateTariff::route('/create'),
            'edit' => Pages\EditTariff::route('/{record}/edit'),
        ];
    }
}
