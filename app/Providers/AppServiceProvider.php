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

        // Фото по id для аватаров — кэш на один запрос или одну задачу очереди
        $this->app->scoped(\App\Support\UserPhotos::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Письмо «Восстановление пароля» — в общем оформлении писем и без канцелярита
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $minutes = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Восстановление пароля — ' . \App\Support\Seo::SITE_NAME)
                ->greeting('Восстановление пароля')
                ->line('Нажмите кнопку и задайте новый пароль. Ссылка действует ' . plural_ru($minutes, 'минуту', 'минуты', 'минут') . '.')
                ->action('Задать новый пароль', route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]))
                ->line('Если вы не просили восстановить пароль, ничего делать не нужно — он останется прежним.');
        });

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
            'avatar' => $user->avatar_thumb_url,
        ]);
    }
}
