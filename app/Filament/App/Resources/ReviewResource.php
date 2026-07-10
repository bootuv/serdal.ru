<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Отзывы';
    protected static ?string $modelLabel = 'Отзыв';
    protected static ?string $pluralModelLabel = 'Отзывы';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('teacher_id', auth()->id())
            ->where('is_rejected', false);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereNull('teacher_read_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Непрочитанные отзывы';
    }

    // Disable creation
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('rating')
                    ->label('Оценка')
                    ->disabled(),
                Forms\Components\Textarea::make('text')
                    ->label('Текст отзыва')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Студент')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Оценка')
                    ->formatStateUsing(fn($state) => str_repeat('★', $state) . str_repeat('☆', 5 - $state))
                    ->color('warning')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('text')
                    ->label('Текст')
                    ->limit(60)
                    ->wrap()
                    ->tooltip(fn($state) => $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->formatStateUsing(fn($state) => format_datetime($state))
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_reported')
                    ->label('Жалоба')
                    ->tooltip(fn(Review $record) => $record->is_reported ? 'Жалоба отправлена' : null)
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->recordAction('view')
            ->recordUrl(null)
            ->actions([
                Tables\Actions\Action::make('share')
                    ->label('Поделиться')
                    ->icon('heroicon-o-share')
                    ->color('primary')
                    ->url(fn(Review $record) => route('reviews.share-card', $record))
                    ->extraAttributes(['onclick' => 'serdalShareReviewCard(this.href); return false;']),
                Tables\Actions\ViewAction::make()
                    // кнопка скрыта: просмотр открывается кликом по строке (recordAction),
                    // но сам экшен должен оставаться видимым, иначе клик не смонтирует модалку
                    ->extraAttributes(['class' => 'hidden'])
                    // открытие модалки считается прочтением отзыва
                    ->mountUsing(function (Review $record) {
                        if ($record->teacher_read_at === null) {
                            $record->forceFill(['teacher_read_at' => now()])->saveQuietly();
                        }
                    })
                    ->modalHeading('Отзыв')
                    ->modalCancelAction(false)
                    ->infolist([
                        \Filament\Infolists\Components\Section::make()
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('user.name')
                                    ->label('Студент'),
                                \Filament\Infolists\Components\TextEntry::make('rating')
                                    ->label('Оценка')
                                    ->formatStateUsing(fn($state) => str_repeat('★', $state) . str_repeat('☆', 5 - $state))
                                    ->color('warning'),
                                \Filament\Infolists\Components\TextEntry::make('text')
                                    ->label('Текст отзыва')
                                    ->columnSpanFull(),
                                \Filament\Infolists\Components\TextEntry::make('created_at')
                                    ->label('Дата')
                                    ->formatStateUsing(fn($state) => format_datetime($state)),
                            ])
                            ->columns(1),
                    ])
                    ->extraModalFooterActions([
                        Tables\Actions\Action::make('share_from_modal')
                            ->label('Поделиться')
                            ->icon('heroicon-o-share')
                            ->color('primary')
                            ->url(fn(Review $record) => route('reviews.share-card', $record))
                            ->extraAttributes(['onclick' => 'serdalShareReviewCard(this.href); return false;']),
                        Tables\Actions\Action::make('report')
                            ->label('Пожаловаться')
                            ->icon('heroicon-o-flag')
                            ->color('danger')
                            ->link()
                            ->requiresConfirmation()
                            ->modalHeading('Пожаловаться на отзыв')
                            ->modalDescription('Вы уверены, что хотите пожаловаться на этот отзыв? Администратор проверит его.')
                            ->visible(fn(Review $record) => !$record->is_reported)
                            ->action(function (Review $record) {
                                $record->update(['is_reported' => true]);

                                // Notify all admins
                                $teacher = auth()->user();
                                $admins = \App\Models\User::where('role', \App\Models\User::ROLE_ADMIN)->get();
                                $studentName = $record->user?->name ?? 'Ученик';

                                foreach ($admins as $admin) {
                                    $admin->notify(new \App\Notifications\TeacherReportedReview($record, $teacher));
                                }

                                \Filament\Notifications\Notification::make()
                                    ->title('Жалоба отправлена')
                                    ->success()
                                    ->send();
                            })
                            ->after(fn() => redirect()->to(request()->header('Referer'))),
                    ]),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
        ];
    }
}
