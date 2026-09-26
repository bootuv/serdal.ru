<?php

namespace App\Livewire;

use App\Models\Room;
use App\Models\User;
use App\Services\MessengerService;
use App\Support\HumanDate;
use Illuminate\Support\Facades\Route;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
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

    public function mount(Room $room)
    {
        $this->room = $room;

        // Учитель своего занятия сюда не ходит — занятие он начинает из кабинета
        if (auth()->id() === $room->user_id) {
            return redirect(Route::has('cabinet.teacher.lesson') ? route('cabinet.teacher.lesson', $room) : url('/'));
        }

        $this->checkRoomStatus();
    }

    private function configureBbb(): void
    {
        $owner = $this->room->user;
        if ($owner && $owner->bbb_url && $owner->bbb_secret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $owner->bbb_url,
                'bigbluebutton.BBB_SECURITY_SALT' => $owner->bbb_secret,
            ]);

            return;
        }

        $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
        $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');
        if ($globalUrl && $globalSecret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
            ]);
        }
    }

    public function checkRoomStatus(): void
    {
        $this->configureBbb();

        try {
            $this->isRoomRunning = (bool) Bigbluebutton::isMeetingRunning(['meetingID' => $this->room->meeting_id]);
        } catch (\Throwable $e) {
            // Нет связи с сервером видеосвязи — считаем по нашей отметке
            $this->isRoomRunning = (bool) $this->room->fresh()?->is_running;
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
