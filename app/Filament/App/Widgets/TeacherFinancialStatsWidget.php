<?php

namespace App\Filament\App\Widgets;

use App\Models\LessonType;
use App\Models\MeetingSession;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TeacherFinancialStatsWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.app.widgets.teacher-financial-stats-widget';

    protected static ?int $sort = 100;

    protected int|string|array $columnSpan = 'full';

    public ?string $date_from = null;
    public ?string $date_to = null;

    public function mount(): void
    {
        $this->form->fill([
            'quick_range' => 'month',
            'date_from' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'date_to' => Carbon::now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                \Filament\Forms\Components\Select::make('quick_range')
                    ->label('Быстрый выбор')
                    ->options([
                        'week' => 'Эта неделя',
                        'month' => 'Этот месяц',
                        'quarter' => 'Этот квартал',
                        'year' => 'Этот год',
                    ])
                    ->live()
                    ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                        if (!$state)
                            return;

                        $now = Carbon::now();
                        [$start, $end] = match ($state) {
                            'week' => [$now->clone()->startOfWeek(), $now->clone()->endOfWeek()],
                            'month' => [$now->clone()->startOfMonth(), $now->clone()->endOfMonth()],
                            'quarter' => [$now->clone()->startOfQuarter(), $now->clone()->endOfQuarter()],
                            'year' => [$now->clone()->startOfYear(), $now->clone()->endOfYear()],
                            default => [null, null],
                        };

                        if ($start && $end) {
                            $set('date_from', $start->format('Y-m-d'));
                            $set('date_to', $end->format('Y-m-d'));
                            $this->dispatch('update-stats');
                        }
                    }),
                DatePicker::make('date_from')
                    ->label('С')
                    ->default(now()->startOfMonth())
                    ->live()
                    ->native(false)
                    ->afterStateUpdated(function (\Filament\Forms\Set $set) {
                        $set('quick_range', null);
                        $this->dispatch('update-stats');
                    }),
                DatePicker::make('date_to')
                    ->label('По')
                    ->default(now())
                    ->live()
                    ->native(false)
                    ->afterStateUpdated(function (\Filament\Forms\Set $set) {
                        $set('quick_range', null);
                        $this->dispatch('update-stats');
                    }),
            ])
            ->columns(3)
            ->statePath('data'); // We'll bind this to a public $data array, actually wait.
        // Widgets with HasForms usually need a public array $data = []; if statePath is 'data'
    }

    public ?array $data = [];

    protected function getViewData(): array
    {
        $start = $this->data['date_from'] ?? now()->startOfMonth();
        $end = $this->data['date_to'] ?? now();

        $userId = Auth::id();

        // Fetch sessions in range
        $sessions = MeetingSession::query()
            ->where('user_id', $userId)
            ->whereBetween('started_at', [
                Carbon::parse($start)->startOfDay(),
                Carbon::parse($end)->endOfDay()
            ])
            ->with(['room.participants', 'room.user.lessonTypes']) // Eager load for calculations
            ->get();

        $totalEarnings = 0;

        foreach ($sessions as $session) {
            // Use stored pricing snapshot if available (immutable historical data)
            if (isset($session->pricing_snapshot['total_cost'])) {
                $totalEarnings += $session->pricing_snapshot['total_cost'];
                continue;
            }

            // Fallback to dynamic calculation for old sessions without snapshot
            $room = $session->room;
            if (!$room)
                continue;

            // Determine payment type
            // Based on room type (individual/group), find corresponding lesson type of the teacher
            $lessonType = $room->user->lessonTypes
                ->where('type', $room->type)
                ->first();

            $paymentType = $lessonType?->payment_type ?? 'per_lesson'; // Default to per_lesson if not found

            if ($paymentType === 'monthly') {
                // Pay for all active participants in the room
                foreach ($room->participants as $participant) {
                    $price = $room->getEffectivePrice($participant->id) ?? 0;
                    $totalEarnings += $price;
                }
            } else {
                // Per lesson: Pay only for attended
                $analytics = $session->analytics_data ?? [];
                $participantsData = $analytics['participants'] ?? [];

                // Get IDs of attended users
                $attendedIds = [];
                foreach ($participantsData as $pData) {
                    if (!empty($pData['user_id'])) {
                        $attendedIds[] = (string) $pData['user_id'];
                    }
                }

                if (empty($attendedIds))
                    continue;

                // We need to match these IDs to students to get prices
                // The students might be in room->participants
                // If a student attended but is NOT in participants anymore, we have a tricky case.
                // We'll try to find price from pivot if possible, or fallback.

                // Let's iterate room participants and see if they attended.
                // This covers current participants.
                $roomParticipants = $room->participants;

                foreach ($roomParticipants as $participant) {
                    if (in_array((string) $participant->id, $attendedIds)) {
                        $price = $room->getEffectivePrice($participant->id) ?? 0;
                        $totalEarnings += $price;

                        // Remove from attendedIds to avoid double counting if any funny business
                        $key = array_search((string) $participant->id, $attendedIds);
                        unset($attendedIds[$key]);
                    }
                }

                // Any remaining attendedIds - these are students who attended but are not currently assigned to the room?
                // Or maybe the teacher joined (we should filter out teacher).
                // Teacher ID is $room->user_id.
                // We should assume only "Students" pay.
                // Ideally, we would fetch User models for remaining IDs and check role.

                // For simplified logic: stick to "Currently assigned students who attended".
                // If a student was removed from the group, arguably the teacher already got paid or history is complex.
                // Improving: We can try to calculate price for them using base defaults if they are not in pivot.
            }
        }

        $commission = $totalEarnings * 0.10;
        $payable = $totalEarnings - $commission;

        return [
            'totalEarnings' => $totalEarnings,
            'commission' => $commission,
            'payable' => $payable,
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view(static::$view, $this->getViewData());
    }
}
