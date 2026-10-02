<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AdminHome;
use App\Http\Middleware\AuthenticateAdminPanel;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Delight Admin')
            ->authGuard('web')
            ->defaultThemeMode(ThemeMode::System)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->pages([AdminHome::class])
            ->navigationItems([
                NavigationItem::make('Analytics')
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn (): string => route('admin.analytics.index')),
                NavigationItem::make('Announcements')
                    ->icon('heroicon-o-megaphone')
                    ->url(fn (): string => route('admin.announcements.index')),
                NavigationItem::make('Back to Delight')
                    ->icon('heroicon-o-arrow-left')
                    ->url(fn (): string => route('dashboard')),
            ])
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
                AuthenticateAdminPanel::class,
            ], isPersistent: true);
    }
}
