<?php

namespace App\Livewire\Cabinet;

use App\Services\CabinetTourService;
use Livewire\Component;

/**
 * Тур по кабинету учителя и ученика: при первом входе открывается сам, потом — по ссылке «Тур по кабинету» в меню.
 * Шаги — CabinetTourService, показ и переходы между экранами — resources/js/tour.js.
 */
class Tour extends Component
{
    /** Открыть самим: человеку тур ещё не предлагали. */
    public bool $auto = false;

    public function mount(): void
    {
        $this->auto = CabinetTourService::shouldAutoStart(auth()->user());
    }

    /** Тур пройден, закрыт или отложен — сам больше не откроется. */
    public function seen(): void
    {
        $user = auth()->user();
        if ($user) {
            CabinetTourService::markSeen($user);
        }
        $this->auto = false;
        $this->skipRender();
    }

    public function render()
    {
        $user = auth()->user();
        $available = CabinetTourService::available($user);

        return view('livewire.cabinet.tour', [
            'steps' => $available ? app(CabinetTourService::class)->steps($user) : [],
        ]);
    }
}
