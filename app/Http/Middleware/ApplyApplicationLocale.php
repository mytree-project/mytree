<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Settings\Application\ApplicationSettingsProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyApplicationLocale
{
    public function __construct(
        private ApplicationSettingsProvider $settings,
    ) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredLocale = strtolower($this->settings->current()->defaultLocale);
        $interfaceLocale = str_starts_with($configuredLocale, 'pl') ? 'pl' : 'en';

        app()->setLocale($interfaceLocale);

        return $next($request);
    }
}
