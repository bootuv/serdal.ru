<?php

namespace App\Filament\Student\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Query\Builder;

class StudentFormerTeachersWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Мои бывшие учителя';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return static::getQuery()->exists();
    }

    protected static function getQuery()
    {
        $studentId = (string) auth()->id();

        return User::query()
            ->whereIn('id', function (Builder $query) use ($studentId) {
                $query->select('rooms.user_id')
                    ->from('rooms')
                    ->join('meeting_sessions', 'meeting_sessions.room_id', '=', 'rooms.id')
                    ->where(function ($q) use ($studentId) {
                        $q->whereJsonContains('meeting_sessions.analytics_data->participants', ['user_id' => $studentId])
                            ->orWhereJsonContains('meeting_sessions.analytics_data->participants', ['user_id' => (int) $studentId]);
                    });
            })
            ->whereNotIn('id', function (Builder $query) use ($studentId) {
                $query->select('teacher_id')
                    ->from('teacher_student')
                    ->where('student_id', $studentId);
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                static::getQuery()
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
                    ->state(function (User $record) {
                        return \App\Models\MeetingSession::query()
                            ->whereHas('room', function ($query) use ($record) {
                                $query->where('user_id', $record->id);
                            })
                            ->get()
                            ->filter(function ($session) {
                                $participants = $session->analytics_data['participants'] ?? [];
                                if (!is_array($participants))
                                    return false;

                                $myId = auth()->id();
                                foreach ($participants as $p) {
                                    if (isset($p['user_id']) && $p['user_id'] == $myId) {
                                        return true;
                                    }
                                }
                                return false;
                            })
                            ->count();
                    }),


            ])
            ->paginated(false)
            ->recordUrl(fn(User $record): string => route('tutors.show', ['username' => $record->username]))
            ->actions([
                Tables\Actions\Action::make('leave_review')
                    ->visible(function (\App\Models\User $record) {
                        return \App\Models\MeetingSession::query()
                            ->whereHas('room', function ($query) use ($record) {
                                $query->where('user_id', $record->id);
                            })
                            ->get()
                            ->filter(function ($session) {
                                $participants = $session->analytics_data['participants'] ?? [];
                                if (!is_array($participants))
                                    return false;

                                $myId = auth()->id();
                                foreach ($participants as $p) {
                                    if (isset($p['user_id']) && $p['user_id'] == $myId) {
                                        return true;
                                    }
                                }
                                return false;
                            })
                            ->count() > 0 && !\App\Models\Review::where('user_id', auth()->id())->where('teacher_id', $record->id)->where('is_rejected', true)->exists();
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
                        \App\Models\Review::updateOrCreate(
                            ['user_id' => auth()->id(), 'teacher_id' => $record->id],
                            ['rating' => $data['rating'], 'text' => $data['text']]
                        );
                    })
                    ->successNotificationTitle('Отзыв сохранен'),
            ]);
    }
}
