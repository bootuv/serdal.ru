<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\MaterialResource\Pages;
use App\Models\MaterialFolder;
use App\Models\TeacherMaterial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MaterialResource extends Resource
{
    protected static ?string $model = TeacherMaterial::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationLabel = 'Материалы';

    protected static ?string $modelLabel = 'Материал';

    protected static ?string $pluralModelLabel = 'Материалы';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Файл')
                            ->disk('s3')
                            ->directory(fn () => 'teacher-materials/' . auth()->id())
                            ->visibility('public')
                            ->storeFileNamesIn('original_name')
                            ->maxSize(512000) // 500 МБ
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // Автозаполнение названия из имени файла
                                if (filled($get('title'))) {
                                    return;
                                }

                                $file = is_array($state) ? reset($state) : $state;

                                if ($file instanceof TemporaryUploadedFile) {
                                    $set('title', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                                }
                            }),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Название')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\Select::make('folder_id')
                                    ->label('Папка')
                                    ->relationship(
                                        'folder',
                                        'name',
                                        fn (Builder $query) => $query->where('teacher_id', auth()->id())->orderBy('name')
                                    )
                                    ->default(fn () => request()->integer('folder') ?: null)
                                    ->placeholder('Без папки (корень каталога)')
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Название папки')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->createOptionUsing(fn (array $data) => MaterialFolder::create([
                                        'teacher_id' => auth()->id(),
                                        'name' => $data['name'],
                                    ])->getKey()),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Описание (опционально)')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\Radio::make('visibility')
                            ->label('Видимость')
                            ->options(TeacherMaterial::getVisibilityOptions())
                            ->descriptions([
                                TeacherMaterial::VISIBILITY_PRIVATE => 'Виден только вам',
                                TeacherMaterial::VISIBILITY_ROOMS => 'Виден ученикам выбранных занятий',
                                TeacherMaterial::VISIBILITY_ALL => 'Виден всем вашим ученикам',
                            ])
                            ->default(TeacherMaterial::VISIBILITY_ALL)
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('rooms')
                            ->label('Занятия (группы)')
                            ->relationship(
                                'rooms',
                                'name',
                                fn (Builder $query) => $query->where('user_id', auth()->id())
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('visibility') === TeacherMaterial::VISIBILITY_ROOMS),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('teacher_id', auth()->id())
            ->with(['folder', 'teacher']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
