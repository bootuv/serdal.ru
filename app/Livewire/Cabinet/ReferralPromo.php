<?php

namespace App\Livewire\Cabinet;

use App\Services\ReferralService;
use Livewire\Component;

/** Промо-плашка партнёрской программы в сайдбаре учителя (цвет promo — только для неё, BRAND.md). */
class ReferralPromo extends Component
{
    public bool $visible = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->visible = $user && ReferralService::shouldShowBanner($user);
    }

    public function hide(): void
    {
        ReferralService::hideBanner(auth()->user());
        $this->visible = false;
    }

    public function render()
    {
        return view('livewire.cabinet.referral-promo', [
            'bonus' => ReferralService::referrerBonus(),
            'href' => \Illuminate\Support\Facades\Route::has('cabinet.teacher.referrals') ? route('cabinet.teacher.referrals') : url('/tutor/referrals'),
        ]);
    }
}
