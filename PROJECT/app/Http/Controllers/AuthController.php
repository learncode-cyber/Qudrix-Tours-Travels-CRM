<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'slug' => str_slug($validated['tenant_name']),
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

        $superAdminRole = $tenant->roles()->where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $user->roles()->attach($superAdminRole);
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

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        if (!$user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['User account is inactive'],
            ]);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
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
}
