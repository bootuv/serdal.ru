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

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Базовые цены';

    protected static ?string $slug = 'prices';

    protected static ?string $navigationGroup = '';

    protected static ?string $modelLabel = 'Базовая цена';

    protected static ?string $pluralModelLabel = 'Базовые цены';

    protected static ?int $navigationSort = 90;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Тип урока')
                    ->options(function (?LessonType $record) {
                        $existingTypesQuery = LessonType::where('user_id', auth()->id());

                        if ($record) {
                            $existingTypesQuery->where('id', '!=', $record->id);
                        }

                        $existingTypes = $existingTypesQuery->pluck('type')->toArray();

                        $allTypes = [
                            LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                            LessonType::TYPE_GROUP => 'Групповой',
                        ];

                        return array_diff_key($allTypes, array_flip($existingTypes));
                    })
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state === LessonType::TYPE_INDIVIDUAL) {
                            $set('payment_type', 'per_lesson');
                        } elseif ($state === LessonType::TYPE_GROUP) {
                            $set('payment_type', 'monthly');
                        }
                    }),
                Forms\Components\Select::make('payment_type')
                    ->label('Тип оплаты')
                    ->options([
                        'per_lesson' => 'Поурочная оплата',
                        'monthly' => 'Помесячная оплата',
                    ])
                    ->default('per_lesson')
                    ->required()
                    ->live()
                    ->selectablePlaceholder(false),
                Forms\Components\TextInput::make('price')
                    ->label(fn(Forms\Get $get) => $get('payment_type') === 'monthly' ? 'Цена за месяц' : 'Цена за урок')
                    ->numeric()
                    ->required()
                    ->prefix('₽'),
                Forms\Components\TextInput::make('count_per_week')
                    ->label('Уроков в неделю')
                    ->numeric()
                    ->required(fn(Forms\Get $get) => $get('payment_type') === 'monthly')
                    ->visible(fn(Forms\Get $get) => $get('payment_type') === 'monthly'),
                Forms\Components\TextInput::make('duration')
                    ->label('Длительность (мин)')
                    ->numeric()
                    ->required()
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип урока')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                        LessonType::TYPE_GROUP => 'Групповой',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Тип оплаты')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'per_lesson' => 'Поурочная',
                        'monthly' => 'Помесячная',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('rub'),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Длительность')
                    ->suffix(' мин'),
                Tables\Columns\TextColumn::make('count_per_week')
                    ->label('В неделю')
                    ->suffix(' раз(а)')
                    ->placeholder('-'),
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

    public static function canCreate(): bool
    {
        return LessonType::where('user_id', auth()->id())->count() < 2;
    }
}
