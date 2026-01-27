<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\HomeworkResource\Pages;
use App\Models\Homework;
use App\Models\Room;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HomeworkResource extends Resource
{
    protected static ?string $model = Homework::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Домашние задания';

    protected static ?string $modelLabel = 'Домашнее задание';

    protected static ?string $pluralModelLabel = 'Домашние задания';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Название')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\Select::make('type')
                                    ->label('Тип')
                                    ->options(Homework::getTypes())
                                    ->default(Homework::TYPE_HOMEWORK)
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        // Установить default max_score при смене типа
                                        $defaultScore = match ($state) {
                                            Homework::TYPE_HOMEWORK => 10,
                                            Homework::TYPE_EXAM, Homework::TYPE_PRACTICE => 100,
                                            default => 10,
                                        };
                                        $set('max_score', $defaultScore);
                                    }),
                            ]),

                        Forms\Components\TextInput::make('max_score')
                            ->label(fn(Forms\Get $get) => match ($get('type')) {
                                Homework::TYPE_HOMEWORK => 'Максимальная оценка',
                                default => 'Максимальный балл',
                            })
                            ->numeric()
                            ->default(10)
                            ->minValue(1)
                            ->maxValue(1000)
                            ->visible(fn(Forms\Get $get) => in_array($get('type'), [
                                Homework::TYPE_EXAM,
                                Homework::TYPE_PRACTICE,
                            ])),

                        Forms\Components\RichEditor::make('description')
                            ->label('Описание задания')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'bulletList',
                                'orderedList',
                                'link',
                            ]),

                        Forms\Components\Select::make('room_id')
                            ->label('Урок (опционально)')
                            ->relationship(
                                'room',
                                'name',
                                fn(Builder $query) => $query->where('user_id', auth()->id())
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Автозаполнение учеников из урока
                                if ($state) {
                                    $room = Room::with('participants')->find($state);
                                    if ($room) {
                                        $studentIds = $room->participants->pluck('id')->toArray();
                                        $set('students', $studentIds);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('students')
                            ->key(fn(Forms\Get $get) => 'students_' . $get('room_id'))
                            ->label('Ученики')
                            ->relationship(
                                'students',
                                'name',
                                function (Builder $query, Forms\Get $get) {
                                    $userId = auth()->id();
                                    // Показывать учеников текущего учителя или учеников выбранного урока
                                    $query->where('role', 'student')
                                        ->where(function ($q) use ($userId, $get) {
                                        $q->whereHas('teachers', function ($q) use ($userId) {
                                            $q->where('teacher_student.teacher_id', $userId);
                                        });

                                        // Если выбран урок, показываем также его участников
                                        $roomId = $get('room_id');
                                        if ($roomId) {
                                            $q->orWhereHas('assignedRooms', function ($q) use ($roomId) {
                                                $q->where('rooms.id', $roomId);
                                            });
                                        }
                                    });
                                }
                            )
                            ->optionsLimit(500)
                            ->multiple()
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required()
                            ->allowHtml()
                            ->getOptionLabelFromRecordUsing(fn(Model $record) => "
                                <div class=\"flex items-center gap-2 py-1\">
                                    <img src=\"{$record->avatar_url}\" class=\"w-6 h-6 rounded-full object-cover\" style=\"flex-shrink: 0;\">
                                    <span class=\"text-sm\">{$record->name}</span>
                                </div>
                            ")
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('deadline')
                            ->label('Срок сдачи')
                            ->native(false)
                            ->displayFormat('d.m.Y H:i')
                            ->minDate(now()),

                        Forms\Components\Toggle::make('is_visible')
                            ->label('Видимо для учеников')
                            ->default(true)
                            ->helperText('Скрытые задания не отображаются ученикам'),

                        Forms\Components\FileUpload::make('attachments')
                            ->label('Файлы задания')
                            ->multiple()
                            ->disk('s3')
                            ->directory(fn() => 'homework-attachments/' . auth()->id())
                            ->visibility('public')
                            // Optimization: Do not check file existence/metadata on S3 during load
                            ->fetchFileInformation(false)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-powerpoint',
                                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                'image/jpeg',
                                'image/png',
                                'image/gif',
                            ])
                            ->maxSize(51200) // 50MB
                            ->afterStateUpdated(\App\Helpers\FileUploadHelper::filamentCallback('attachments', 'homework-attachments', 1920, 1080, 85, true))
                            ->deleteUploadedFileUsing(\App\Helpers\FileUploadHelper::filamentDeleteCallback())
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('type_label')
                    ->label('Тип')
                    ->badge()
                    ->color(fn(Homework $record): string => $record->type_color)
                    ->icon(fn(Homework $record): string => $record->type_icon),

                Tables\Columns\TextColumn::make('room.name')
                    ->label('Урок')
                    ->placeholder('—')
                    ->toggleable(),



                Tables\Columns\TextColumn::make('submitted_count')
                    ->label('Сдано')
                    ->badge()
                    ->getStateUsing(function (Homework $record) {
                        $total = $record->students()->count();
                        $submitted = $record->submissions()->whereNotNull('submitted_at')->count();
                        return "{$submitted}/{$total}";
                    })
                    ->color(function (Homework $record) {
                        $total = $record->students()->count();
                        $submitted = $record->submissions()->whereNotNull('submitted_at')->count();

                        // Если учеников нет, серый
                        if ($total === 0)
                            return 'gray';

                        // Если сдали не все - оранжевый (warning), если все - зеленый (success)
                        return $submitted < $total ? 'warning' : 'success';
                    }),

                Tables\Columns\TextColumn::make('graded_count')
                    ->label('Проверено')
                    ->badge()
                    ->getStateUsing(function (Homework $record) {
                        $submitted = $record->submissions()->whereNotNull('submitted_at')->count();
                        $graded = $record->submissions()->whereNotNull('grade')->count();
                        return "{$graded}/{$submitted}";
                    })
                    ->color(function (Homework $record) {
                        $submitted = $record->submissions()->whereNotNull('submitted_at')->count();
                        $graded = $record->submissions()->whereNotNull('grade')->count(); // Исправлено: считаем только те, у которых есть оценка
            
                        // Если никто не сдал, то проверять нечего - серый
                        if ($submitted === 0)
                            return 'gray';

                        // Если проверены не все из сданных - оранжевый, иначе зеленый
                        return $graded < $submitted ? 'warning' : 'success';
                    }),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Срок сдачи')
                    ->formatStateUsing(fn($state) => format_datetime($state))
                    ->sortable()
                    ->color(fn(Homework $record) => $record->is_overdue ? 'danger' : null),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Видимо')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создано')
                    ->formatStateUsing(fn($state) => format_date($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('room_id')
                    ->label('Урок')
                    ->relationship('room', 'name', fn(Builder $query) => $query->where('user_id', auth()->id())),

                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Видимость'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                // Edit button removed from here, available in View page
            ])
            ->recordUrl(fn(Homework $record) => route('filament.app.resources.homework.view', $record))
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Нет домашних заданий')
            ->emptyStateDescription('Создайте первое домашнее задание для ваших учеников')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
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
            'index' => Pages\ListHomeworks::route('/'),
            'create' => Pages\CreateHomework::route('/create'),
            'view' => Pages\ViewHomework::route('/{record}'),
            'edit' => Pages\EditHomework::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('teacher_id', auth()->id());
    }
}
