<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Filament\Resources\ReviewResource\RelationManagers;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Отзывы';

    protected static ?string $modelLabel = 'Отзыв';

    protected static ?string $pluralModelLabel = 'Отзывы';

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
                    ->color('warning'),
                Tables\Columns\TextColumn::make('text')
                    ->label('Текст')
                    ->limit(50)
                    ->tooltip(fn($state) => $state),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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
