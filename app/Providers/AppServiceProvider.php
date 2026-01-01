<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Room::observe(\App\Observers\RoomObserver::class);
        \App\Models\RoomSchedule::observe(\App\Observers\RoomScheduleObserver::class);

        \Illuminate\Support\Facades\Gate::define('viewPulse', function ($user) {
            return $user->isAdmin();
        });
    }
}
