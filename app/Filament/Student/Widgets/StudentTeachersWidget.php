<?php

namespace App\Filament\Student\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StudentTeachersWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Мои преподаватели';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                auth()->user()->teachers()->getQuery()
            )
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->size(50),

                Tables\Columns\TextColumn::make('name')
                    ->label('Имя')
                    ->weight('bold')
                    ->description(fn(\App\Models\User $record) => $record->email),

                Tables\Columns\TextColumn::make('subjects.name')
                    ->label('Предметы')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон')
                    ->icon('heroicon-m-phone')
                    ->copyable(),
            ])
            ->paginated(false)
            ->recordUrl(fn(\App\Models\User $record): string => route('tutors.show', ['username' => $record->username]))
            ->emptyStateHeading('У вас нет преподавателей')
            ->emptyStateDescription('');
    }
}
