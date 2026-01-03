<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\StudentResource\Pages;
use App\Filament\App\Resources\StudentResource\RelationManagers;
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

                            $colorClasses = $isGroup
                                ? 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20 dark:bg-orange-500/10 dark:text-orange-400 dark:ring-orange-400/30'
                                : 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-400/30';

                            return "<span class=\"inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium {$colorClasses}\">{$icon} <span>" . e($room->name) . "</span></span>";
                        })->join(' ');

                        return new \Illuminate\Support\HtmlString('<div class="flex flex-wrap gap-1.5">' . $badges . '</div>');
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
                Tables\Actions\ViewAction::make()
                    ->label('Информация')
                    ->modalHeading('Карточка ученика')
                    ->modalWidth('md')
                    ->extraAttributes(['class' => 'hidden']) // Hide the button visually
                    ->modalFooterActions(fn(User $record): array => [
                        Tables\Actions\Action::make('delete_from_list')
                            ->label('Удалить из списка')
                            ->color('danger')
                            ->icon('heroicon-o-trash')
                            ->requiresConfirmation()
                            ->action(function (User $record) {
                                $teacher = auth()->user();
                                auth()->user()->students()->detach($record);

                                // Remove student from all teacher's rooms
                                $teacherRooms = \App\Models\Room::where('user_id', $teacher->id)->get();
                                foreach ($teacherRooms as $room) {
                                    $room->participants()->detach($record->id);
                                }

                                // Check if student can leave a review:
                                // 1. Has at least one completed lesson with this teacher
                                // 2. Hasn't already left a review for this teacher
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

                                $canLeaveReview = $hasCompletedLesson && !$hasExistingReview;

                                // Notify the student about being removed
                                // Notify the student about being removed
                                $record->notify(new \App\Notifications\TeacherRemoved($teacher, $canLeaveReview));

                                Notification::make()
                                    ->title('Ученик удален из списка')
                                    ->success()
                                    ->send();
                            })
                            ->cancelParentActions(),
                    ]),

                Tables\Actions\EditAction::make()
                    ->label('Назначить занятия')
                    ->modalHeading('Управление занятиями ученика')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\CheckboxList::make('assignedRooms')
                            ->label('Занятия')
                            ->relationship('assignedRooms', 'name', modifyQueryUsing: fn(Builder $query) => $query->where('rooms.user_id', auth()->id()))
                            ->columns(1)
                            ->gridDirection('row')
                            ->searchable()
                            ->noSearchResultsMessage('Занятия не найдены')
                            ->bulkToggleable()
                    ])
                    ->after(function (User $record, array $data) {
                        $teacher = auth()->user();

                        // Get assigned rooms from database after save
                        $assignedRooms = $record->assignedRooms()
                            ->where('rooms.user_id', auth()->id())
                            ->get();

                        // Send notification for each assigned lesson
                        foreach ($assignedRooms as $room) {
                            $record->notify(new \App\Notifications\TeacherAssignedLesson($room, $teacher));
                        }
                    }),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
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
        ];
    }
}
