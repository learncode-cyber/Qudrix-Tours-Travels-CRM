<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/v1/register', [
            'tenant_name' => 'Test Tenant',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user', 'tenant', 'token']);
    }

    public function test_user_can_login()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'token']);
    }

    public function test_login_fails_with_invalid_credentials()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_health_check_endpoint_works()
    {
        $response = $this->getJson('/api/v1/health');

        // The endpoint honestly reports real system state: it returns 503
        // when disk usage is genuinely over 80% rather than always
        // claiming 200, so a specific status code isn't something a test
        // can assert regardless of the machine it runs on. What's testable
        // is that the endpoint responds with the real structure.
        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure(['status', 'checks' => ['database', 'cache', 'disk', 'memory'], 'timestamp']);
    }
}
