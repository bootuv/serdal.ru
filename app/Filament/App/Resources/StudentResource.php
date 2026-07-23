<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\StudentResource\Pages;
use App\Filament\App\Resources\StudentResource\RelationManagers;
use App\Models\PaymentRecord;
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
                Tables\Columns\TextColumn::make('assignedRooms.name')
                    ->label('Назначенные занятия')
                    ->formatStateUsing(function (string $state, User $record) {
                        // We need to fetch the actual rooms to inspect their Type
                        // Since getStateUsing isn't creating the state objects fully here for standard badges to work with icons per-item easily in a list,
                        // we will override the state processing or just render HTML ourselves.
                        // Actually, standard TextColumn with separator can be tricky with per-item icons.
                        // Better to use a custom view or formatStateUsing returning HTML.
            
                        $rooms = $record->assignedRooms()
                            ->where('rooms.user_id', auth()->id())
                            ->get(['name', 'type']);

                        if ($rooms->isEmpty()) {
                            return null;
                        }

                        $badges = $rooms->map(function ($room) {
                            $isGroup = $room->type === 'group';
                            $icon = $isGroup
                                ? '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>'
                                : '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>';

                            $colorClasses = 'text-gray-700 ring-1 ring-inset ring-gray-600/20 dark:text-gray-300 dark:ring-gray-400/30';

                            return "<span class=\"inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium {$colorClasses}\">{$icon} <span>" . e($room->name) . "</span></span>";
                        })->join(' ');

                        return new \Illuminate\Support\HtmlString('<div class="flex flex-wrap gap-1.5">' . $badges . '</div>');
                    }),
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

                            $monthly = $overdue->firstWhere('type', PaymentRecord::TYPE_MONTHLY);
                            if ($monthly) {
                                return 'Долг: ' . mb_strtolower($monthly->label);
                            }
                            return 'Долг: ' . trans_choice('{1} :count занятие|[2,4] :count занятия|[5,*] :count занятий', $overdue->count());
                        }

                        return 'Ожидает оплаты';
                    })
                    ->badge()
                    ->icon(fn(string $state): ?string => $state === 'Заблокирован' ? 'heroicon-m-lock-closed' : null)
                    ->tooltip(function (string $state, User $record): ?string {
                        if ($state !== 'Заблокирован') {
                            return null;
                        }

                        $overdueCount = PaymentRecord::overdue()
                            ->where('teacher_id', auth()->id())
                            ->where('student_id', $record->id)
                            ->count();

                        return 'Долг: ' . trans_choice('{1} :count занятие|[2,4] :count занятия|[5,*] :count занятий', $overdueCount)
                            . '. Кабинет ученика заблокирован, разблокируется после отметки оплаты.';
                    })
                    ->color(fn(string $state): string => match (true) {
                        $state === 'Занятий не было', $state === 'Оплата не требуется' => 'gray',
                        $state === 'Бесплатно' => 'info',
                        $state === 'Оплачено' => 'success',
                        str_starts_with($state, 'Долг'), str_starts_with($state, 'Заблокирован') => 'danger',
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
                Tables\Actions\Action::make('add_student')
                    ->label('Добавить ученика')
                    ->icon('heroicon-o-plus')
                    ->color('gray')
                    ->modalSubmitActionLabel('Добавить')
                    ->form([
                        Forms\Components\Select::make('student_id')
                            ->label('Выберите ученика')
                            ->options(function () {
                                // Get all students NOT already associated with this teacher
                                return User::where('role', 'student')
                                    ->whereDoesntHave('teachers', function ($q) {
                                    $q->where('users.id', auth()->id());
                                })
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $student = User::find($data['student_id']);
                        if ($student) {
                            $changes = auth()->user()->students()->syncWithoutDetaching([$student->id]);

                            if (count($changes['attached']) > 0) {
                                // Notify the student
                                // Notify the student
                                $student->notify(new \App\Notifications\NewTeacher(auth()->user()));

                                Notification::make()
                                    ->title('Ученик добавлен')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Ученик уже в вашем списке')
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),
                Tables\Actions\Action::make('invite_student')
                    ->label('Пригласить ученика')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Forms\Components\Section::make('Ссылка для приглашения')
                            ->description('Отправьте эту ссылку ученику, чтобы он мог зарегистрироваться и автоматически добавиться в ваш список.')
                            ->schema([
                                Forms\Components\TextInput::make('invitation_link')
                                    ->label('Ссылка')
                                    ->default(fn() => \Illuminate\Support\Facades\URL::signedRoute('student.invitation', ['teacher' => auth()->id()]))
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
                            ->schema([
                                Forms\Components\TextInput::make('email')
                                    ->label('Email ученика')
                                    ->email()
                                    ->placeholder('student@example.com'),
                            ]),
                    ])
                    ->modalSubmitActionLabel('Отправить')
                    ->action(function (array $data) {
                        if (!empty($data['email'])) {
                            \Illuminate\Support\Facades\Mail::to($data['email'])->send(new \App\Mail\StudentInvitation($data['invitation_link'], auth()->user()->name));

                            Notification::make()
                                ->title('Приглашение отправлено')
                                ->body("Письмо отправлено на {$data['email']}")
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc')
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
            ])
            ->recordUrl(fn(User $record): string => Pages\ViewStudent::getUrl([$record]))
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
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
