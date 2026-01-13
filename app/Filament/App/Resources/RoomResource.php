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
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Занятия';

    protected static ?string $modelLabel = 'Занятие';

    protected static ?string $pluralModelLabel = 'Занятия';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Название комнаты')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Hidden::make('type')
                    ->dehydrated(),
                Forms\Components\FileUpload::make('presentations')
                    ->label('Презентации')
                    ->multiple()
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.oasis.opendocument.presentation',
                        'application/vnd.oasis.opendocument.text',
                        'application/vnd.oasis.opendocument.spreadsheet',
                        'image/jpeg',
                        'image/png',
                    ])
                    ->maxSize(204800)
                    ->directory('presentations')
                    ->columnSpanFull(),
                Forms\Components\Select::make('participants')
                    ->label('Ученики')
                    ->relationship(
                        'participants',
                        'name',
                        function (Builder $query, $livewire) {
                            // Show only teacher's students in dropdown
                            $query->where('role', 'student')
                                ->whereHas('teachers', function ($q) {
                                $q->where('teacher_student.teacher_id', auth()->id());
                            });

                            // But also include already selected students (even if not teacher's students)
                            if ($livewire instanceof \Filament\Resources\Pages\EditRecord && $livewire->record) {
                                $existingIds = $livewire->record->participants()->pluck('users.id')->toArray();
                                if (!empty($existingIds)) {
                                    $query->orWhereIn('users.id', $existingIds);
                                }
                            }

                            return $query;
                        }
                    )
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
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?array $state) {
                        $count = is_array($state) ? count($state) : 0;

                        if ($count === 0) {
                            // No students selected - clear type and price
                            $set('type', null);
                            $set('base_price', null);
                            $set('custom_price_enabled', false);
                            return;
                        }

                        // Determine type based on participant count
                        $type = $count > 1 ? 'group' : 'individual';
                        $set('type', $type);

                        // Only update base_price if custom price is NOT enabled
                        if (!$get('custom_price_enabled')) {
                            $lessonType = auth()->user()?->lessonTypes()
                                ->where('type', $type)
                                ->first();
                            $set('base_price', $lessonType?->price);
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\Section::make('Стоимость занятия')
                    ->description(function () {
                        $profileUrl = \App\Filament\App\Pages\EditProfile::getUrl();
                        return new \Illuminate\Support\HtmlString(
                            "Изменить базовую цену можно в <a href=\"{$profileUrl}\" class=\"text-primary-600 hover:underline\">настройках профиля</a>"
                        );
                    })
                    ->compact()
                    ->visible(fn(Forms\Get $get) => !empty($get('participants')))
                    ->schema([
                        Forms\Components\Hidden::make('custom_price_enabled')
                            ->default(false)
                            ->dehydrated(true)
                            ->afterStateHydrated(function (Forms\Components\Hidden $component, Forms\Get $get, ?Model $record) {
                                if (!$record)
                                    return;

                                $participants = $record->participants ?? collect();
                                $count = $participants->count();
                                $type = $count > 1 ? 'group' : 'individual';

                                $lessonType = auth()->user()?->lessonTypes()
                                    ->where('type', $type)
                                    ->first();
                                $defaultPrice = $lessonType?->price;

                                if ($record->base_price !== null && $record->base_price != $defaultPrice) {
                                    $component->state(true);
                                }
                            }),

                        Forms\Components\Grid::make(2)
                            ->visible(fn(Forms\Get $get) => !$get('custom_price_enabled'))
                            ->schema([
                                Forms\Components\Placeholder::make('base_price_display')
                                    ->label('Базовая цена за одно занятие')
                                    ->content(function (Forms\Get $get) {
                                        $participants = $get('participants');
                                        $count = is_array($participants) ? count($participants) : 0;

                                        if ($count === 0)
                                            return '—';

                                        $type = $count > 1 ? 'group' : 'individual';
                                        $lessonType = auth()->user()?->lessonTypes()
                                            ->where('type', $type)
                                            ->first();

                                        if (!$lessonType) {
                                            $profileUrl = \App\Filament\App\Pages\EditProfile::getUrl();
                                            $typeLabel = $type === 'group' ? 'групповых' : 'индивидуальных';
                                            return new \Illuminate\Support\HtmlString(
                                                "Цена для {$typeLabel} занятий не задана в <a href=\"{$profileUrl}\" class=\"text-primary-600 hover:underline\">профиле</a>"
                                            );
                                        }

                                        return number_format($lessonType->price, 0, '', ' ') . ' ₽';
                                    }),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('toggle_custom_price')
                                        ->label('Изменить')
                                        ->icon('heroicon-o-pencil')
                                        ->link()
                                        ->action(function (Forms\Set $set) {
                                            $set('custom_price_enabled', true);
                                        }),
                                ])->alignEnd(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->visible(fn(Forms\Get $get) => $get('custom_price_enabled'))
                            ->schema([
                                Forms\Components\TextInput::make('base_price')
                                    ->label('Новая цена')
                                    ->numeric()
                                    ->suffix('₽')
                                    ->helperText(function (Forms\Get $get) {
                                        $participants = $get('participants');
                                        $count = is_array($participants) ? count($participants) : 0;
                                        $type = $count > 1 ? 'group' : 'individual';

                                        $lessonType = auth()->user()?->lessonTypes()
                                            ->where('type', $type)
                                            ->first();
                                        $defaultPrice = $lessonType?->price;
                                        $typeLabel = $type === 'group' ? 'группового' : 'индивидуального';

                                        if ($defaultPrice) {
                                            return "Базовая цена {$typeLabel} занятия: " . number_format($defaultPrice, 0, '', ' ') . " ₽";
                                        }
                                        return null;
                                    }),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('reset_price')
                                        ->label('Сбросить')
                                        ->icon('heroicon-o-arrow-uturn-left')
                                        ->color('gray')
                                        ->link()
                                        ->action(function (Forms\Set $set, Forms\Get $get) {
                                            $set('custom_price_enabled', false);

                                            $participants = $get('participants');
                                            $count = is_array($participants) ? count($participants) : 0;
                                            $type = $count > 1 ? 'group' : 'individual';

                                            $lessonType = auth()->user()?->lessonTypes()
                                                ->where('type', $type)
                                                ->first();
                                            $set('base_price', $lessonType?->price);
                                        }),
                                ])->alignEnd(),
                            ]),
                    ]),

                Forms\Components\Section::make('Расписание')
                    ->description('Настройте расписание автоматического запуска встреч')
                    ->compact()
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
                                                'recurring' => 'Повторяющееся (регулярное)',
                                                'once' => 'Одноразовое (конкретная дата)',
                                            ])
                                            ->required()
                                            ->live()
                                            ->default('recurring')
                                            ->native(false),


                                        // One-time schedule
                                        Forms\Components\Grid::make(2)
                                            ->visible(fn(Forms\Get $get) => $get('type') === 'once')
                                            ->schema([
                                                Forms\Components\DatePicker::make('scheduled_date')
                                                    ->label('Дата занятия')
                                                    ->required(fn(Forms\Get $get) => $get('type') === 'once')
                                                    ->native(false)
                                                    ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                                        // Populate from scheduled_at when loading
                                                        if (!$state && $get('scheduled_at')) {
                                                            $datetime = \Carbon\Carbon::parse($get('scheduled_at'));
                                                            $set('scheduled_date', $datetime->format('Y-m-d'));
                                                        }
                                                    })
                                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                        // Combine date and time into scheduled_at
                                                        if ($state && $get('scheduled_time')) {
                                                            $date = \Carbon\Carbon::parse($state)->format('Y-m-d');
                                                            $time = $get('scheduled_time');
                                                            $set('scheduled_at', $date . ' ' . $time . ':00');
                                                            // Also set start_date for database requirement
                                                            $set('start_date', $date);
                                                        }
                                                    })
                                                    ->live(),

                                                Forms\Components\TimePicker::make('scheduled_time')
                                                    ->label('Время занятия')
                                                    ->required(fn(Forms\Get $get) => $get('type') === 'once')
                                                    ->native(true)
                                                    ->seconds(false)
                                                    ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                                        // Populate from scheduled_at when loading
                                                        if (!$state && $get('scheduled_at')) {
                                                            $datetime = \Carbon\Carbon::parse($get('scheduled_at'));
                                                            $set('scheduled_time', $datetime->format('H:i'));
                                                        }
                                                    })
                                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                        // Combine date and time into scheduled_at
                                                        if ($state && $get('scheduled_date')) {
                                                            $date = \Carbon\Carbon::parse($get('scheduled_date'))->format('Y-m-d');
                                                            $set('scheduled_at', $date . ' ' . $state . ':00');
                                                            // Also set start_date for database requirement
                                                            $set('start_date', $date);
                                                        }
                                                    })
                                                    ->live(),
                                            ]),

                                        // Hidden field to store combined datetime
                                        Forms\Components\Hidden::make('scheduled_at')
                                            ->dehydrated()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                // For one-time schedules, also set start_date to the scheduled date
                                                if ($get('type') === 'once' && $state) {
                                                    $date = \Carbon\Carbon::parse($state)->format('Y-m-d');
                                                    $set('start_date', $date);
                                                }
                                            })
                                            ->live(),

                                        // Recurring schedule Group
                                        Forms\Components\Fieldset::make('Настройки повторения')
                                            ->visible(fn(Forms\Get $get) => $get('type') === 'recurring')
                                            ->schema([
                                                Forms\Components\Hidden::make('recurrence_type')
                                                    ->default('weekly')
                                                    ->dehydrated(),

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
                                                    ->required(),

                                                Forms\Components\TimePicker::make('recurrence_time')
                                                    ->label('Время начала')
                                                    ->required()
                                                    ->native(true)
                                                    ->seconds(false),

                                                Forms\Components\Grid::make(2)
                                                    ->schema([
                                                        Forms\Components\DatePicker::make('start_date')
                                                            ->label('Дата начала расписания')
                                                            ->required()
                                                            ->default(now())
                                                            ->native(false)
                                                            ->hidden(fn(Forms\Get $get) => $get('type') === 'once')
                                                            ->dehydrated()
                                                            ->dehydrateStateUsing(function ($state, callable $get) {
                                                                // For one-time schedules, extract date from scheduled_at
                                                                if ($get('type') === 'once' && $get('scheduled_at')) {
                                                                    return \Carbon\Carbon::parse($get('scheduled_at'))->format('Y-m-d');
                                                                }
                                                                // For recurring or if no scheduled_at, use state or now
                                                                return $state ?? now()->format('Y-m-d');
                                                            }),

                                                        Forms\Components\DatePicker::make('end_date')
                                                            ->label('Дата окончания (необязательно)')
                                                            ->native(false)
                                                            ->helperText('Если не указано, расписание будет действовать бессрочно')
                                                            ->hidden(fn(Forms\Get $get) => $get('type') === 'once'),
                                                    ]),
                                            ])
                                            ->columns(1), // Fieldset content in 1 column

                                        Forms\Components\TextInput::make('duration_minutes')
                                            ->label('Длительность занятия (минуты)')
                                            ->numeric()
                                            ->default(90)
                                            ->required()
                                            ->minValue(1)
                                            ->maxValue(1440)
                                            ->step(5),
                                    ]),
                            ])
                            ->columns(1)
                            ->collapsible()
                            ->collapseAllAction(fn(Forms\Components\Actions\Action $action) => $action->hidden())
                            ->expandAllAction(fn(Forms\Components\Actions\Action $action) => $action->hidden())
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
                            ->collapsed()
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action
                                    ->requiresConfirmation()
                                    ->action(function (array $arguments, Forms\Components\Repeater $component): void {
                                        $items = $component->getState();
                                        $itemKey = $arguments['item'];
                                        $itemData = $items[$itemKey] ?? null;

                                        if ($itemData && isset($itemData['id'])) {
                                            $schedule = \App\Models\RoomSchedule::find($itemData['id']);
                                            if ($schedule) {
                                                // Get room and participants before deleting
                                                $room = $schedule->room;
                                                $participants = $room ? $room->participants : collect();
                                                $teacher = auth()->user();

                                                $schedule->delete();

                                                // Notify participants about schedule update (deletion)
                                                foreach ($participants as $student) {
                                                    $student->notify(new \App\Notifications\TeacherUpdatedSchedule($teacher));
                                                }
                                            }
                                        }

                                        unset($items[$itemKey]);
                                        $component->state($items);
                                    })
                            ),
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
                Tables\Columns\TextColumn::make('participants_custom')
                    ->label('Ученики')
                    ->getStateUsing(function (Room $record) {
                        // Get only participants assigned to THIS room
                        $participants = $record->participants;
                        $count = $participants->count();

                        if ($count === 0) {
                            return new \Illuminate\Support\HtmlString('<span class="text-gray-400 dark:text-gray-500 text-xs text-left block w-full">Нет учеников</span>');
                        }

                        $avatarsHtml = '<div class="flex -space-x-2 overflow-hidden">';
                        foreach ($participants->take(4) as $participant) {
                            $url = $participant->avatar_url;
                            $name = e($participant->name);
                            $avatarsHtml .= "<img class='inline-block h-6 w-6 rounded-full ring-2 ring-white dark:ring-gray-900 object-cover' src='{$url}' alt='{$name}' title='{$name}' />";
                        }
                        $avatarsHtml .= '</div>';

                        // Russian pluralization
                        $n = abs($count) % 100;
                        $n1 = $n % 10;
                        if ($n > 10 && $n < 20) {
                            $text = $count . ' учеников';
                        } elseif ($n1 > 1 && $n1 < 5) {
                            $text = $count . ' ученика';
                        } elseif ($n1 == 1) {
                            $text = $count . ' ученик';
                        } else {
                            $text = $count . ' учеников';
                        }

                        return new \Illuminate\Support\HtmlString("
                            <div class='flex items-center gap-3'>
                                {$avatarsHtml}
                                <span class='font-medium text-gray-700 dark:text-gray-300 text-sm'>{$text}</span>
                            </div>
                        ");
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ViewColumn::make('next_start')
                    ->label('Статус')
                    ->view('filament.tables.columns.next-lesson')
                    ->sortable()
                    ->state(fn(Room $record) => $record->next_start?->toIso8601String()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'individual' => 'Индивидуальное',
                        'group' => 'Групповое',
                    ]),
                Tables\Filters\TernaryFilter::make('is_running')
                    ->label('Запущено'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->defaultSort('next_start', 'asc')
            ->actions([
                Tables\Actions\Action::make('start')
                    ->label('Начать')
                    ->icon('heroicon-o-play')
                    ->color(fn(Room $record) => $record->next_start && $record->next_start->isPast() && !$record->next_start->addMinutes($record->duration ?? 45)->isPast() ? 'success' : 'gray')
                    ->button()
                    ->url(fn(Room $record) => route('rooms.start', $record))
                    ->openUrlInNewTab()
                    ->visible(function (Room $record) {
                        // Hide if this room is already running
                        if ($record->is_running) {
                            return false;
                        }

                        // Hide if archived
                        if ($record->trashed()) {
                            return false;
                        }

                        // Hide if user has another running meeting
                        $hasOtherRunningMeeting = Room::where('user_id', auth()->id())
                            ->where('is_running', true)
                            ->where('id', '!=', $record->id)
                            ->exists();

                        return !$hasOtherRunningMeeting;
                    }),

                Tables\Actions\Action::make('join')
                    ->label('Присоединиться')
                    ->icon('heroicon-o-user-plus')
                    ->button()
                    ->color('warning')
                    ->url(fn(Room $record) => route('rooms.join', $record))
                    ->openUrlInNewTab()
                    ->visible(fn(Room $record) => $record->is_running),

                Tables\Actions\Action::make('stop')
                    ->label('Остановить')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->action(fn(Room $record) => redirect()->route('rooms.stop', $record))
                    ->visible(fn(Room $record) => $record->is_running),

                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
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
            'view' => Pages\ViewRoom::route('/{record}'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
