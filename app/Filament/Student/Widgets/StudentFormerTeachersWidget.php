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
        $studentId = auth()->id();

        return User::query()
            ->whereIn('id', function (Builder $query) use ($studentId) {
                $query->select('user_id')
                    ->from('rooms')
                    ->whereIn('id', function (Builder $q) use ($studentId) {
                        $q->select('room_id')
                            ->from('room_user')
                            ->where('user_id', $studentId);
                    })
                    ->whereIn('id', function (Builder $q) {
                        $q->select('room_id')
                            ->from('meeting_sessions');
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
                    ->description(fn(User $record) => $record->email),

                Tables\Columns\TextColumn::make('subjects.name')
                    ->label('Предметы')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон')
                    ->icon('heroicon-m-phone')
                    ->copyable(),
            ])
            ->paginated(false)
            ->recordUrl(fn(User $record): string => route('tutors.show', ['username' => $record->username]));
    }
}
