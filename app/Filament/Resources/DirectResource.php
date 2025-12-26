<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DirectResource\Pages;
use App\Filament\Resources\DirectResource\RelationManagers;
use App\Models\Direct;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DirectResource extends Resource
{
    protected static ?string $model = Direct::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationLabel = 'Направления';

    protected static ?string $modelLabel = 'Направление';

    protected static ?string $pluralModelLabel = 'Направления';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Название')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
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
            'index' => Pages\ListDirects::route('/'),
            'create' => Pages\CreateDirect::route('/create'),
            'edit' => Pages\EditDirect::route('/{record}/edit'),
        ];
    }
}
