<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AdminHome;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Http\Middleware\AuthenticateAdminPanel;
use App\Http\Middleware\SecurityHeadersMiddleware;
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
            ->sidebarFullyCollapsibleOnDesktop()
            ->authGuard('web')
            ->defaultThemeMode(ThemeMode::System)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->pages([AdminHome::class])
            ->resources([AnnouncementResource::class])
            ->navigationItems([
                NavigationItem::make('Analytics')
                    ->sort(1)
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn (): string => route('admin.analytics.index')),
                NavigationItem::make('Back to Delight')
                    ->sort(3)
                    ->icon('heroicon-o-arrow-left')
                    ->url(fn (): string => route('dashboard')),
            ])
            ->middleware([
                SecurityHeadersMiddleware::class,
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
