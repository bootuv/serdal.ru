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
use Illuminate\Database\Eloquent\Model;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Filament\Notifications\Notification;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

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
                Forms\Components\Select::make('type')
                    ->label('Тип занятия')
                    ->options([
                        'individual' => 'Индивидуальное',
                        'group' => 'Групповое',
                    ])
                    ->required()
                    ->default('individual'),
                Forms\Components\Textarea::make('welcome_msg')
                    ->label('Приветственное сообщение')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\Select::make('participants')
                    ->label('Ученики')
                    ->relationship('participants', 'name', function (Builder $query) {
                        return $query->where('role', 'student')
                            ->whereHas('teachers', function ($q) {
                                $q->where('teacher_student.teacher_id', auth()->id());
                            });
                    })
                    ->multiple()
                    ->searchable(['name', 'email', 'username'])
                    ->preload(true)
                    ->allowHtml()
                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "
                        <div class=\"flex items-center gap-2 py-1\">
                            <img src=\"{$record->avatar_url}\" class=\"w-6 h-6 rounded-full object-cover\" style=\"flex-shrink: 0;\">
                            <span class=\"text-sm\">{$record->name}</span>
                        </div>
                    ")
                    ->extraAttributes(['class' => 'student-select'])
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
                                Forms\Components\Grid::make(1) // Single column layout for the item content
                                    ->schema([
                                        Forms\Components\Select::make('type')
                                            ->label('Тип расписания')
                                            ->options([
                                                'once' => 'Одноразовое (конкретная дата)',
                                                'recurring' => 'Повторяющееся (регулярное)',
                                            ])
                                            ->required()
                                            ->live()
                                            ->default('once')
                                            ->native(false),

                                        // One-time schedule
                                        Forms\Components\DateTimePicker::make('scheduled_at')
                                            ->label('Дата и время занятия')
                                            ->visible(fn(Forms\Get $get) => $get('type') === 'once')
                                            ->required(fn(Forms\Get $get) => $get('type') === 'once')
                                            ->native(false)
                                            ->seconds(false),

                                        // Recurring schedule Group
                                        Forms\Components\Fieldset::make('Настройки повторения')
                                            ->visible(fn(Forms\Get $get) => $get('type') === 'recurring')
                                            ->schema([
                                                Forms\Components\Select::make('recurrence_type')
                                                    ->label('Периодичность')
                                                    ->options([
                                                        'daily' => 'Ежедневно',
                                                        'weekly' => 'Еженедельно',
                                                        'monthly' => 'Ежемесячно',
                                                    ])
                                                    ->required()
                                                    ->live()
                                                    ->native(false),

                                                Forms\Components\CheckboxList::make('recurrence_days')
                                                    ->label('Выберите дни недели')
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
                                                    ->gridDirection('row')
                                                    ->visible(fn(Forms\Get $get) => $get('recurrence_type') === 'weekly')
                                                    ->required(fn(Forms\Get $get) => $get('recurrence_type') === 'weekly'),

                                                Forms\Components\Select::make('recurrence_day_of_month')
                                                    ->label('День месяца')
                                                    ->options(array_combine(range(1, 31), range(1, 31)))
                                                    ->visible(fn(Forms\Get $get) => $get('recurrence_type') === 'monthly')
                                                    ->required(fn(Forms\Get $get) => $get('recurrence_type') === 'monthly')
                                                    ->native(false),

                                                Forms\Components\TimePicker::make('recurrence_time')
                                                    ->label('Время начала')
                                                    ->required()
                                                    ->native(false)
                                                    ->seconds(false),

                                                Forms\Components\DatePicker::make('end_date')
                                                    ->label('Дата окончания (необязательно)')
                                                    ->native(false)
                                                    ->helperText('Если не указано, расписание будет действовать бессрочно'),
                                            ])
                                            ->columns(1), // Fieldset content in 1 column

                                        // Hidden Start Date for database compatibility (required column)
                                        // We default it to now() or scheduled_at roughly to satisfy the DB constraint
                                        Forms\Components\DatePicker::make('start_date')
                                            ->label('Дата начала расписания')
                                            ->required()
                                            ->default(now())
                                            ->native(false)
                                            // Only show for recurring, but ALWAYS save it. 
                                            // For 'once', it will save the default or the hidden value.
                                            ->visible(fn(Forms\Get $get) => $get('type') === 'recurring')
                                            ->dehydratedWhenHidden(true),

                                        Forms\Components\TextInput::make('duration_minutes')
                                            ->label('Длительность занятия (минуты)')
                                            ->numeric()
                                            ->default(60)
                                            ->required()
                                            ->minValue(1)
                                            ->maxValue(1440)
                                            ->step(5),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Расписание активно')
                                            ->default(true)
                                            ->inline(false)
                                            ->onColor('success')
                                            ->offColor('danger'),
                                    ]),
                            ])
                            ->columns(1) // Repeater items are full width (although inside Grid(1) effectively does the same, this ensures the container is 1 col)
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                $state['type'] === 'once'
                                ? '📅 Одноразовое: ' . (\Carbon\Carbon::parse($state['scheduled_at'] ?? now())->format('d.m.Y H:i'))
                                : '🔄 ' . match ($state['recurrence_type'] ?? '') {
                                    'daily' => 'Ежедневно',
                                    'weekly' => 'Еженедельно',
                                    'monthly' => 'Ежемесячно',
                                    default => 'Повторяющееся'
                                } . ' в ' . ($state['recurrence_time'] ?? '')
                            )
                            ->defaultItems(0)
                            ->addActionLabel('Добавить время занятия')
                            ->reorderableWithButtons()
                            ->cloneable()
                            ->collapsed(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->formatStateUsing(function (string $state, Room $record) {
                        $isGroup = $record->type === 'group';
                        $icon = $isGroup
                            ? '<svg class="w-5 h-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" data-tooltip-target="tooltip-type-' . $record->id . '"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>'
                            : '<svg class="w-5 h-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" data-tooltip-target="tooltip-type-' . $record->id . '"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>';

                        $tooltipText = $isGroup ? 'Групповое занятие' : 'Индивидуальное занятие';

                        return new \Illuminate\Support\HtmlString(
                            '<div class="flex items-center gap-2" title="' . $tooltipText . '">
                                ' . $icon . '
                                <span>' . e($state) . '</span>
                            </div>'
                        );
                    }),
                Tables\Columns\TextColumn::make('invitation_link')
                    ->label('Ссылка')
                    ->getStateUsing(fn() => 'Скопировать')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyableState(fn(Room $record) => route('rooms.join', $record))
                    ->copyMessage('Ссылка скопирована')
                    ->icon('heroicon-o-link'),
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
                    ->openUrlInNewTab()
                    ->visible(fn(Room $record) => !$record->is_running),

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
