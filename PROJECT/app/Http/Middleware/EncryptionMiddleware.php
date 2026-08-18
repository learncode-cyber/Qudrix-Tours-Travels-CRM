<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class EncryptionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            if ($request->header('Content-Type') === 'application/json') {
                $data = $request->json()->all();
                // Validate and sanitize sensitive fields
                if (isset($data['password'])) {
                    unset($data['password']);
                }
                if (isset($data['api_key'])) {
                    unset($data['api_key']);
                }
            }
        }
        
        return $next($request);
    }
}
