<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use App\Models\Tariff;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Подписки';

    protected static ?string $modelLabel = 'Подписка';

    protected static ?string $pluralModelLabel = 'Подписки';

    protected static ?string $slug = 'subscriptions';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Преподаватель')
                    ->options(
                        User::query()
                            ->whereIn('role', [User::ROLE_TUTOR, User::ROLE_MENTOR])
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('tariff_id')
                    ->label('Тариф')
                    ->options(Tariff::orderBy('sort')->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($tariff = Tariff::find($state)) {
                            $set('price', $tariff->price);
                        }
                    }),
                Forms\Components\TextInput::make('price')
                    ->label('Цена (снимок)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₽')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        Subscription::STATUS_ACTIVE => 'Активна',
                        Subscription::STATUS_EXPIRED => 'Истекла',
                        Subscription::STATUS_CANCELLED => 'Отменена',
                    ])
                    ->default(Subscription::STATUS_ACTIVE)
                    ->required(),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Начало')
                    ->default(now())
                    ->required(),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('Окончание')
                    ->helperText('Пусто = бессрочно (для бесплатного тарифа)'),
                Forms\Components\TextInput::make('comment')
                    ->label('Комментарий администратора')
                    ->maxLength(255),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Преподаватель')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tariff.name')
                    ->label('Тариф')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('rub'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn(Subscription $record) => $record->status_label)
                    ->color(fn(Subscription $record) => $record->isActive() ? 'success' : ($record->status === Subscription::STATUS_CANCELLED ? 'danger' : 'gray')),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Начало')
                    ->dateTime('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Окончание')
                    ->dateTime('d.m.Y')
                    ->placeholder('Бессрочно')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tariff_id')
                    ->label('Тариф')
                    ->options(Tariff::orderBy('sort')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        Subscription::STATUS_ACTIVE => 'Активна',
                        Subscription::STATUS_EXPIRED => 'Истекла',
                        Subscription::STATUS_CANCELLED => 'Отменена',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'tariff']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
