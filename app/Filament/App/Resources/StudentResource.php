<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\StudentResource\Pages;
use App\Filament\App\Resources\StudentResource\RelationManagers;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;

class StudentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Ученики';

    protected static ?string $modelLabel = 'Ученик';

    protected static ?string $pluralModelLabel = 'Ученики';

    protected static ?string $slug = 'students'; // Url slug

    protected static ?int $navigationSort = 1;

    // Disable the default create button since we use custom "Add Student" action
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        // Only show students associated with the currently logged-in teacher (mentor/tutor)
        return parent::getEloquentQuery()
            ->whereHas('teachers', function (Builder $query) {
                $query->whereKey(auth()->id());
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('header')
                    ->hiddenLabel()
                    ->content(fn(User $record): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString('
                        <div class="flex items-center gap-4">
                            <img src="' . e($record->avatar_url) . '" class="rounded-full object-cover shadow-md" style="width: 80px; height: 80px;">
                            <div>
                                <p class="text-lg font-medium text-gray-900 dark:text-white">' . e($record->name) . '</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">' . e($record->display_role) . '</p>
                            </div>
                        </div>
                    '))
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('email')
                    ->hiddenLabel()
                    ->content(fn(User $record): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString('
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</p>
                            <p class="mt-1 text-gray-900 dark:text-white break-all">' . e($record->email) . '</p>
                        </div>
                    '))
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('phone')
                    ->hiddenLabel()
                    ->content(fn(User $record): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString('
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Телефон</p>
                            <p class="mt-1 text-gray-900 dark:text-white">' . e($record->phone ?? '-') . '</p>
                        </div>
                    '))
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('messengers')
                    ->hiddenLabel()
                    ->content(function (User $record): \Illuminate\Support\HtmlString {
                        $badges = [];

                        if ($record->telegram) {
                            $badges[] = '<a href="https://t.me/' . e($record->telegram) . '" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400 rounded-md ring-1 ring-inset ring-blue-600/20 dark:ring-blue-400/30">Telegram: ' . e($record->telegram) . '</a>';
                        }

                        if ($record->whatsup) {
                            $whatsappNumber = preg_replace('/[^0-9]/', '', $record->whatsup);
                            $badges[] = '<a href="https://wa.me/' . $whatsappNumber . '" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400 rounded-md ring-1 ring-inset ring-green-600/20 dark:ring-green-400/30">WhatsApp: ' . e($record->whatsup) . '</a>';
                        }

                        if (empty($badges)) {
                            return new \Illuminate\Support\HtmlString('');
                        }

                        return new \Illuminate\Support\HtmlString('
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Мессенджеры</p>
                                <div class="mt-1 flex flex-wrap gap-2">' . implode('', $badges) . '</div>
                            </div>
                        ');
                    })
                    ->visible(fn(User $record): bool => $record->telegram || $record->whatsup)
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('assigned_rooms_list')
                    ->hiddenLabel()
                    ->content(function (User $record) {
                        $rooms = $record->assignedRooms()
                            ->where('rooms.user_id', auth()->id())
                            ->pluck('name')
                            ->toArray();

                        if (empty($rooms)) {
                            return new \Illuminate\Support\HtmlString('
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Назначенные занятия</p>
                                    <p class="mt-1 text-gray-500 dark:text-gray-400">Нет назначенных занятий</p>
                                </div>
                            ');
                        }

                        return new \Illuminate\Support\HtmlString('
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Назначенные занятия</p>
                                <div class="mt-1 flex flex-wrap gap-2">' .
                            implode('', array_map(
                                fn($name) =>
                                '<span class="px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white rounded-md">' . e($name) . '</span>',
                                $rooms
                            )) .
                            '</div>
                            </div>
                        ');
                    })
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('created_at')
                    ->hiddenLabel()
                    ->content(fn(User $record): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString('
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Дата регистрации</p>
                            <p class="mt-1 text-gray-900 dark:text-white">' . $record->created_at->format('d.m.Y H:i') . '</p>
                        </div>
                    '))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with([
                'assignedRooms' => fn($query) => $query->where('rooms.user_id', auth()->id()),
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Имя')
                    ->sortable()
                    ->searchable(['name', 'email', 'phone'])
                    ->formatStateUsing(function (User $record) {
                        $avatarUrl = $record->avatar_url ?? url('/images/default-avatar.png');
                        return new \Illuminate\Support\HtmlString(
                            '<div class="flex items-center gap-3">
                                <img src="' . e($avatarUrl) . '" class="rounded-full object-cover" style="width: 40px; height: 40px;">
                                <span>' . e($record->name) . '</span>
                            </div>'
                        );
                    }),
                Tables\Columns\TextColumn::make('assigned_rooms')
                    ->label('Назначенные занятия')
                    ->state(fn(User $record) => static::assignedRoomsBadges($record))
                    // Ни одного занятия — вместо пустой ячейки ссылка, открывающая окно назначения
                    ->placeholder(fn(User $record) => new \Illuminate\Support\HtmlString(
                        '<button type="button" ' . static::assignRoomTrigger($record) . ' class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">'
                        . '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>'
                        . '<span>Назначить занятие</span></button>'
                    ))
                    // Ячейка, как и вся строка, ведёт в профиль ученика. Действие регистрируем через колонку,
                    // но открывают его только тег занятия, кружок «+N» и ссылка (см. assignRoomTrigger)
                    ->url(fn(User $record): string => Pages\ViewStudent::getUrl([$record]))
                    ->action(static::assignRoomAction()),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Оплата')
                    ->state(function (User $record) {
                        if (static::isFreeStudent($record)) {
                            return 'Бесплатно';
                        }

                        $records = PaymentRecord::where('teacher_id', auth()->id())
                            ->where('student_id', $record->id)
                            ->get();

                        // Записей об оплате ещё не было (не прошло ни одного занятия / месяца)
                        if ($records->isEmpty()) {
                            return 'Занятий не было';
                        }

                        $unpaid = $records->where('status', PaymentRecord::STATUS_UNPAID);

                        if ($unpaid->isEmpty()) {
                            // Ни одной реальной оплаты — только отменённые записи
                            return $records->contains('status', PaymentRecord::STATUS_PAID)
                                ? 'Оплачено'
                                : 'Оплата не требуется';
                        }

                        $overdue = $unpaid->filter(fn(PaymentRecord $r) => $r->isOverdue());

                        if ($overdue->isNotEmpty()) {
                            // Кабинет ученика заблокирован за неоплату; долг — в подсказке
                            if ($record->payment_blocked_at) {
                                return 'Заблокирован';
                            }

                            if ($overdue->firstWhere('type', PaymentRecord::TYPE_MONTHLY)) {
                                return 'Просрочено';
                            }
                            return 'Просрочено: ' . trans_choice('{1} :count занятие|[2,4] :count занятия|[5,*] :count занятий', $overdue->count());
                        }

                        return 'Ожидает оплаты';
                    })
                    ->badge()
                    ->icon(fn(string $state): ?string => $state === 'Заблокирован' ? 'heroicon-m-lock-closed' : null)
                    ->tooltip(function (string $state, User $record): ?string {
                        if ($state === 'Заблокирован') {
                            return static::blockedPaymentTooltip($record);
                        }

                        if (str_starts_with($state, 'Просрочено')) {
                            $due = PaymentRecord::overdue()
                                ->where('teacher_id', auth()->id())
                                ->where('student_id', $record->id)
                                ->orderBy('due_date')
                                ->first()?->due_date;

                            return $due ? 'Срок оплаты был ' . $due->format('d.m.Y') : null;
                        }

                        if ($state === 'Ожидает оплаты') {
                            $due = PaymentRecord::unpaid()
                                ->where('teacher_id', auth()->id())
                                ->where('student_id', $record->id)
                                ->orderBy('due_date')
                                ->first()?->due_date;

                            return $due ? 'Оплата до ' . $due->format('d.m.Y') : null;
                        }

                        return null;
                    })
                    ->color(fn(string $state): string => match (true) {
                        $state === 'Занятий не было', $state === 'Оплата не требуется' => 'gray',
                        $state === 'Бесплатно' => 'info',
                        $state === 'Оплачено' => 'success',
                        str_starts_with($state, 'Просрочено'), str_starts_with($state, 'Заблокирован') => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('assignedRooms')
                    ->label('Занятие')
                    ->relationship('assignedRooms', 'name', modifyQueryUsing: fn(Builder $query) => $query->where('rooms.user_id', auth()->id()))
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->defaultSort('name', 'asc')
            ->headerActions([
                static::addStudentAction(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('Пока нет учеников')
            ->emptyStateDescription('Ученика, который уже зарегистрирован на Serdal, можно найти по имени или email. Если его ещё нет на платформе — отправьте ему ссылку-приглашение.')
            ->emptyStateActions([
                static::addStudentAction(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('mark_payment')
                        ->label('Отметить оплату')
                        ->icon('heroicon-o-check-circle')
                        ->modalHeading(fn(User $record) => "Оплата — {$record->name}")
                        ->modalSubmitActionLabel('Сохранить')
                        ->modalSubmitAction(fn($action, User $record) => static::hasUnpaidRecords($record) ? $action : false)
                        ->modalCancelActionLabel(fn(User $record) => static::hasUnpaidRecords($record) ? 'Отмена' : 'Закрыть')
                        ->form(fn(User $record) => static::getPaymentFormSchema($record))
                        ->action(fn(User $record, array $data) => static::applyPaymentMarks($record, $data)),
                    Tables\Actions\Action::make('payment_settings')
                        ->label('Настройки оплаты')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->modalHeading(fn(User $record) => "Настройки оплаты — {$record->name}")
                        ->modalWidth('md')
                        ->modalSubmitActionLabel('Сохранить')
                        ->form(fn(User $record) => static::getPaymentSettingsFormSchema($record))
                        ->action(fn(User $record, array $data) => static::applyPaymentSettings($record, $data)),
                ])
                    ->label('Оплата')
                    ->icon('heroicon-o-banknotes')
                    ->color('gray')
                    ->button(),
                static::deleteFromListAction()
                    ->iconButton()
                    // В таблице иконка неброская серая, но подтверждение в окне остаётся красным
                    ->color('gray')
                    ->modalSubmitAction(fn(\Filament\Actions\StaticAction $action) => $action->color('danger'))
                    // Небольшой отступ от кнопки «Оплата»
                    ->extraAttributes(['style' => 'margin-inline-start: 0.5rem;']),
            ])
            ->recordUrl(fn(User $record): string => Pages\ViewStudent::getUrl([$record]))
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
    }

    /**
     * Занятия текущего учителя, в которые добавлен ученик.
     * Отношение подгружается одним запросом на всю таблицу (см. modifyQueryUsing),
     * фильтр по учителю дублируем в памяти на случай ленивой загрузки.
     */
    public static function teacherRoomsOf(User $record): \Illuminate\Support\Collection
    {
        return $record->assignedRooms
            ->where('user_id', auth()->id())
            ->sortBy('name')
            ->values();
    }

    /**
     * Первое назначенное занятие и счётчик остальных для колонки таблицы. Null — ученик никуда не добавлен.
     */
    public static function assignedRoomsBadges(User $record): ?\Illuminate\Support\HtmlString
    {
        $rooms = static::teacherRoomsOf($record);

        if ($rooms->isEmpty()) {
            return null;
        }

        $colorClasses = 'text-gray-700 ring-1 ring-inset ring-gray-600/20 dark:text-gray-300 dark:ring-gray-400/30';

        // В таблице показываем только первое занятие, остальные сворачиваем в кружок «+N»
        $first = $rooms->first();
        $rest = $rooms->slice(1);

        $isGroup = $first->type === 'group';
        $icon = $isGroup
            ? '<svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>'
            : '<svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>';

        $trigger = static::assignRoomTrigger($record);

        $html = "<button type=\"button\" {$trigger} class=\"student-rooms-tag inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium transition hover:bg-gray-50 dark:hover:bg-white/5 {$colorClasses}\">{$icon} <span>" . e($first->name) . "</span></button>";

        if ($rest->isNotEmpty()) {
            $hiddenNames = $rest->pluck('name')->map(fn(string $name) => e($name))->join(', ');

            $html .= "<button type=\"button\" {$trigger} class=\"inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-medium transition hover:bg-gray-50 {$colorClasses} dark:bg-white/5 dark:hover:bg-white/10\" title=\"{$hiddenNames}\">+" . $rest->count() . '</button>';
        }

        return new \Illuminate\Support\HtmlString('<div class="student-rooms flex items-center gap-1.5">' . $html . '</div>');
    }

    /**
     * Атрибуты для элемента, открывающего окно назначения занятий этому ученику.
     * .stop — чтобы клик не ушёл в строку таблицы, .prevent — чтобы не сработала ссылка на профиль.
     */
    protected static function assignRoomTrigger(User $record): string
    {
        return 'wire:click.stop.prevent="mountTableAction(\'assign_room\', \'' . e($record->getKey()) . '\')"';
    }

    /**
     * Все занятия текущего учителя с количеством участников — варианты для окна назначения.
     */
    public static function teacherRoomsForAssignment(): \Illuminate\Support\Collection
    {
        return Room::query()
            ->where('user_id', auth()->id())
            ->withCount('participants')
            ->orderBy('name')
            ->get();
    }

    /**
     * Действие «Назначить занятие»: окно со списком занятий учителя,
     * где можно отметить несколько занятий или снять уже назначенные.
     */
    public static function assignRoomAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('assign_room')
            ->label('Назначить занятие')
            ->icon('heroicon-o-plus')
            ->modalHeading(fn(User $record) => "Занятия — {$record->name}")
            ->modalDescription('Отметьте занятия, в которых должен участвовать ученик. Снимите отметку, чтобы убрать его из занятия.')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Сохранить')
            ->modalSubmitAction(fn($action) => static::teacherRoomsForAssignment()->isNotEmpty() ? $action : false)
            ->modalCancelActionLabel(fn() => static::teacherRoomsForAssignment()->isNotEmpty() ? 'Отмена' : 'Закрыть')
            ->form(fn(User $record) => static::getAssignRoomFormSchema($record))
            ->action(fn(User $record, array $data) => static::syncStudentRooms($record, $data));
    }

    public static function getAssignRoomFormSchema(User $record): array
    {
        $rooms = static::teacherRoomsForAssignment();

        if ($rooms->isEmpty()) {
            $createUrl = RoomResource::getUrl('create');

            return [
                Forms\Components\Placeholder::make('no_rooms')
                    ->hiddenLabel()
                    ->content(new \Illuminate\Support\HtmlString(
                        '<p class="text-sm text-gray-600 dark:text-gray-400">У вас пока нет занятий. Создайте занятие, а затем назначьте его ученику.</p>'
                        . '<a href="' . e($createUrl) . '" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">'
                        . 'Создать занятие →</a>'
                    )),
            ];
        }

        $assignedIds = static::teacherRoomsOf($record)->pluck('id')->all();

        return [
            Forms\Components\CheckboxList::make('room_ids')
                ->hiddenLabel()
                ->options($rooms->mapWithKeys(fn(Room $room) => [$room->id => e($room->name)]))
                ->descriptions($rooms->mapWithKeys(fn(Room $room) => [
                    $room->id => ($room->type === 'group' ? 'Группа' : 'Индивидуально')
                        . ' · ' . plural_ru($room->participants_count, 'ученик', 'ученика', 'учеников'),
                ]))
                ->default($assignedIds)
                ->searchable($rooms->count() > 8)
                ->bulkToggleable($rooms->count() > 1)
                ->columns(1),
        ];
    }

    /**
     * Приводит участие ученика в занятиях учителя к отмеченному набору:
     * добавляет в новые занятия, убирает из снятых.
     */
    public static function syncStudentRooms(User $record, array $data): void
    {
        $teacher = auth()->user();

        $teacherRooms = Room::where('user_id', $teacher->id)->get()->keyBy('id');

        // Чужие id отбрасываем — назначать можно только свои занятия
        $wantedIds = collect($data['room_ids'] ?? [])
            ->map(fn($id) => (int) $id)
            ->filter(fn(int $id) => $teacherRooms->has($id))
            ->unique()
            ->values();

        $currentIds = $record->assignedRooms()
            ->where('rooms.user_id', $teacher->id)
            ->pluck('rooms.id');

        $addedIds = $wantedIds->diff($currentIds)->values();
        $removedIds = $currentIds->diff($wantedIds)->values();

        foreach ($addedIds as $roomId) {
            $room = $teacherRooms[$roomId];
            $room->participants()->attach($record->id);
            static::refreshRoomType($room);

            // Как и при добавлении через форму занятия: выдаём ученику задания этого занятия
            $room->attachParticipantsToHomeworks([$record->id]);

            $record->notify(new \App\Notifications\TeacherAssignedLesson($room, $teacher));
        }

        foreach ($removedIds as $roomId) {
            $room = $teacherRooms[$roomId];
            $room->participants()->detach($record->id);
            static::refreshRoomType($room);
        }

        // Таблица перерисуется в этом же запросе — сбрасываем загруженное отношение
        $record->unsetRelation('assignedRooms');

        if ($addedIds->isEmpty() && $removedIds->isEmpty()) {
            Notification::make()
                ->title('Изменений нет')
                ->info()
                ->send();

            return;
        }

        $parts = [];

        if ($addedIds->isNotEmpty()) {
            $parts[] = 'Назначено: ' . $addedIds->map(fn(int $id) => '«' . $teacherRooms[$id]->name . '»')->join(', ');
        }

        if ($removedIds->isNotEmpty()) {
            $parts[] = 'Снято: ' . $removedIds->map(fn(int $id) => '«' . $teacherRooms[$id]->name . '»')->join(', ');
        }

        Notification::make()
            ->title('Занятия ученика обновлены')
            ->body(implode('. ', $parts) . '.')
            ->success()
            ->send();
    }

    /**
     * attach()/detach() не сохраняют комнату, поэтому тип пересчитываем сами (как в EditRoom).
     */
    protected static function refreshRoomType(Room $room): void
    {
        $participantCount = $room->participants()->count();

        $room->updateQuietly([
            'type' => match (true) {
                $participantCount === 0 => 'pending',
                $participantCount === 1 => 'individual',
                default => 'group',
            },
        ]);
    }

    /**
     * Действие «Удалить из списка» с подтверждением. Используется и в таблице, и в профиле ученика.
     */
    public static function deleteFromListAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('delete_from_list')
            ->label('Удалить из списка')
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->requiresConfirmation()
            ->action(function (User $record) {
                static::removeStudentFromList($record);

                Notification::make()
                    ->title('Ученик удален из списка')
                    ->success()
                    ->send();
            });
    }

    /**
     * Убирает ученика из списка учителя: снимает связь, удаляет из всех занятий
     * и уведомляет ученика (с предложением оставить отзыв, если были занятия).
     */
    public static function removeStudentFromList(User $record): void
    {
        $teacher = auth()->user();
        $teacher->students()->detach($record);

        // Убираем ученика из всех занятий учителя
        Room::where('user_id', $teacher->id)->get()->each(function (Room $room) use ($record) {
            $room->participants()->detach($record->id);
        });

        // Может ли ученик оставить отзыв: было хотя бы одно занятие и отзыва ещё нет
        $studentId = (string) $record->id;
        $hasCompletedLesson = \App\Models\MeetingSession::whereHas('room', function ($q) use ($teacher) {
            $q->where('user_id', $teacher->id);
        })
            ->where(function ($q) use ($studentId) {
                $q->whereJsonContains('analytics_data->participants', ['user_id' => $studentId])
                    ->orWhereJsonContains('analytics_data->participants', ['user_id' => (int) $studentId]);
            })
            ->exists();

        $hasExistingReview = \App\Models\Review::where('user_id', $record->id)
            ->where('teacher_id', $teacher->id)
            ->exists();

        $record->notify(new \App\Notifications\TeacherRemoved($teacher, $hasCompletedLesson && !$hasExistingReview));
    }

    /**
     * Занимается ли ученик у текущего учителя бесплатно.
     */
    public static function isFreeStudent(User $record): bool
    {
        return \Illuminate\Support\Facades\DB::table('teacher_student')
            ->where('teacher_id', auth()->id())
            ->where('student_id', $record->id)
            ->value('is_free') == true;
    }

    /**
     * Персональный тип оплаты ученика (null — как в базовых ценах).
     */
    public static function paymentTypeOverride(User $record): ?string
    {
        return \Illuminate\Support\Facades\DB::table('teacher_student')
            ->where('teacher_id', auth()->id())
            ->where('student_id', $record->id)
            ->value('payment_type_override');
    }

    /**
     * Единый текст подсказки для статуса «Заблокирован» (список учеников и дашборд).
     */
    public static function blockedPaymentTooltip(User $student): string
    {
        $overdueCount = PaymentRecord::overdue()
            ->where('teacher_id', auth()->id())
            ->where('student_id', $student->id)
            ->count();

        return 'Просрочено: ' . trans_choice('{1} :count занятие|[2,4] :count занятия|[5,*] :count занятий', $overdueCount)
            . '. Кабинет ученика заблокирован, разблокируется после отметки оплаты.';
    }

    /**
     * Есть ли у ученика записи, которые можно отметить.
     */
    public static function hasUnpaidRecords(User $record): bool
    {
        return !static::isFreeStudent($record) && PaymentRecord::unpaid()
            ->where('teacher_id', auth()->id())
            ->where('student_id', $record->id)
            ->exists();
    }

    /**
     * Форма отметки оплаты: только чекбоксы по неоплаченным записям.
     * Настройки (бесплатно, тип оплаты) — в отдельной модалке «Настройки оплаты».
     */
    public static function getPaymentFormSchema(User $record): array
    {
        if (static::isFreeStudent($record)) {
            return [
                Forms\Components\Placeholder::make('free_student')
                    ->hiddenLabel()
                    ->content('Ученик занимается бесплатно — оплата не отслеживается. Изменить это можно в настройках оплаты (кнопка с шестерёнкой).'),
            ];
        }

        $unpaid = PaymentRecord::unpaid()
            ->where('teacher_id', auth()->id())
            ->where('student_id', $record->id)
            ->orderBy('due_date')
            ->get();

        if ($unpaid->isEmpty()) {
            $history = PaymentRecord::where('teacher_id', auth()->id())
                ->where('student_id', $record->id)
                ->get();

            $intro = match (true) {
                // Записей ещё не было вообще
                $history->isEmpty() => 'Записей об оплате пока нет.',
                // Были реальные оплаты, долгов нет
                $history->contains('status', PaymentRecord::STATUS_PAID) => 'Все занятия оплачены — отмечать пока нечего.',
                // Только отменённые записи (пробные/бесплатные занятия)
                default => 'Сейчас отмечать нечего: по прошедшим занятиям вы указали, что оплата не требуется.',
            };

            return [
                Forms\Components\Placeholder::make('no_debts')
                    ->hiddenLabel()
                    ->content($intro . ' Новая запись появится сама: после следующего занятия — при поурочной оплате, или в начале месяца — при помесячной. Тогда здесь можно будет отметить, оплатил ученик или нет.'),
            ];
        }

        return [
            Forms\Components\CheckboxList::make('record_ids')
                ->label('Выберите, что оплатил ученик')
                ->allowHtml()
                ->options($unpaid->mapWithKeys(function (PaymentRecord $r) {
                    $label = e($r->label);
                    // Просроченные выделяем красной подписью вместо серого описания
                    if ($r->isOverdue()) {
                        $label .= '<span class="block font-normal text-danger-600 dark:text-danger-400">Срок оплаты прошёл</span>';
                    }
                    return [$r->id => $label];
                }))
                ->descriptions($unpaid->reject(fn(PaymentRecord $r) => $r->isOverdue())->mapWithKeys(fn(PaymentRecord $r) => [
                    $r->id => 'Оплата до ' . $r->due_date->format('d.m.Y'),
                ]))
                ->columns(1)
                ->bulkToggleable()
                ->required()
                ->validationMessages(['required' => 'Отметьте хотя бы одно занятие или месяц.'])
                ->extraAttributes(['class' => 'payment-record-cards']),
            Forms\Components\Radio::make('mark_action')
                ->label('Что сделать с выбранным')
                ->options([
                    'paid' => 'Ученик оплатил',
                    'cancelled' => 'Не требовать оплату (например, бесплатное или пробное занятие)',
                    'extend' => 'Продлить срок оплаты',
                ])
                ->default('paid')
                ->required()
                ->live(),
            Forms\Components\TextInput::make('extend_days')
                ->label('На сколько дней продлить')
                ->numeric()
                ->minValue(1)
                ->maxValue(60)
                ->default(3)
                ->suffix('дн.')
                ->required(fn(Forms\Get $get) => $get('mark_action') === 'extend')
                ->visible(fn(Forms\Get $get) => $get('mark_action') === 'extend'),
        ];
    }

    public static function applyPaymentMarks(User $record, array $data): void
    {
        if (empty($data['record_ids'])) {
            return;
        }

        $teacher = auth()->user();

        $records = PaymentRecord::unpaid()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $record->id)
            ->whereIn('id', $data['record_ids'])
            ->get();

        // Продление срока: сдвигаем due_date, сбрасываем отметку о напоминании
        // и снимаем блокировку, если просроченных долгов не осталось
        if (($data['mark_action'] ?? 'paid') === 'extend') {
            $days = max(1, (int) ($data['extend_days'] ?? 3));

            foreach ($records as $paymentRecord) {
                $paymentRecord->extendDue($days);
            }

            $latestDue = $records->map(fn(PaymentRecord $r) => $r->fresh()->due_date)->max();

            Notification::make()
                ->title('Срок оплаты продлён')
                ->body('Новый срок: до ' . $latestDue->format('d.m.Y') . '. Напоминание придёт ученику, если он снова не оплатит вовремя.')
                ->success()
                ->send();

            return;
        }

        $status = ($data['mark_action'] ?? 'paid') === 'cancelled'
            ? PaymentRecord::STATUS_CANCELLED
            : PaymentRecord::STATUS_PAID;

        foreach ($records as $paymentRecord) {
            $paymentRecord->markAs($status, $teacher->id);
        }

        Notification::make()
            ->title($status === PaymentRecord::STATUS_PAID ? 'Отметили: оплачено' : 'Готово: оплата не требуется')
            ->success()
            ->send();
    }

    /**
     * Форма настроек оплаты ученика: бесплатные занятия + персональный тип оплаты.
     */
    public static function getPaymentSettingsFormSchema(User $record): array
    {
        return [
            Forms\Components\Toggle::make('is_free')
                ->label('Ученик занимается бесплатно')
                ->helperText('Оплата не отслеживается: записи не создаются, напоминания не приходят. Текущие неоплаченные записи будут отменены.')
                ->default(static::isFreeStudent($record))
                ->live(),
            Forms\Components\Select::make('payment_type_override')
                ->label('Как этот ученик оплачивает занятия')
                ->options([
                    PaymentRecord::TYPE_PER_LESSON => 'Поурочно',
                    PaymentRecord::TYPE_MONTHLY => 'Помесячно',
                ])
                ->placeholder('Как в базовых ценах (по умолчанию)')
                ->default(static::paymentTypeOverride($record))
                ->visible(fn(Forms\Get $get) => !$get('is_free')),
        ];
    }

    public static function applyPaymentSettings(User $record, array $data): void
    {
        $teacher = auth()->user();
        $wasFree = static::isFreeStudent($record);
        $isFree = (bool) ($data['is_free'] ?? false);

        if ($isFree !== $wasFree) {
            $teacher->students()->updateExistingPivot($record->id, ['is_free' => $isFree]);

            if ($isFree) {
                // Отменяем все неоплаченные записи, чтобы не осталось долгов и напоминаний
                PaymentRecord::unpaid()
                    ->where('teacher_id', $teacher->id)
                    ->where('student_id', $record->id)
                    ->get()
                    ->each(fn(PaymentRecord $r) => $r->markAs(PaymentRecord::STATUS_CANCELLED, $teacher->id));

                Notification::make()
                    ->title('Ученик занимается бесплатно')
                    ->body('Записи об оплате и напоминания для этого ученика отключены.')
                    ->success()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Оплата снова отслеживается')
                ->body('Новые записи появятся после следующего занятия или в начале месяца.')
                ->success()
                ->send();
        }

        // Персональный тип оплаты (для бесплатного ученика неактуален)
        if (!$isFree) {
            $newOverride = $data['payment_type_override'] ?: null;
            $oldOverride = static::paymentTypeOverride($record);

            if ($newOverride !== $oldOverride) {
                $teacher->students()->updateExistingPivot($record->id, ['payment_type_override' => $newOverride]);

                Notification::make()
                    ->title('Тип оплаты ученика обновлён')
                    ->body(match ($newOverride) {
                        PaymentRecord::TYPE_PER_LESSON => 'Теперь этот ученик оплачивает поурочно.',
                        PaymentRecord::TYPE_MONTHLY => 'Теперь этот ученик оплачивает помесячно.',
                        default => 'Теперь действует настройка из «Базовых цен».',
                    })
                    ->success()
                    ->send();
            }
        }
    }

    /**
     * Ученики, которых ещё нет в списке текущего учителя.
     */
    protected static function availableStudentsQuery(): Builder
    {
        return User::where('role', 'student')
            ->whereDoesntHave('teachers', function (Builder $query) {
                $query->where('users.id', auth()->id());
            });
    }

    /**
     * Подпись в выпадающем списке: имя и email, чтобы тёзок можно было различить.
     */
    protected static function studentOptionLabel(User $student): string
    {
        return filled($student->email)
            ? "{$student->name} ({$student->email})"
            : $student->name;
    }

    /**
     * Единая точка входа: и в шапке таблицы, и в пустом состоянии.
     */
    protected static function addStudentAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('add_student')
            ->label('Добавить ученика')
            ->icon('heroicon-o-plus')
            ->modalHeading('Добавить ученика')
            ->modalWidth('lg')
            ->modalSubmitActionLabel(fn (array $data): string => ($data['mode'] ?? 'existing') === 'invite'
                ? 'Отправить приглашение'
                : 'Добавить')
            ->form([
                // Учитель не обязан знать, зарегистрирован ли ученик, поэтому
                // спрашиваем об этом прямо, а не прячем различие в двух кнопках.
                Forms\Components\Radio::make('mode')
                    ->label('Как добавить ученика?')
                    ->options([
                        'existing' => 'Ученик уже зарегистрирован на Serdal',
                        'invite' => 'Ученика ещё нет на Serdal',
                    ])
                    ->descriptions([
                        'existing' => 'Найдём его по имени или email и добавим в ваш список',
                        'invite' => 'Отправим ссылку-приглашение — после регистрации ученик появится в списке автоматически',
                    ])
                    ->default('existing')
                    ->live()
                    ->required(),

                Forms\Components\Select::make('student_id')
                    ->label('Выберите ученика')
                    ->options(fn () => static::availableStudentsQuery()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (User $student) => [
                            $student->id => static::studentOptionLabel($student),
                        ]))
                    ->searchable()
                    ->searchPrompt('Введите имя или email...')
                    ->noSearchResultsMessage('Никого не нашли. Если ученик ещё не зарегистрирован, выберите вариант «Ученика ещё нет на Serdal».')
                    ->getSearchResultsUsing(fn (string $search) => static::availableStudentsQuery()
                        ->where(function (Builder $query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orderBy('name')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (User $student) => [
                            $student->id => static::studentOptionLabel($student),
                        ]))
                    ->getOptionLabelUsing(fn ($value) => ($student = User::find($value))
                        ? static::studentOptionLabel($student)
                        : null)
                    ->visible(fn (Forms\Get $get) => $get('mode') === 'existing')
                    ->required(fn (Forms\Get $get) => $get('mode') === 'existing'),

                Forms\Components\Section::make('Ссылка для приглашения')
                    ->description('Отправьте эту ссылку ученику, чтобы он мог зарегистрироваться и автоматически добавиться в ваш список.')
                    ->visible(fn (Forms\Get $get) => $get('mode') === 'invite')
                    ->schema([
                        Forms\Components\TextInput::make('invitation_link')
                            ->label('Ссылка')
                            ->default(fn () => \Illuminate\Support\Facades\URL::signedRoute('student.invitation', ['teacher' => auth()->id()]))
                            ->readOnly()
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('copy')
                                    ->icon('heroicon-m-clipboard')
                                    ->label('Копировать')
                                    ->action(function ($livewire, $state) {
                                        $livewire->js("window.navigator.clipboard.writeText('{$state}'); \$tooltip('Скопировано', { timeout: 1500 });");
                                        Notification::make()->title('Ссылка скопирована')->success()->send();
                                    })
                            ),
                    ]),

                Forms\Components\Section::make('Отправить по Email')
                    ->description('Или укажите Email, и мы отправим приглашение.')
                    ->visible(fn (Forms\Get $get) => $get('mode') === 'invite')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Email ученика')
                            ->email()
                            ->placeholder('student@example.com'),
                    ]),
            ])
            ->action(function (array $data) {
                if (($data['mode'] ?? 'existing') === 'invite') {
                    static::sendStudentInvitation($data);

                    return;
                }

                static::attachExistingStudent($data);
            });
    }

    /**
     * Привязывает уже зарегистрированного ученика к текущему учителю.
     */
    protected static function attachExistingStudent(array $data): void
    {
        $student = User::find($data['student_id'] ?? null);

        if (! $student) {
            return;
        }

        $changes = auth()->user()->students()->syncWithoutDetaching([$student->id]);

        if (count($changes['attached']) === 0) {
            Notification::make()
                ->title('Ученик уже в вашем списке')
                ->warning()
                ->send();

            return;
        }

        $student->notify(new \App\Notifications\NewTeacher(auth()->user()));

        Notification::make()
            ->title('Ученик добавлен')
            ->success()
            ->send();
    }

    /**
     * Отправляет приглашение на почту. Ссылку учитель может и просто скопировать,
     * поэтому пустой email — не ошибка, но об этом стоит сказать явно.
     */
    protected static function sendStudentInvitation(array $data): void
    {
        if (blank($data['email'] ?? null)) {
            Notification::make()
                ->title('Email не указан')
                ->body('Скопируйте ссылку и отправьте её ученику сами или укажите email.')
                ->warning()
                ->send();

            return;
        }

        \Illuminate\Support\Facades\Mail::to($data['email'])
            ->send(new \App\Mail\StudentInvitation($data['invitation_link'], auth()->user()->name));

        Notification::make()
            ->title('Приглашение отправлено')
            ->body("Письмо отправлено на {$data['email']}")
            ->success()
            ->send();
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
            'index' => Pages\ListStudents::route('/'),
            'view' => Pages\ViewStudent::route('/{record}'),
        ];
    }
}
