<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Отзывы';
    protected static ?string $modelLabel = 'Отзыв';
    protected static ?string $pluralModelLabel = 'Отзывы';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('is_reported', true)
            ->where('is_rejected', false)
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Кто оставил')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('teacher_id')
                    ->label('Кому оставили')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('rating')
                    ->label('Оценка')
                    ->options([
                        1 => '1 звезда',
                        2 => '2 звезды',
                        3 => '3 звезды',
                        4 => '4 звезды',
                        5 => '5 звезд',
                    ])
                    ->default(5)
                    ->required(),
                Forms\Components\Textarea::make('text')
                    ->label('Текст отзыва')
                    ->required()
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Кто оставил')
                    ->searchable(),
                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Кому оставили')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Оценка')
                    ->formatStateUsing(fn($state) => str_repeat('★', $state) . str_repeat('☆', 5 - $state))
                    ->color('warning')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('text')
                    ->label('Текст')
                    ->limit(50)
                    ->tooltip(fn($state) => $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->formatStateUsing(fn($state) => format_datetime($state))
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_rejected')
                    ->label('Отклонен')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('reported')
                    ->label('Жалобы')
                    ->query(fn(Builder $query) => $query->where('is_reported', true)),
                Tables\Filters\Filter::make('rejected')
                    ->label('Отклоненные')
                    ->query(fn(Builder $query) => $query->where('is_rejected', true)),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('reported_badge')
                    ->label('Поступила жалоба')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->url(fn(Review $record) => route('filament.admin.resources.reviews.edit', $record))
                    ->visible(fn(Review $record) => $record->is_reported && !$record->is_rejected),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
