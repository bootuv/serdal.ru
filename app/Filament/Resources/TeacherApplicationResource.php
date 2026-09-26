<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherApplicationResource\Pages;
use App\Models\TeacherApplication;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Services\TeacherApplicationService;
use Filament\Notifications\Notification;

class TeacherApplicationResource extends Resource
{
    protected static ?string $model = TeacherApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Заявки учителей';

    protected static ?string $modelLabel = 'Заявка';

    protected static ?string $pluralModelLabel = 'Заявки учителей';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация о заявителе')
                    ->schema([
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('last_name')->label('Фамилия'),
                            Forms\Components\TextInput::make('first_name')->label('Имя'),
                            Forms\Components\TextInput::make('middle_name')->label('Отчество'),
                        ])->columns(3)->columnSpanFull(),

                        Forms\Components\TextInput::make('email')->label('Email')->columnSpanFull(),

                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('phone')->label('Телефон'),
                        ])->columns(1)->columnSpanFull(),

                        Forms\Components\Textarea::make('about')->label('О себе')->columnSpanFull(),

                        Forms\Components\KeyValue::make('subjects_list') // Визуализация для просмотра
                            ->label('Предметы (IDs)')
                            ->formatStateUsing(fn($record) => $record?->subjects ?? []), // Просто показать IDs или попробовать загрузить имена?

                        // Лучше просто показать статус
                        Forms\Components\TextInput::make('status')
                            ->label('Статус')
                            ->readOnly(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name') // Accessor from Model
                    ->label('ФИО')
                    ->searchable(['last_name', 'first_name', 'middle_name']),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('desiredTariff.name')
                    ->label('Выбранный тариф')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('referrer.name')
                    ->label('Пригласил')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->formatStateUsing(fn($state) => format_datetime($state))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'На рассмотрении',
                        'approved' => 'Одобрено',
                        'rejected' => 'Отклонено',
                        default => $state,
                    })
                    ->toggleable(),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->form(
                        fn(Form $form) => $form
                            ->schema([
                                Forms\Components\Section::make('Личные данные')
                                    ->schema([
                                        Forms\Components\Group::make([
                                            Forms\Components\TextInput::make('last_name')->label('Фамилия'),
                                            Forms\Components\TextInput::make('first_name')->label('Имя'),
                                            Forms\Components\TextInput::make('middle_name')->label('Отчество'),
                                        ])->columns(3)->columnSpanFull(),
                                        Forms\Components\TextInput::make('email')->label('Email'),
                                        Forms\Components\TextInput::make('phone')->label('Телефон'),
                                    ]),
                                Forms\Components\Section::make('Контакты в мессенджерах')
                                    ->schema([
                                        Forms\Components\TextInput::make('whatsup')->label('WhatsApp'),
                                        Forms\Components\TextInput::make('telegram')->label('Telegram'),
                                    ])->columns(3),
                                Forms\Components\Section::make('Профессиональные данные')
                                    ->schema([
                                        Forms\Components\Textarea::make('about')->label('О себе')->columnSpanFull(),
                                        Forms\Components\TextInput::make('subjects_display')
                                            ->label('Предметы')
                                            ->formatStateUsing(function ($state, $record) {
                                                if (empty($record->subjects))
                                                    return 'Не указано';
                                                $names = \App\Models\Subject::whereIn('id', $record->subjects)->pluck('name')->toArray();
                                                return implode(', ', $names);
                                            })
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('directs_display')
                                            ->label('Направления')
                                            ->formatStateUsing(function ($state, $record) {
                                                if (empty($record->directs))
                                                    return 'Не указано';
                                                $names = \App\Models\Direct::whereIn('id', $record->directs)->pluck('name')->toArray();
                                                return implode(', ', $names);
                                            })
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('grade_display')
                                            ->label('Классы')
                                            ->formatStateUsing(function ($state, $record) {
                                                if (empty($record->grade))
                                                    return 'Не указано';
                                                return implode(', ', $record->grade);
                                            })
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpanFull(),
                                    ]),
                            ])
                    )
                    ->modalFooterActions(fn(Tables\Actions\ViewAction $action, TeacherApplication $record) => [
                        Tables\Actions\DeleteAction::make()
                            ->record($record)
                            ->label('Удалить заявку')
                            ->icon('heroicon-o-trash')
                            ->link()
                            ->color('danger')
                            ->modalHeading('Удалить заявку')
                            ->successRedirectUrl(static::getUrl('index')),
                    ]),

                Tables\Actions\Action::make('approve')
                    ->label('Принять')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(TeacherApplication $record) => $record->status === 'pending')
                    ->action(function (TeacherApplication $record) {
                        // Логика одобрения — TeacherApplicationService (общая с новой админкой)
                        if (! app(TeacherApplicationService::class)->approve($record)) {
                            Notification::make()
                                ->title('Ошибка')
                                ->body('Пользователь с таким Email уже существует')
                                ->danger()
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->title('Заявка одобрена')
                            ->body("Пользователь создан. Пароль отправлен.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn(TeacherApplication $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Причина (уйдёт в письме, необязательно)'),
                    ])
                    ->action(function (TeacherApplication $record, array $data) {
                        app(TeacherApplicationService::class)->reject($record, $data['reason'] ?? null);

                        Notification::make()
                            ->title('Заявка отклонена')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ManageTeacherApplications::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
