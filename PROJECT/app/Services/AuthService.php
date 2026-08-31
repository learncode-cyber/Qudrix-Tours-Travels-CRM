<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function register(array $data)
    {
        $tenant = Tenant::create([
            'name' => $data['tenant_name'],
            'slug' => Str::slug($data['tenant_name']),
            'is_active' => true,
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);

        $this->seedSystemRoles($tenant);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'status' => 'active',
        ]);

        $superAdminRole = $tenant->roles()->where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $user->roles()->attach($superAdminRole);
        }

        return ['user' => $user, 'tenant' => $tenant];
    }

    public function login(array $credentials)
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw new \Exception('Invalid credentials');
        }

        if (!$user->isActive()) {
            throw new \Exception('User account is inactive');
        }

        return JWTAuth::fromUser($user);
    }

    private function seedSystemRoles(Tenant $tenant)
    {
        $roles = [
            ['name' => 'super-admin', 'display_name' => 'Super Admin', 'description' => 'Full system access', 'is_system' => true, 'permissions' => json_encode(['*'])],
            ['name' => 'admin', 'display_name' => 'Admin', 'description' => 'Administrator access', 'is_system' => true, 'permissions' => json_encode(['*'])],
            ['name' => 'manager', 'display_name' => 'Manager', 'description' => 'Management access', 'is_system' => true, 'permissions' => json_encode(['read', 'create', 'update'])],
            ['name' => 'agent', 'display_name' => 'Agent', 'description' => 'Agent access', 'is_system' => true, 'permissions' => json_encode(['read', 'create'])],
            ['name' => 'customer', 'display_name' => 'Customer', 'description' => 'Customer access', 'is_system' => true, 'permissions' => json_encode(['read:own'])],
            ['name' => 'support', 'display_name' => 'Support', 'description' => 'Support access', 'is_system' => true, 'permissions' => json_encode(['read', 'update'])],
            ['name' => 'finance', 'display_name' => 'Finance', 'description' => 'Finance access', 'is_system' => true, 'permissions' => json_encode(['read:finance', 'create:finance', 'update:finance'])],
            ['name' => 'compliance', 'display_name' => 'Compliance', 'description' => 'Compliance access', 'is_system' => true, 'permissions' => json_encode(['read:audit', 'read:logs'])],
        ];

        foreach ($roles as $roleData) {
            $roleData['tenant_id'] = $tenant->id;
            Role::create($roleData);
        }
    }
}
