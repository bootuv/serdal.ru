<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HelpCategoryResource\Pages;
use App\Models\HelpCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HelpCategoryResource extends Resource
{
    protected static ?string $model = HelpCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    /** Отдельного пункта меню нет — категории открываются табом на странице «База знаний» */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Категория';

    protected static ?string $pluralModelLabel = 'Категории';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('audience')
                    ->label('Раздел')
                    ->options(HelpCategory::AUDIENCES)
                    ->default(HelpCategory::AUDIENCE_STUDENT)
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->label('Слаг (URL)')
                    ->helperText('Оставьте пустым — сгенерируется из названия')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('icon')
                    ->label('Иконка (эмодзи)')
                    ->helperText('Например: 🎓 📅 💳 🎥')
                    ->maxLength(16),
                Forms\Components\Textarea::make('description')
                    ->label('Описание')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_published')
                    ->label('Опубликована')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon')
                    ->label('')
                    ->width('1%'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->description(fn(HelpCategory $record) => $record->slug)
                    ->searchable(),
                Tables\Columns\TextColumn::make('audience')
                    ->label('Раздел')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => HelpCategory::AUDIENCES[$state] ?? $state)
                    ->color(fn(string $state) => $state === HelpCategory::AUDIENCE_STUDENT ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Статей')
                    ->counts('articles'),
                Tables\Columns\ToggleColumn::make('is_published')
                    ->label('Опубликована'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('audience')
                    ->label('Раздел')
                    ->options(HelpCategory::AUDIENCES),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHelpCategories::route('/'),
            'create' => Pages\CreateHelpCategory::route('/create'),
            'edit' => Pages\EditHelpCategory::route('/{record}/edit'),
        ];
    }
}
