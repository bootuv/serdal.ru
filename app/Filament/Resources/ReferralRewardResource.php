<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferralRewardResource\Pages;
use App\Models\ReferralReward;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Начисления партнёрской программы (только просмотр — создаются автоматически при оплате).
 */
class ReferralRewardResource extends Resource
{
    protected static ?string $model = ReferralReward::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Приглашения';

    protected static ?string $modelLabel = 'Начисление';

    protected static ?string $pluralModelLabel = 'Партнёрская программа';

    protected static ?string $slug = 'referral-rewards';

    protected static ?int $navigationSort = 9;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('referrer.name')
                    ->label('Пригласил')
                    ->description(fn(ReferralReward $record) => $record->referrer?->email)
                    ->searchable(),
                Tables\Columns\TextColumn::make('referred.name')
                    ->label('Приглашённый')
                    ->description(fn(ReferralReward $record) => $record->referred?->email)
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment.amount')
                    ->label('Оплата')
                    ->formatStateUsing(fn($state, ReferralReward $record) => number_format($state, 0, ',', ' ') . ' ₽ · ' . $record->payment?->tariff?->name)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('referrer_lessons')
                    ->label('Пригласившему')
                    ->formatStateUsing(fn($state) => '+' . $state),
                Tables\Columns\TextColumn::make('referred_lessons')
                    ->label('Приглашённому')
                    ->formatStateUsing(fn($state) => '+' . $state),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => ReferralReward::statusLabels()[$state] ?? $state)
                    ->color(fn(string $state) => match ($state) {
                        ReferralReward::STATUS_CREDITED => 'success',
                        ReferralReward::STATUS_LIMIT => 'warning',
                        default => 'danger',
                    })
                    ->description(fn(ReferralReward $record) => $record->note),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ReferralReward::statusLabels()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferralRewards::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
