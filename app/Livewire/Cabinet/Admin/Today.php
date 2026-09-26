<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\User;
use App\Services\AdminTodayService;
use App\Support\HumanDate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** «Сегодня» администратора. Макет: AdminToday (очередь исключений, идут сейчас, итоги периода, «Всё разобрано»). */
#[Layout('components.layouts.cabinet', ['title' => 'Сегодня', 'active' => 'today'])]
class Today extends Component
{
    use AdminScreen;

    /** Период итогов: 7, 30 или 90 дней. */
    #[Url(except: 7)]
    public int $period = 7;

    /** Учитель в фильтре итогов (пусто — все). */
    #[Url(except: '')]
    public string $teacher = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
        if (! in_array($this->period, AdminTodayService::PERIODS, true)) {
            $this->period = 7;
        }
    }

    /** Занятие началось или завершилось — обновляем «Идут сейчас». */
    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function updatedPeriod(): void
    {
        if (! in_array((int) $this->period, AdminTodayService::PERIODS, true)) {
            $this->period = 7;
        }
    }

    public function render()
    {
        $service = app(AdminTodayService::class);
        $teacher = $this->teacher !== '' ? User::where('role', User::ROLE_TUTOR)->find((int) $this->teacher) : null;

        return view('livewire.cabinet.admin.today', [
            'today' => HumanDate::todayLong(),
            'queue' => $service->queue(),
            'live' => $service->live(),
            'teachers' => $service->teacherOptions(),
            'summary' => $service->period((int) $this->period, $teacher),
            'lessonsUrl' => \Illuminate\Support\Facades\Route::has('cabinet.admin.lessons') ? route('cabinet.admin.lessons') : null,
        ]);
    }
}
