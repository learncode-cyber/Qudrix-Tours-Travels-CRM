<?php
namespace App\Http\Middleware;

use App\Models\Webhook;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * FIX (2026-09-22, cross-tenant IDOR): WebhookMonitoringController and
 * WebhookAnalyticsDashboardController both take `Webhook $webhook` via
 * route-model binding across ~20 methods combined, with zero tenant check
 * anywhere in either file — any authenticated user could pull health/
 * audit/compliance/analytics data for another tenant's webhook just by
 * guessing the ID. Rather than repeat the same check in every method (as
 * was done explicitly in AdminWebhookController, which has its own
 * dedicated fix), this middleware enforces it once for any route that
 * binds a `webhook` route parameter, run after route-model binding so
 * $request->route('webhook') is already resolved. 404s rather than 403s,
 * to avoid confirming the ID exists at all.
 */
class EnsureWebhookBelongsToTenant
{
    public function handle(Request $request, Closure $next)
    {
        $webhook = $request->route('webhook');

        if ($webhook instanceof Webhook) {
            $user = $request->user ?? auth()->user();
            if (!$user || $webhook->tenant_id !== $user->tenant_id) {
                throw new NotFoundHttpException();
            }
        }

        return $next($request);
    }
}
