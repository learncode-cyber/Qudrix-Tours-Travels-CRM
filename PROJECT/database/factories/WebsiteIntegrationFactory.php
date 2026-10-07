<?php

namespace Database\Factories;

use App\Models\WebsiteIntegration;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

class WebsiteIntegrationFactory extends Factory
{
    protected $model = WebsiteIntegration::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->company() . ' Website',
            'website_url' => $this->faker->url(),
            'crm_base_url' => 'https://crm.example.com/api/v1',
            'description' => $this->faker->sentence(),
            'crm_api_key' => Crypt::encryptString('qd_' . $this->faker->sha256()),
            'crm_api_secret' => Crypt::encryptString('sk_' . $this->faker->sha256()),
            'webhook_secret' => Crypt::encryptString($this->faker->sha256()),
            'webhook_url' => null,
            'status' => 'pending',
            'is_active' => true,
            'integration_type' => 'website',
            'sync_settings' => [
                'auto_sync' => true,
                'sync_interval_minutes' => 15,
                'entities' => ['leads', 'bookings', 'customers'],
            ],
            'last_connection_test_at' => null,
            'last_connection_status' => null,
            'last_sync_at' => null,
            'last_sync_error' => null,
        ];
    }

    public function connected(): self
    {
        return $this->state([
            'status' => 'connected',
            'last_connection_status' => 'success',
            'last_connection_test_at' => now(),
        ]);
    }

    public function withError(): self
    {
        return $this->state([
            'status' => 'error',
            'last_connection_status' => 'failed',
            'last_sync_error' => 'Connection timeout',
        ]);
    }

    public function inactive(): self
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}
