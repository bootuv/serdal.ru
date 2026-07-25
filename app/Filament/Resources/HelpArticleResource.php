<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HelpArticleResource\Pages;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HelpArticleResource extends Resource
{
    protected static ?string $model = HelpArticle::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'База знаний';

    protected static ?string $modelLabel = 'Инструкция';

    protected static ?string $pluralModelLabel = 'Инструкции';

    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Левая колонка: контент статьи
                Forms\Components\Group::make([
                    Forms\Components\TextInput::make('title')
                        ->label('Заголовок')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('help_category_id')
                        ->label('Категория')
                        ->options(
                            HelpCategory::orderBy('audience')->orderBy('sort_order')->get()
                                ->groupBy(fn(HelpCategory $c) => $c->audience_label)
                                ->map(fn($group) => $group->pluck('name', 'id'))
                                ->toArray()
                        )
                        ->required()
                        ->searchable()
                        ->native(false),
                    Forms\Components\Textarea::make('excerpt')
                        ->label('Короткое описание')
                        ->helperText('Показывается в списке статей и в результатах поиска')
                        ->rows(2),
                    Forms\Components\RichEditor::make('content')
                        ->label('Текст инструкции')
                        ->fileAttachmentsDisk('s3')
                        ->fileAttachmentsVisibility('public')
                        ->fileAttachmentsDirectory('help-articles'),
                    Forms\Components\TextInput::make('slug')
                        ->label('Слаг (URL)')
                        ->helperText('Оставьте пустым — сгенерируется из заголовка')
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Forms\Components\Toggle::make('is_published')
                        ->label('Опубликована')
                        ->default(true),
                ])->columnSpan(2),

                // Правая колонка: видео и служебные поля
                Forms\Components\Group::make([
                    Forms\Components\FileUpload::make('video_file')
                        ->label('Видеофайл')
                        ->helperText('MP4 до 100 МБ — загрузится на CDN и покажется в начале статьи. Конвертируйте в MP4 (H.264, до 720p) заранее.')
                        ->disk('s3')
                        ->directory('help-videos')
                        ->visibility('public')
                        ->acceptedFileTypes(['video/mp4', 'video/webm'])
                        ->maxSize(102400),
                    Forms\Components\TextInput::make('video_url')
                        ->label('Или ссылка на видео')
                        ->helperText('Kinescope, YouTube, VK Видео или Rutube — используется, если файл не загружен')
                        ->url()
                        ->maxLength(255),
                ])->columnSpan(1),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->description(fn(HelpArticle $record) => $record->slug),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Категория'),
                Tables\Columns\TextColumn::make('category.audience')
                    ->label('Раздел')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => HelpCategory::AUDIENCES[$state] ?? $state)
                    ->color(fn(string $state) => $state === HelpCategory::AUDIENCE_STUDENT ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('has_video')
                    ->label('Видео')
                    ->badge()
                    ->state(fn(HelpArticle $record) => filled($record->video_file) ? 'Файл' : (filled($record->video_url) ? 'Ссылка' : '—'))
                    ->color(fn(string $state) => match ($state) {
                        'Файл' => 'success',
                        'Ссылка' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('views_count')
                    ->label('Просмотры')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_published')
                    ->label('Опубликована'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('help_category_id')
                    ->label('Категория')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('audience')
                    ->label('Раздел')
                    ->options(HelpCategory::AUDIENCES)
                    ->query(fn($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn($q, $value) => $q->whereHas('category', fn($c) => $c->where('audience', $value))
                    )),
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
            'index' => Pages\ListHelpArticles::route('/'),
            'create' => Pages\CreateHelpArticle::route('/create'),
            'edit' => Pages\EditHelpArticle::route('/{record}/edit'),
        ];
    }
}
