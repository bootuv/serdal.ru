<?php

namespace App\Filament\Student\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StudentTeachersWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Мои учителя';

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                auth()->user()->teachers()->getQuery()
            )
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->size(50),

                Tables\Columns\TextColumn::make('name')
                    ->label('Имя')
                    ->weight('bold')
                    ->description(fn(\App\Models\User $record) => $record->email),

                Tables\Columns\TextColumn::make('subjects.name')
                    ->label('Предметы')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('sessions_count')
                    ->label('Занятий.')
                    ->badge()
                    ->color('success')
                    ->state(function (\App\Models\User $record) {
                        $studentId = (string) auth()->id();
                        $teacherId = (string) $record->id;

                        // Find all sessions where this student participated
                        $sessions = \App\Models\MeetingSession::query()
                            ->where(function ($q) use ($studentId) {
                            $q->whereJsonContains('analytics_data->participants', ['user_id' => $studentId])
                                ->orWhereJsonContains('analytics_data->participants', ['user_id' => (int) $studentId]);
                        })
                            ->get();

                        // Count sessions where this specific teacher also participated
                        return $sessions->filter(function ($session) use ($teacherId) {
                            $participants = $session->analytics_data['participants'] ?? [];
                            if (!is_array($participants))
                                return false;

                            foreach ($participants as $p) {
                                // Check if teacher is in participants
                                if (isset($p['user_id']) && $p['user_id'] == $teacherId) {
                                    return true;
                                }
                            }
                            return false;
                        })->count();
                    }),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон')
                    ->icon('heroicon-m-phone')
                    ->copyable(),
            ])
            ->paginated(false)
            ->recordUrl(fn(\App\Models\User $record): string => route('tutors.show', ['username' => $record->username]))
            ->emptyStateHeading('У вас нет преподавателей')
            ->emptyStateDescription('');
    }
}
