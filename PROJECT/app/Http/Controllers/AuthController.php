<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'tenant_name' => 'required|string|unique:tenants,name',
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $tenant = Tenant::create([
            'name' => $validated['tenant_name'],
            'slug' => Str::slug($validated['tenant_name']),
            'is_active' => true,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
            'status' => 'active',
        ]);

        // FIXED: this looked up $tenant->roles() (tenant-scoped roles) for a
        // role named 'super-admin' (hyphen). Neither matched reality: the
        // system roles this app actually seeds (RoleSeeder) are global
        // (tenant_id null) and named 'super_admin' (underscore) — so this
        // lookup always returned null and every self-registered tenant's
        // first user silently got zero roles, permanently unable to pass
        // any role-based check. Mirrors the same lookup DatabaseSeeder uses
        // for the seeded admin.
        $superAdminRole = Role::whereNull('tenant_id')->where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $user->roles()->attach($superAdminRole->id, ['tenant_id' => $tenant->id]);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'tenant' => $tenant,
            'token' => $token
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $credentials['email'];
        $ip = $request->ip();

        $maxAttempts = (int) config('security.login.max_failed_attempts', 5);
        $lockoutMinutes = (int) config('security.login.lockout_minutes', 15);

        // Blunt credential stuffing. The lockout key is email + source IP,
        // so an attacker cannot lock a real user out globally just by
        // hammering their address from somewhere else.
        if (\App\Models\FailedLoginAttempt::recentCount($email, $ip, $lockoutMinutes) >= $maxAttempts) {
            $this->recordFailedLogin($request, $email, 'locked_out');

            return response()->json([
                'message' => "Too many failed attempts. Try again in {$lockoutMinutes} minutes.",
            ], 429);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            // The reason is recorded for defenders but NOT returned to the
            // client — the response stays identical either way, so the
            // endpoint cannot be used to enumerate valid addresses.
            $this->recordFailedLogin($request, $email, $user ? 'bad_password' : 'unknown_email');

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        if (!$user->isActive()) {
            $this->recordFailedLogin($request, $email, 'inactive_account');

            throw ValidationException::withMessages([
                'email' => ['User account is inactive'],
            ]);
        }

        // A successful login clears the failure streak for this pair.
        \App\Models\FailedLoginAttempt::where('email', $email)
            ->when($ip, fn ($q) => $q->where('ip_address', $ip))
            ->delete();

        $user->forceFill(['last_login_at' => now()])->save();

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    }

    private function recordFailedLogin(Request $request, string $email, string $reason): void
    {
        \App\Models\FailedLoginAttempt::create([
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => mb_strcut((string) $request->userAgent(), 0, 1000),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function profile()
    {
        $user = JWTAuth::parseToken()->authenticate();

        return response()->json([
            'user' => $user->load('roles', 'tenant'),
            'permissions' => $user->roles->pluck('permissions')->flatten()->unique()
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'phone' => 'nullable|string',
            'avatar_url' => 'nullable|url',
            'telegram_chat_id' => 'nullable|string',
        ]);
        $user->update($validated);
        return response()->json(['user' => $user]);
    }
}
