<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Communication;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class Phase1Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_can_create_customer()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/customers', [
                'name' => 'Test Customer',
                'email' => 'customer@example.com',
                'phone' => '+1234567890',
                'customer_type' => 'individual',
                'country' => 'USA',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_create_lead()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/leads', [
                'name' => 'Test Lead',
                'email' => 'lead@example.com',
                'phone' => '+1234567890',
                'company' => 'Test Company',
                'source' => 'website',
                'priority' => 'high',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_create_communication()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'email' => 'cust@example.com',
            'phone' => '1234567890',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/communications', [
                'customer_id' => $customer->id,
                'type' => 'email',
                'subject' => 'Test Email',
                'message' => 'This is a test communication',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_create_task()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/tasks', [
                'title' => 'Follow up with customer',
                'type' => 'followup',
                'priority' => 'high',
                'due_date' => now()->addDays(1)->toDateString(),
                'assigned_to' => $this->user->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_mark_task_complete()
    {
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Test Task',
            'type' => 'task',
            'status' => 'open',
            'priority' => 'medium',
            'due_date' => now(),
            'assigned_to' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/tasks/{$task->id}/complete");

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_list_customers()
    {
        Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer 1',
            'email' => 'cust1@example.com',
            'phone' => '1234567890',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/customers');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'pagination']);
    }

    public function test_can_get_task_stats()
    {
        Task::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Task 1',
            'type' => 'task',
            'status' => 'open',
            'priority' => 'high',
            'due_date' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/tasks/stats');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }
}
