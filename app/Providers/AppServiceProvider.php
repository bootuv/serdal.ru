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
        // Временные файлы Livewire: укорачиваем слишком длинные имена (см. контроллер)
        $this->app->bind(
            \Livewire\Features\SupportFileUploads\FileUploadController::class,
            \App\Http\Controllers\LivewireFileUploadController::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SEO-настройки читаются один раз за запрос; после изменения в админке сбрасываем их и кэш sitemap/llms
        \App\Models\Setting::saved(fn () => \App\Support\SeoSettings::flush());
        \App\Models\Setting::deleted(fn () => \App\Support\SeoSettings::flush());

        \App\Models\Room::observe(\App\Observers\RoomObserver::class);
        \App\Models\RoomSchedule::observe(\App\Observers\RoomScheduleObserver::class);

        \Illuminate\Support\Facades\Gate::define('viewPulse', function ($user) {
            return $user->isAdmin();
        });

        \Laravel\Pulse\Facades\Pulse::user(fn($user) => [
            'name' => $user->name,
            'extra' => $user->email,
            'avatar' => $user->avatar_url,
        ]);
    }
}
