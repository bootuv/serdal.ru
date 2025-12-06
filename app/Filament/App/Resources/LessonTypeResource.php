<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\LessonTypeResource\Pages;
use App\Filament\App\Resources\LessonTypeResource\RelationManagers;
use App\Models\LessonType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LessonTypeResource extends Resource
{
    protected static ?string $model = LessonType::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Типы уроков';

    protected static ?string $modelLabel = 'Тип урока';

    protected static ?string $pluralModelLabel = 'Типы уроков';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Тип')
                    ->options([
                        LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                        LessonType::TYPE_GROUP => 'Групповой',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('price')
                    ->label('Цена')
                    ->numeric()
                    ->required()
                    ->prefix('₽'),
                Forms\Components\TextInput::make('duration')
                    ->label('Длительность (мин)')
                    ->numeric()
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Описание')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
            ->filters([
                //
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
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
            'index' => Pages\ListLessonTypes::route('/'),
            'create' => Pages\CreateLessonType::route('/create'),
            'edit' => Pages\EditLessonType::route('/{record}/edit'),
        ];
    }
}
