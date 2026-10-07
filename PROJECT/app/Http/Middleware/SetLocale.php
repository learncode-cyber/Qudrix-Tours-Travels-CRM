<?php

namespace App\Http\Middleware;

use App\Services\LocalizationService;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Priority: header > query param > user preference > default
        $locale = $request->header('Accept-Language')
            ?? $request->query('locale')
            ?? ($request->user()?->language ?? 'en');

        LocalizationService::setLocale($locale);

        return $next($request);
    }
}
