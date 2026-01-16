<?php

namespace App\Filament\App\Resources\StudentResource\Pages;

use App\Filament\App\Resources\StudentResource;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\MeetingSession;
use App\Models\User;
use App\Filament\App\Resources\StudentResource\Widgets\StudentPerformanceChart;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\HtmlString;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    public function getTitle(): string
    {
        return 'Профиль ученика';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('delete_from_list')
                ->label('Удалить из списка')
                ->color('danger')
                ->link()
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function () {
                    $record = $this->record;
                    $teacher = auth()->user();
                    $teacher->students()->detach($record);

                    // Remove student from all teacher's rooms
                    $teacherRooms = \App\Models\Room::where('user_id', $teacher->id)->get();
                    foreach ($teacherRooms as $room) {
                        $room->participants()->detach($record->id);
                    }

                    // Check if student can leave a review
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

                    // Notify the student
                    $record->notify(new \App\Notifications\TeacherRemoved($teacher, $canLeaveReview));

                    \Filament\Notifications\Notification::make()
                        ->title('Ученик удален из списка')
                        ->success()
                        ->send();

                    $this->redirect(StudentResource::getUrl('index'));
                }),
        ];
    }

    // ...

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make()
                    ->columns([
                        'sm' => 1,
                        'md' => 4,
                    ])
                    ->schema([
                        // Left: Avatar only (1 column)
                        Infolists\Components\Group::make()
                            ->columnSpan(['md' => 1])
                            ->schema([
                                Infolists\Components\ImageEntry::make('avatar_url')
                                    ->hiddenLabel()
                                    ->circular()
                                    ->width(160)
                                    ->height(160),
                            ]),

                        // Right: Name, subtitle, and profile data (3 columns)
                        Infolists\Components\Group::make()
                            ->columnSpan(['md' => 3])
                            ->schema([
                                // Name and subtitle (full width)
                                Infolists\Components\TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                                // Profile data in grid
                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('email')
                                            ->label('Email'),
                                        Infolists\Components\TextEntry::make('phone')
                                            ->label('Телефон')
                                            ->default('-'),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Дата регистрации')
                                            ->dateTime('d.m.Y'),
                                    ]),
                            ]),
                    ]),

                // Profile Section is above this (lines 33-68)

                Infolists\Components\Grid::make(['default' => 1, 'md' => 3]) // Use 3 columns grid: 2 for stats, 1 for chart
                    ->schema([
                        Infolists\Components\Group::make()
                            ->columnSpan(['md' => 2])
                            ->schema([
                                Infolists\Components\Section::make('Статистика посещаемости')
                                    ->schema([
                                        Infolists\Components\Grid::make(3)
                                            ->schema([
                                                Infolists\Components\TextEntry::make('total_sessions')
                                                    ->label('Всего уроков')
                                                    ->state(fn(User $record) => $this->getVisitsStats($record)['total']),
                                                Infolists\Components\TextEntry::make('attended_sessions')
                                                    ->label('Посещено')
                                                    ->state(fn(User $record) => $this->getVisitsStats($record)['attended'])
                                                    ->color('success'),
                                                Infolists\Components\TextEntry::make('missed_sessions')
                                                    ->label('Пропущено')
                                                    ->state(fn(User $record) => $this->getVisitsStats($record)['missed'])
                                                    ->color('danger'),
                                            ]),
                                    ]),

                                Infolists\Components\Section::make('Домашние задания')
                                    ->schema([
                                        Infolists\Components\Grid::make(3)
                                            ->schema([
                                                Infolists\Components\TextEntry::make('total_homeworks')
                                                    ->label('Назначено ДЗ')
                                                    ->state(fn(User $record) => $this->getHomeworkStats($record)['total']),
                                                Infolists\Components\TextEntry::make('submitted_homeworks')
                                                    ->label('Сдано')
                                                    ->state(fn(User $record) => $this->getHomeworkStats($record)['submitted'])
                                                    ->color('success'),
                                                Infolists\Components\TextEntry::make('overdue_homeworks')
                                                    ->label('Просрочено')
                                                    ->state(fn(User $record) => $this->getHomeworkStats($record)['overdue'])
                                                    ->color('danger'),
                                            ]),
                                    ]),

                                Infolists\Components\Section::make('История посещений')
                                    ->collapsed()
                                    ->schema([
                                        Infolists\Components\View::make('filament.app.resources.student-resource.pages.attendance-history')
                                            ->viewData([
                                                'history' => $this->getAttendanceHistory($this->record),
                                            ]),
                                    ]),
                            ]),

                        Infolists\Components\Group::make()
                            ->columnSpan(['md' => 1])
                            ->schema([
                                Infolists\Components\View::make('filament.app.resources.student-resource.pages.student-performance-chart-entry')
                                    ->viewData([
                                        'record' => $this->record,
                                    ]),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getVisitsStats(User $student): array
    {
        $teacherId = auth()->id();

        // Ensure we only count completed sessions relevant to this teacher
        $sessions = MeetingSession::whereHas('room', function ($q) use ($teacherId) {
            $q->where('user_id', $teacherId);
        })
            ->where('status', 'completed') // Only completed sessions
            ->get();

        $total = 0;
        $attended = 0;

        foreach ($sessions as $session) {
            // Check if student was a participant in the room at that time
            // We need to know if the student was assigned to the room when the session happened?
            // Or simpler: Check if student exists in the room participants NOW? 
            // Ideally we should rely on pricing_snapshot or analytics_data to know if they were INTENDED to be there.
            // But if pricing_snapshot doesn't exist, we fallback to room participants.

            // Check if student is in this room's participant list?
            // To properly calculate "Total Lessons", we should count all sessions of rooms where student is a participant.

            $room = $session->room;
            if (!$room)
                continue;

            // Optimization: load participants once or use relationship existence check
            // But for accurate "Total", we assume if student is assigned to room, they should have attended all sessions?
            // Or only sessions created after they were assigned? This is hard to track without "assigned_at".
            // Let's assume simplest logic: All completed sessions of rooms where student is CURRENTLY assigned.

            if (!$room->participants->contains($student->id)) {
                continue; // Student not assigned to this room
            }

            $total++;

            $attendance = $session->getStudentAttendance();
            // getStudentAttendance returns aggregation. We need specific student status.
            // We need to check if THIS student ($student->id) attended.

            $isAttended = false;
            $studentIdStr = (string) $student->id;

            // Check pricing snapshot first
            if (isset($session->pricing_snapshot['participants'])) {
                foreach ($session->pricing_snapshot['participants'] as $p) {
                    if (($p['user_id'] ?? '') == $studentIdStr && ($p['attended'] ?? false)) {
                        $isAttended = true;
                        break;
                    }
                }
            } else {
                // Fallback to analytics
                $analytics = $session->analytics_data ?? [];
                $participants = $analytics['participants'] ?? [];
                foreach ($participants as $p) {
                    if (($p['user_id'] ?? '') == $studentIdStr) {
                        $isAttended = true;
                        break;
                    }
                }
            }

            if ($isAttended) {
                $attended++;
            }
        }

        return [
            'total' => $total,
            'attended' => $attended,
            'missed' => $total - $attended,
            'rate' => $total > 0 ? ($attended / $total) * 100 : 0,
        ];
    }

    protected function getHomeworkStats(User $student): array
    {
        $teacherId = auth()->id();

        $homeworks = Homework::where('teacher_id', $teacherId)
            ->whereHas('students', function ($q) use ($student) {
                $q->where('users.id', $student->id);
            })
            ->with([
                'submissions' => function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                }
            ])
            ->get();

        $total = $homeworks->count();
        $submitted = 0;
        $overdue = 0;
        $gradesSum = 0;
        $gradesCount = 0;

        foreach ($homeworks as $homework) {
            $submission = $homework->submissions->first();

            if ($submission && $submission->submitted_at) {
                $submitted++;
                if ($submission->grade !== null) {
                    // Normalize grade to 100-scale? Or just average raw?
                    // Let's normalize to 100 scale for average_grade metrics usually.
                    // But for display, maybe just raw average if scales are consistent?
                    // Homework model has effective_max_score

                    $max = $homework->effective_max_score;
                    if ($max > 0) {
                        $gradesSum += ($submission->grade / $max) * 100;
                        $gradesCount++;
                    }
                }
            } else {
                if ($homework->is_overdue) {
                    $overdue++;
                }
            }
        }

        return [
            'total' => $total,
            'submitted' => $submitted,
            'overdue' => $overdue,
            'average_grade' => $gradesCount > 0 ? round($gradesSum / $gradesCount) : '—',
        ];
    }

    protected function getAttendanceHistory(User $student): array
    {
        $teacherId = auth()->id();

        $sessions = MeetingSession::whereHas('room', function ($q) use ($teacherId) {
            $q->where('user_id', $teacherId);
        })
            ->where('status', 'completed')
            ->orderBy('ended_at', 'desc')
            ->get();

        $history = [];
        $studentIdStr = (string) $student->id;

        foreach ($sessions as $session) {
            $room = $session->room;
            if (!$room || !$room->participants->contains($student->id)) {
                continue;
            }

            $isAttended = false;
            $activityScore = 0;

            $analytics = $session->analytics_data ?? [];
            $participants = $analytics['participants'] ?? [];

            // Check pricing_snapshot first
            if (isset($session->pricing_snapshot['participants'])) {
                foreach ($session->pricing_snapshot['participants'] as $p) {
                    if (($p['user_id'] ?? '') == $studentIdStr && ($p['attended'] ?? false)) {
                        $isAttended = true;
                        break;
                    }
                }
            }

            // Get activity score from analytics
            foreach ($participants as $p) {
                if (($p['user_id'] ?? '') == $studentIdStr) {
                    $isAttended = true;
                    // Calculate activity score: (Talk Time (m) * 2) + (Messages * 1) + (Emoji * 1) + (Raise Hand * 2)
                    $talkMinutes = ($p['talking_time'] ?? 0) / 60;
                    $rawScore = ($talkMinutes * 2) + ($p['message_count'] ?? 0) + ($p['emoji_count'] ?? 0) + (($p['raise_hand_count'] ?? 0) * 2);
                    $activityScore = min(10, round($rawScore));
                    break;
                }
            }

            $history[] = [
                'session_id' => $session->id,
                'room_id' => $room->id,
                'room_name' => $room->name ?? 'Урок',
                'date' => $session->ended_at?->format('d.m.Y H:i') ?? '-',
                'attended' => $isAttended,
                'activity_score' => $activityScore,
            ];
        }

        return $history;
    }
}
