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

    protected static ?int $navigationSort = 7;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('teacher_id', auth()->id())
            ->where('is_rejected', false);
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
                    ->limit(50)
                    ->tooltip(fn($state) => $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_reported')
                    ->label('Жалоба отправлена')
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
                Tables\Actions\ViewAction::make()
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
                                    ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->format('d.m.Y H:i')),
                            ])
                            ->columns(1),
                    ])
                    ->extraModalFooterActions([
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
                                    \Filament\Notifications\Notification::make()
                                        ->title('Жалоба на отзыв')
                                        ->body("Учитель {$teacher->name} пожаловался на отзыв ученика {$studentName}")
                                        ->icon('heroicon-o-flag')
                                        ->iconColor('danger')
                                        ->actions([
                                            \Filament\Notifications\Actions\Action::make('view')
                                                ->label('Открыть')
                                                ->button()
                                                ->url(route('filament.admin.resources.reviews.index'))
                                        ])
                                        ->sendToDatabase($admin)
                                        ->broadcast($admin);
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
