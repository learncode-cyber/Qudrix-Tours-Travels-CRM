<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth as JWTAuthFacade;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtAuth
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if (!$token = JWTAuthFacade::getToken()) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            if (!$user = JWTAuthFacade::authenticate($token)) {
                return response()->json(['error' => 'User not found'], 401);
            }

            if (!$user->isActive()) {
                return response()->json(['error' => 'User account is inactive'], 403);
            }

            $request->user = $user;

            // Also register the authenticated user with Laravel's own Auth
            // facade (default 'web' guard, in-memory only — never
            // Auth::login(), so nothing is persisted to a session). Without
            // this, Gate::authorize()/$this->authorize() in controllers
            // always sees a guest, since it resolves the user from Auth::
            // user() rather than this request's dynamic ->user property —
            // every gate-protected admin endpoint was silently denying
            // every request, including from real admins.
            Auth::setUser($user);

        } catch (JWTException $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
