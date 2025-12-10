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
                        'individual' => 'Индивидуальный',
                        'group' => 'Групповой',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('price')
                    ->label('Цена')
                    ->numeric(),
                Forms\Components\TextInput::make('count_per_week')
                    ->label('Количество в неделю')
                    ->numeric(),
                Forms\Components\TextInput::make('duration')
                    ->label('Длительность')
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('Тип'),
                Tables\Columns\TextColumn::make('price')->label('Цена'),
                Tables\Columns\TextColumn::make('count_per_week')->label('Количество в неделю'),
                Tables\Columns\TextColumn::make('duration')->label('Длительность'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
