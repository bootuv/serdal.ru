<?php

namespace App\Filament\Student\Widgets;

use App\Services\StudentTeachersService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StudentTeachersWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Мои учителя';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                app(StudentTeachersService::class)->currentTeachers((int) auth()->id())
            )
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->size(50),

                Tables\Columns\TextColumn::make('name')
                    ->label('Имя')
                    ->weight('bold')
                    ->description(fn(\App\Models\User $record) => $record->subjects->pluck('name')->join(', ')),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон')
                    ->icon('heroicon-m-phone')
                    ->copyable(),

                Tables\Columns\TextColumn::make('sessions_count')
                    ->label('Занятий')
                    ->badge()
                    ->color('success')
                    ->state(fn (\App\Models\User $record) => app(StudentTeachersService::class)
                        ->lessonsWithCurrentTeacher((int) auth()->id(), $record)->count()),

            ])
            ->emptyStateHeading('У вас нет учителей')
            ->emptyStateDescription('')
            ->recordUrl(fn(\App\Models\User $record): string => route('tutors.show', ['username' => $record->username]))
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('leave_review')
                    ->visible(function (\App\Models\User $record) {
                        $service = app(StudentTeachersService::class);
                        $count = $service->lessonsWithCurrentTeacher((int) auth()->id(), $record)->count();

                        return $service->canReview((int) auth()->id(), $record->id, $count);
                    })
                    ->label(function (\App\Models\User $record) {
                        $review = \App\Models\Review::where('user_id', auth()->id())->where('teacher_id', $record->id)->first();
                        if ($review) {
                            $stars = str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating);
                            return new \Illuminate\Support\HtmlString('<span style="color: #F59E0B;">' . $stars . '</span>');
                        }
                        return 'Оставить отзыв';
                    })
                    ->color(fn($record) => \App\Models\Review::where('user_id', auth()->id())->where('teacher_id', $record->id)->exists() ? 'gray' : 'primary')
                    ->button()
                    ->slideOver()
                    ->modalHeading('Отзыв')
                    ->form([
                        \Filament\Forms\Components\Grid::make()
                            ->schema([
                                \Filament\Forms\Components\ViewField::make('rating')
                                    ->label('Оценка')
                                    ->view('filament.forms.components.star-rating')
                                    ->default(5)
                                    ->required(),
                                \Filament\Forms\Components\Textarea::make('text')
                                    ->label('Текст отзыва')
                                    ->rows(3)
                                    ->required()
                                    ->maxLength(\App\Models\Review::MAX_TEXT)
                                    ->helperText('Без телефонов и ссылок')
                                    ->rules([new \App\Rules\NoContacts]),
                            ])
                            ->columns(1),
                    ])
                    ->mountUsing(function (\Filament\Forms\Form $form, \App\Models\User $record) {
                        $data = [
                            'rating' => 5,
                            'text' => null,
                        ];

                        $review = \App\Models\Review::where('user_id', auth()->id())
                            ->where('teacher_id', $record->id)
                            ->first();

                        if ($review) {
                            $data['rating'] = $review->rating;
                            $data['text'] = $review->text;
                        }

                        $form->fill($data);
                    })
                    ->action(function (array $data, \App\Models\User $record) {
                        app(StudentTeachersService::class)->saveReview(auth()->user(), $record, (int) $data['rating'], (string) $data['text']);
                    })
                    ->successNotificationTitle('Отзыв сохранен'),
            ]);
    }
}
