<?php

namespace App\Providers\Filament;

use App\Filament\Pages\EditProfile;
use App\Filament\Pages\Login;
use App\Filament\Resources\TemperatureDeviations\TemperatureDeviationResource;
use App\Filament\Resources\TemperatureHumidities\TemperatureHumidityResource;
use App\Http\Middleware\CheckPasswordChangeRequired;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class DashboardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('dashboard')
            ->path('/dashboard')
            ->login(Login::class)
            ->profile(EditProfile::class)
            ->spa()
            ->brandLogo(asset('assets/images/LOGO-MEDQUEST-HD.png'))
            ->brandLogoHeight('2rem')
            ->defaultThemeMode(ThemeMode::Light)
            ->favicon(asset('assets/images/Medquest-Favicon.png'))
            // ->databaseNotifications()
            ->databaseTransactions()
            ->maxContentWidth(Width::Full)
            ->plugins([
            ])
            ->colors([
                'primary' => Color::Blue,
            ])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn () => Auth::user()->name)
                    ->url(fn () => EditProfile::getUrl())
                    ->icon('heroicon-o-user-circle'),
            ])
            ->navigationItems([
                // Temperature & Humidity
                NavigationItem::make('All')
                    ->label('All')
                    ->url(fn () => TemperatureHumidityResource::getUrl('index'))
                    ->group('Temperature & Humidity')
                    ->isActiveWhen(fn () => request()->routeIs('filament.dashboard.resources.temperature-humidities.index'))
                    ->sort(1),
                NavigationItem::make('Pending Review')
                    ->label('Pending Review')
                    ->url(fn () => TemperatureHumidityResource::getUrl('reviewed'))
                    ->group('Temperature & Humidity')
                    ->isActiveWhen(fn () => request()->routeIs('filament.dashboard.resources.temperature-humidities.reviewed'))
                    ->visible(fn () => auth()->user()->hasRole(['Super Admin', 'Supply Chain Manager', 'QA Manager']))
                    ->sort(1),
                NavigationItem::make('Pending Acknowledgement')
                    ->label('Pending Acknowledgement')
                    ->url(fn () => TemperatureHumidityResource::getUrl('acknowledged'))
                    ->group('Temperature & Humidity')
                    ->isActiveWhen(fn () => request()->routeIs('filament.dashboard.resources.temperature-humidities.acknowledged'))
                    ->visible(fn () => auth()->user()->hasRole(['Super Admin', 'Supply Chain Manager', 'QA Manager']))
                    ->sort(2),

                // Temperature Deviation
                NavigationItem::make('All')
                    ->label('All')
                    ->url(fn () => TemperatureDeviationResource::getUrl('index'))
                    ->group('Temperature Deviation')
                    ->isActiveWhen(fn () => request()->routeIs('filament.dashboard.resources.temperature-deviations.index'))
                    ->sort(1),
                NavigationItem::make('Pending Review')
                    ->label('Pending Review')
                    ->url(fn () => TemperatureDeviationResource::getUrl('reviewed'))
                    ->group('Temperature Deviation')
                    ->isActiveWhen(fn () => request()->routeIs('filament.dashboard.resources.temperature-deviations.reviewed'))
                    ->visible(fn () => auth()->user()->hasRole(['Super Admin', 'Supply Chain Manager', 'QA Manager']))
                    ->sort(1),
                NavigationItem::make('Pending Acknowledgement')
                    ->label('Pending Acknowledgement')
                    ->url(fn () => TemperatureDeviationResource::getUrl('acknowledged'))
                    ->group('Temperature Deviation')
                    ->isActiveWhen(fn () => request()->routeIs('filament.dashboard.resources.temperature-deviations.acknowledged'))
                    ->visible(fn () => auth()->user()->hasRole(['Super Admin', 'Supply Chain Manager', 'QA Manager']))
                    ->sort(2),
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Temperature & Humidity')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Temperature Deviation')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Location Management')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Master Data')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Admin Settings')
                    ->collapsed(true),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
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
                CheckPasswordChangeRequired::class,
            ]);
    }
}
