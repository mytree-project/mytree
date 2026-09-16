<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Acquisition\SourceEditor;
use App\Filament\Pages\Acquisition\Sources;
use App\Filament\Pages\Settings;
use App\Filament\Widgets\SystemStatusWidget;
use App\Http\Middleware\ApplyApplicationLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_END,
            fn (): View => view('filament.support.source-type-template-combobox'),
            scopes: Settings::class,
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('MyTree')
            ->login()
            ->pages([
                Dashboard::class,
                Sources::class,
                SourceEditor::class,
                Settings::class,
            ])
            ->widgets([
                SystemStatusWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                ApplyApplicationLocale::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
