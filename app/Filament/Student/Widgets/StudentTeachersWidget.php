<?php

namespace App\Filament\Student\Widgets;

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
                \App\Models\User::query()
                    ->whereHas('students', function ($query) {
                        $query->where('student_id', auth()->id());
                    })
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
                    ->state(function (\App\Models\User $record) {
                        $studentId = (string) auth()->id();
                        $teacherIds = \App\Models\User::where('email', $record->email)->pluck('id')->map(fn($id) => (string) $id)->toArray();
                        $teacherIds[] = (string) $record->id;
                        $teacherIds = array_unique($teacherIds);

                        $sessions = \App\Models\MeetingSession::query()
                            ->where(function ($q) use ($studentId) {
                                $q->whereJsonContains('analytics_data->participants', ['user_id' => $studentId])
                                    ->orWhereJsonContains('analytics_data->participants', ['user_id' => (int) $studentId]);
                            })
                            ->get();

                        return $sessions->filter(function ($session) use ($teacherIds) {
                            $participants = $session->analytics_data['participants'] ?? [];
                            if (!is_array($participants))
                                return false;
                            foreach ($participants as $p) {
                                if (isset($p['user_id']) && in_array((string) $p['user_id'], $teacherIds))
                                    return true;
                            }
                            return false;
                        })->count();
                    }),


            ])
            ->emptyStateHeading('У вас нет учителей')
            ->emptyStateDescription('')
            ->recordUrl(fn(\App\Models\User $record): string => route('tutors.show', ['username' => $record->username]))
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('leave_review')
                    ->visible(function (\App\Models\User $record) {
                        $studentId = (string) auth()->id();
                        $teacherIds = \App\Models\User::where('email', $record->email)->pluck('id')->map(fn($id) => (string) $id)->toArray();
                        $teacherIds[] = (string) $record->id;
                        $teacherIds = array_unique($teacherIds);

                        $sessions = \App\Models\MeetingSession::query()
                            ->where(function ($q) use ($studentId) {
                                $q->whereJsonContains('analytics_data->participants', ['user_id' => $studentId])
                                    ->orWhereJsonContains('analytics_data->participants', ['user_id' => (int) $studentId]);
                            })
                            ->get();

                        $count = $sessions->filter(function ($session) use ($teacherIds) {
                            $participants = $session->analytics_data['participants'] ?? [];
                            if (!is_array($participants))
                                return false;
                            foreach ($participants as $p) {
                                if (isset($p['user_id']) && in_array((string) $p['user_id'], $teacherIds))
                                    return true;
                            }
                            return false;
                        })->count();

                        $hasRejected = \App\Models\Review::where('user_id', auth()->id())
                            ->where('teacher_id', $record->id)
                            ->where('is_rejected', true)
                            ->exists();
                        return $count > 0 && !$hasRejected;
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
                                    ->required(),
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
                        $existingReview = \App\Models\Review::where('user_id', auth()->id())
                            ->where('teacher_id', $record->id)
                            ->first();

                        $isNew = !$existingReview;

                        $review = \App\Models\Review::updateOrCreate(
                            ['user_id' => auth()->id(), 'teacher_id' => $record->id],
                            ['rating' => $data['rating'], 'text' => $data['text']]
                        );

                        // Send notifications only for new reviews
                        if ($isNew) {
                            $student = auth()->user();

                            // Notify teacher
                            $record->notify(new \App\Notifications\StudentLeftReview($review, $student));

                            // Notify all admins
                            $admins = \App\Models\User::where('role', \App\Models\User::ROLE_ADMIN)->get();
                            foreach ($admins as $admin) {
                                $admin->notify(new \App\Notifications\StudentLeftReviewAdmin($review, $student, $record));
                            }
                        }
                    })
                    ->successNotificationTitle('Отзыв сохранен'),
            ]);
    }
}
