<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class Phase2Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $token;
    private $lead;

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

        $this->lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Lead',
            'email' => 'lead@example.com',
            'phone' => '+1234567890',
            'source' => 'website',
            'status' => 'qualified',
            'priority' => 'high',
        ]);
    }

    public function test_can_create_quotation()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/quotations', [
                'lead_id' => $this->lead->id,
                'subject' => 'Hajj Package Quotation',
                'valid_until' => now()->addDays(30)->toDateString(),
                'currency' => 'USD',
                'items' => [
                    [
                        'description' => 'Hajj Package - 5 Star',
                        'quantity' => 1,
                        'unit_price' => 5000,
                        'tax_rate' => 10,
                    ]
                ]
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_create_proposal_from_quotation()
    {
        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $this->lead->id,
            'created_by' => $this->user->id,
            'quotation_number' => 'QT-001',
            'subject' => 'Test Quote',
            'status' => 'sent',
            'total_amount' => 5000,
            'valid_until' => now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/proposals/from-quotation', [
                'quotation_id' => $quotation->id,
                'title' => 'Formal Proposal',
                'expiry_date' => now()->addDays(30)->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_send_quotation()
    {
        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $this->lead->id,
            'created_by' => $this->user->id,
            'quotation_number' => 'QT-002',
            'subject' => 'Test Quote',
            'status' => 'draft',
            'total_amount' => 5000,
            'valid_until' => now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/quotations/{$quotation->id}/send");

        $response->assertStatus(200);
        $this->assertEquals('sent', $quotation->fresh()->status);
    }

    public function test_can_sign_proposal()
    {
        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $this->lead->id,
            'created_by' => $this->user->id,
            'quotation_number' => 'QT-003',
            'subject' => 'Test Quote',
            'status' => 'sent',
            'total_amount' => 5000,
            'valid_until' => now()->addDays(30),
        ]);

        $proposal = Proposal::create([
            'tenant_id' => $this->tenant->id,
            'quotation_id' => $quotation->id,
            'lead_id' => $this->lead->id,
            'proposal_number' => 'PROP-001',
            'status' => 'sent',
            'title' => 'Test Proposal',
            'proposal_date' => now(),
            'expiry_date' => now()->addDays(30),
            'sent_date' => now(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/proposals/{$proposal->id}/sign");

        $response->assertStatus(200);
        $this->assertEquals('signed', $proposal->fresh()->status);
        $this->assertEquals('won', $this->lead->fresh()->status);
    }

    public function test_can_get_pipeline()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/pipeline/full');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'pipeline_value']);
    }

    public function test_can_get_quotation_stats()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/quotations/stats');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_record_sales_activity()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/pipeline/activity', [
                'lead_id' => $this->lead->id,
                'activity_type' => 'call',
                'title' => 'Follow-up call',
                'outcome' => 'positive',
                'activity_date' => now()->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_update_lead_stage()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v1/pipeline/stage', [
                'lead_id' => $this->lead->id,
                'new_stage' => 'proposal',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('proposal', $this->lead->fresh()->status);
    }
}
