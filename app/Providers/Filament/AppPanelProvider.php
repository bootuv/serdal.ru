<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Light)
            ->path('tutor')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->passwordReset()
            ->renderHook(
                'panels::body.end',
                fn() => \Illuminate\Support\Facades\Blade::render("@vite(['resources/css/app.css', 'resources/js/app.js'])")
            )
            ->renderHook(
                'panels::global-search.after',
                fn() => view('filament.app.components.subscription-badge')
            )
            ->renderHook(
                'panels::global-search.after',
                fn() => view('filament.app.components.profile-link')
            )
            // На мобильных ссылка на публичный профиль уезжает из шапки в низ бургер-меню
            ->renderHook(
                \Filament\View\PanelsRenderHook::SIDEBAR_FOOTER,
                fn() => view('filament.app.components.profile-link', ['inSidebar' => true])
            )
            // Постоянная ссылка на чат техподдержки внизу сайдбара
            ->renderHook(
                \Filament\View\PanelsRenderHook::SIDEBAR_FOOTER,
                fn() => view('filament.components.support-link')
            )
            ->renderHook(
                'panels::body.end',
                fn() => \Illuminate\Support\Facades\Blade::render('@livewire(\'push-notification-modal\')')
            )
            ->renderHook(
                'panels::body.end',
                fn() => view('filament.notifications.sound')
            )

            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Amber,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'indigo' => Color::Indigo,
                'purple' => Color::Purple,
                'pink' => Color::Pink,
                'teal' => Color::Teal,
                'orange' => Color::Orange,
            ])
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('')
                    ->extraSidebarAttributes(['class' => 'mt-5 pt-5 border-t border-gray-200 dark:border-white/10']),
            ])
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label('Подписка')
                    ->icon('heroicon-o-credit-card')
                    ->url(fn() => \App\Filament\App\Pages\ManageSubscription::getUrl()),
                \Filament\Navigation\MenuItem::make()
                    ->label('Платежи')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn() => \App\Filament\App\Pages\PaymentHistory::getUrl()),
                \Filament\Navigation\MenuItem::make()
                    ->label('Техподдержка')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn() => route('filament.app.pages.messenger', ['support' => 1])),
            ])
            ->brandLogo(fn() => asset('images/Logo.svg'))
            ->darkModeBrandLogo(fn() => asset('images/Logo-white.svg'))
            ->brandLogoHeight('2rem')
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_START,
                fn() => view('partials.favicon')
            )
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\\Filament\\App\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\\Filament\\App\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\CheckUserActive::class,
                \App\Http\Middleware\CheckUserProfileCompleted::class,
            ]);
    }
}
