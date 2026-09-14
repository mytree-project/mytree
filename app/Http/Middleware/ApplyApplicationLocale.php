<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Settings\Application\ApplicationSettingsProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyApplicationLocale
{
    public function __construct(
        private ApplicationSettingsProvider $settings,
    ) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredLocale = Schema::hasTable('application_settings')
            ? strtolower($this->settings->current()->defaultLocale)
            : strtolower((string) config('app.locale', 'en'));
        $interfaceLocale = str_starts_with($configuredLocale, 'pl') ? 'pl' : 'en';

        app()->setLocale($interfaceLocale);

        return $next($request);
    }
}
