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
        // Подпись колонки на каждой ячейке: мобильный CSS (resources/css/app.css)
        // рендерит таблицы карточками «метка: значение» через content: attr(data-label)
        \Filament\Tables\Columns\Column::configureUsing(function (\Filament\Tables\Columns\Column $column) {
            $column->extraCellAttributes(function () use ($column) {
                $label = $column->getLabel();

                return ['data-label' => strip_tags($label instanceof \Illuminate\Contracts\Support\Htmlable ? $label->toHtml() : (string) $label)];
            }, merge: true);
        });

        // Шаринг-карточки отзывов: на мобильных — системный экран «Поделиться» (Web Share API),
        // на десктопе и там, где шаринг файлов не поддерживается, — обычное скачивание
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::BODY_END,
            fn() => new \Illuminate\Support\HtmlString(<<<'HTML'
<script>
window.serdalShareReviewCard = async function (url) {
    // iPad на iPadOS представляется как Macintosh — отличаем его по мультитачу
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
        || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1);

    if (isMobile && navigator.canShare) {
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const blob = await response.blob();
            const file = new File([blob], 'serdal-review.jpg', { type: 'image/jpeg' });

            if (navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file] });
                return;
            }
        } catch (error) {
            if (error.name === 'AbortError') return; // пользователь закрыл шэринг
        }
    }

    window.location.href = url; // десктоп и фолбэк: скачать файл
};
</script>
HTML),
        );

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
