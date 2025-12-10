<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\RoomResource\Pages;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Filament\Notifications\Notification;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Занятия';

    protected static ?string $modelLabel = 'Занятие';

    protected static ?string $pluralModelLabel = 'Занятия';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Название комнаты')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('welcome_msg')
                    ->label('Приветственное сообщение')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('presentations')
                    ->label('Презентации')
                    ->multiple()
                    ->acceptedFileTypes([
                        // PDF
                        'application/pdf',
                        // Microsoft PowerPoint
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        // Microsoft Word
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        // Microsoft Excel
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        // OpenOffice/LibreOffice
                        'application/vnd.oasis.opendocument.presentation',
                        'application/vnd.oasis.opendocument.text',
                        'application/vnd.oasis.opendocument.spreadsheet',
                        // Images
                        'image/jpeg',
                        'image/png',
                    ])
                    ->maxSize(102400) // 100MB in KB
                    ->directory('presentations')
                    ->columnSpanFull(),

                Forms\Components\Section::make('')
                    ->description('Настройте расписание автоматического запуска встреч')
                    ->schema([
                        Forms\Components\Repeater::make('schedules')
                            ->hiddenLabel()
                            ->relationship('schedules')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Тип расписания')
                                    ->options([
                                        'once' => 'Одноразовое',
                                        'recurring' => 'Повторяющееся',
                                    ])
                                    ->required()
                                    ->live()
                                    ->default('once'),

                                // One-time schedule
                                Forms\Components\DateTimePicker::make('scheduled_at')
                                    ->label('Дата и время')
                                    ->visible(fn(Forms\Get $get) => $get('type') === 'once')
                                    ->required(fn(Forms\Get $get) => $get('type') === 'once')
                                    ->native(false),

                                // Recurring schedule
                                Forms\Components\Select::make('recurrence_type')
                                    ->label('Повторение')
                                    ->options([
                                        'daily' => 'Ежедневно',
                                        'weekly' => 'Еженедельно',
                                        'monthly' => 'Ежемесячно',
                                    ])
                                    ->visible(fn(Forms\Get $get) => $get('type') === 'recurring')
                                    ->required(fn(Forms\Get $get) => $get('type') === 'recurring')
                                    ->live(),

                                Forms\Components\CheckboxList::make('recurrence_days')
                                    ->label('Дни недели')
                                    ->options([
                                        1 => 'Понедельник',
                                        2 => 'Вторник',
                                        3 => 'Среда',
                                        4 => 'Четверг',
                                        5 => 'Пятница',
                                        6 => 'Суббота',
                                        0 => 'Воскресенье',
                                    ])
                                    ->columns(3)
                                    ->visible(
                                        fn(Forms\Get $get) =>
                                        $get('type') === 'recurring' &&
                                        $get('recurrence_type') === 'weekly'
                                    )
                                    ->required(
                                        fn(Forms\Get $get) =>
                                        $get('type') === 'recurring' &&
                                        $get('recurrence_type') === 'weekly'
                                    ),

                                Forms\Components\Select::make('recurrence_day_of_month')
                                    ->label('День месяца')
                                    ->options(array_combine(range(1, 31), range(1, 31)))
                                    ->visible(
                                        fn(Forms\Get $get) =>
                                        $get('type') === 'recurring' &&
                                        $get('recurrence_type') === 'monthly'
                                    )
                                    ->required(
                                        fn(Forms\Get $get) =>
                                        $get('type') === 'recurring' &&
                                        $get('recurrence_type') === 'monthly'
                                    ),

                                Forms\Components\TimePicker::make('recurrence_time')
                                    ->label('Время')
                                    ->visible(fn(Forms\Get $get) => $get('type') === 'recurring')
                                    ->required(fn(Forms\Get $get) => $get('type') === 'recurring')
                                    ->native(false),

                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Начало')
                                    ->required()
                                    ->default(now())
                                    ->native(false),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Окончание')
                                    ->visible(fn(Forms\Get $get) => $get('type') === 'recurring')
                                    ->native(false)
                                    ->helperText('Оставьте пустым для бессрочного расписания'),

                                Forms\Components\TextInput::make('duration_minutes')
                                    ->label('Длительность (минуты)')
                                    ->numeric()
                                    ->default(60)
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(1440),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Активно')
                                    ->default(true)
                                    ->inline(false),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                $state['type'] === 'once'
                                ? 'Одноразовое: ' . ($state['scheduled_at'] ?? 'не указано')
                                : 'Повторяющееся: ' . match ($state['recurrence_type'] ?? '') {
                                    'daily' => 'Ежедневно',
                                    'weekly' => 'Еженедельно',
                                    'monthly' => 'Ежемесячно',
                                    default => 'не указано'
                                }
                            )
                            ->defaultItems(0)
                            ->addActionLabel('Добавить расписание'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                Tables\Columns\TextColumn::make('meeting_id')
                    ->label('Meeting ID')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_running')
                    ->label('Запущена')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('start')
                    ->label('Начать')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->url(fn(Room $record) => route('rooms.start', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('join')
                    ->label('Присоединиться')
                    ->icon('heroicon-o-user-plus')
                    ->url(fn(Room $record) => route('rooms.join', $record))
                    ->openUrlInNewTab()
                    ->visible(fn(Room $record) => $record->is_running),

                Tables\Actions\Action::make('stop')
                    ->label('Остановить')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(Room $record) => redirect()->route('rooms.stop', $record))
                    ->visible(fn(Room $record) => $record->is_running),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
