<?php

namespace App\Livewire;

use App\Models\Room;
use App\Models\User;
use App\Services\Bbb\BbbServerPool;
use App\Services\MessengerService;
use App\Support\HumanDate;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Вход в класс до начала занятия. Макеты: LsStudentWait (ученик ждёт учителя), LsGuestJoin (гость по ссылке).
 * Страница проверяет статус каждые 30 секунд; когда занятие началось — кнопка «Войти в класс» (→ rooms.connect).
 */
#[Layout('components.layouts.auth', ['title' => 'Вход в класс'])]
class GuestJoinRoom extends Component
{
    #[Locked]
    public Room $room;

    #[Locked]
    public bool $isRoomRunning = false;

    /** Имя гостя (для вошедших не нужно). */
    public string $name = '';

    /** Вход не удался: в классе нет свободных мест. Лимит участников (0 — неизвестен). */
    #[Locked]
    public ?int $full = null;

    public function mount(Room $room)
    {
        $this->room = $room;

        // Учитель своего занятия сюда не ходит — занятие он начинает из кабинета
        if (auth()->id() === $room->user_id) {
            return redirect(Route::has('cabinet.teacher.lesson') ? route('cabinet.teacher.lesson', $room) : url('/'));
        }

        if (session()->has(\App\Services\RoomCapacityService::SESSION_KEY)) {
            $this->full = (int) session(\App\Services\RoomCapacityService::SESSION_KEY);
            $this->name = (string) session('guest_name', '');
        }

        $this->checkRoomStatus();
    }

    public function checkRoomStatus(): void
    {
        // Свежая комната: пока страница открыта, учитель мог начать занятие на другом сервере
        $room = $this->room->fresh() ?? $this->room;
        $server = app(BbbServerPool::class)->forRoom($room);

        try {
            $this->isRoomRunning = (bool) $server?->client()->isMeetingRunning(['meetingID' => $room->meeting_id]);
        } catch (\Throwable $e) {
            // Нет связи с сервером видеосвязи — считаем по нашей отметке
            $this->isRoomRunning = (bool) $room->is_running;
        }
    }

    public function submitName()
    {
        $this->validate(['name' => ['required', 'string', 'max:255']], [
            'name.required' => 'Напишите, как вас зовут — это имя увидят участники.',
        ]);

        session(['guest_name' => trim($this->name)]);

        return redirect()->route('rooms.connect', $this->room);
    }

    public function render()
    {
        $user = auth()->user();
        $teacher = $this->room->user;
        $start = $this->room->next_start;

        $when = collect([
            $start ? mb_strtoupper(mb_substr($d = HumanDate::at($start), 0, 1)) . mb_substr($d, 1) : null,
            $this->room->duration ? plural_ru((int) $this->room->duration, 'минута', 'минуты', 'минут') : null,
        ])->filter()->join(' · ');

        $isStudent = $user?->role === User::ROLE_STUDENT;

        return view('livewire.auth.room-join', [
            'user' => $user,
            'teacher' => $teacher,
            'when' => $when,
            'chatUrl' => $user ? MessengerService::url($user, $this->room->id) : null,
            'materialsUrl' => $isStudent && Route::has('cabinet.student.materials') ? route('cabinet.student.materials') : null,
            'cabinetUrl' => $user ? \App\Http\Middleware\EnsureCabinetRole::homeFor($user) : null,
        ]);
    }
}
