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
                    ->label('Занятий')
                    ->badge()
                    ->color('success')
                    ->state(function (\App\Models\User $record) {
                        $studentId = (string) auth()->id();

                        // Handle duplicate teacher accounts: find all User IDs matching the teacher's email
                        $teacherIds = \App\Models\User::where('email', $record->email)
                            ->pluck('id')
                            ->map(fn($id) => (string) $id)
                            ->toArray();

                        // ALSO include the current record ID just in case email is empty or unique constraint failed differently
                        $teacherIds[] = (string) $record->id;
                        $teacherIds = array_unique($teacherIds);

                        // Find all sessions where this student participated
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
                                // Check if ANY of the teacher's IDs are in the participant list
                                if (isset($p['user_id']) && in_array((string) $p['user_id'], $teacherIds)) {
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
