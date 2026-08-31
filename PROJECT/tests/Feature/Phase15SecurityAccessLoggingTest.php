<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\AuditLog;
use App\Models\FailedLoginAttempt;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase15SecurityAccessLoggingTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $otherTenant;
    private $superAdminRole;
    private $adminUser;
    private $adminToken;
    private $plainUser;
    private $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Security Tenant',
            'slug' => 'security-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant P15',
            'slug' => 'other-tenant-p15',
            'is_active' => true,
        ]);

        $this->superAdminRole = Role::create([
            'tenant_id' => null,
            'name' => 'super_admin',
            'display_name' => 'Super Administrator',
            'is_system' => true,
            'permissions' => ['*'],
        ]);

        $this->adminUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@security-test.local',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->superAdminRole->id, ['tenant_id' => $this->tenant->id]);
        $this->adminToken = JWTAuth::fromUser($this->adminUser);

        $this->plainUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plain User',
            'email' => 'plain@security-test.local',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);
        $this->plainToken = JWTAuth::fromUser($this->plainUser);
    }

    private function asAdmin()
    {
        return $this->withHeader('Authorization', "Bearer {$this->adminToken}");
    }

    private function asPlain()
    {
        return $this->withHeader('Authorization', "Bearer {$this->plainToken}");
    }

    // --- Admin gate wiring ---

    public function test_admin_gated_endpoint_is_accessible_to_a_real_admin()
    {
        $this->asAdmin()->getJson('/api/v1/admin/backups')->assertOk();
    }

    public function test_admin_gated_endpoint_is_denied_to_a_non_admin()
    {
        $this->asPlain()->getJson('/api/v1/admin/backups')->assertStatus(403);
    }

    // --- Security log endpoints require the admin gate ---

    public function test_security_summary_is_denied_to_a_non_admin_user()
    {
        $this->asPlain()->getJson('/api/v1/admin/security/summary')->assertStatus(403);
    }

    public function test_security_access_logs_are_denied_to_a_non_admin_user()
    {
        $this->asPlain()->getJson('/api/v1/admin/security/access-logs')->assertStatus(403);
    }

    public function test_security_audit_logs_are_denied_to_a_non_admin_user()
    {
        $this->asPlain()->getJson('/api/v1/admin/security/audit-logs')->assertStatus(403);
    }

    public function test_security_failed_logins_are_denied_to_a_non_admin_user()
    {
        $this->asPlain()->getJson('/api/v1/admin/security/failed-logins')->assertStatus(403);
    }

    public function test_security_endpoints_are_accessible_to_a_real_admin_and_return_real_data()
    {
        AccessLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'method' => 'GET',
            'url' => 'http://test/api/v1/customers',
            'status_code' => 200,
            'duration_ms' => 12,
            'is_suspicious' => false,
            'created_at' => now(),
        ]);
        AccessLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'method' => 'GET',
            'url' => 'http://test/api/v1/secret',
            'status_code' => 403,
            'duration_ms' => 5,
            'is_suspicious' => true,
            'suspicion_reason' => 'Forbidden — authenticated but not permitted',
            'created_at' => now(),
        ]);

        $logs = $this->asAdmin()->getJson('/api/v1/admin/security/access-logs');
        $logs->assertOk();
        $this->assertCount(2, $logs->json('data'));

        $suspiciousOnly = $this->asAdmin()->getJson('/api/v1/admin/security/access-logs?suspicious_only=1');
        $suspiciousOnly->assertOk();
        $this->assertCount(1, $suspiciousOnly->json('data'));

        $summary = $this->asAdmin()->getJson('/api/v1/admin/security/summary');
        $summary->assertOk();
        // >= rather than exact: AccessLogMiddleware itself logs every one of
        // this test's own HTTP calls (including the /access-logs calls
        // above), so the real count is the 2 seeded rows plus that traffic.
        $this->assertGreaterThanOrEqual(2, $summary->json('data.total_requests'));
        $this->assertGreaterThanOrEqual(1, $summary->json('data.suspicious_requests'));
    }

    public function test_access_logs_are_tenant_scoped_even_for_an_admin()
    {
        AccessLog::create([
            'tenant_id' => $this->otherTenant->id,
            'user_id' => null,
            'method' => 'GET',
            'url' => 'http://test/api/v1/other',
            'status_code' => 200,
            'duration_ms' => 5,
            'is_suspicious' => false,
            'created_at' => now(),
        ]);

        $logs = $this->asAdmin()->getJson('/api/v1/admin/security/access-logs');
        $logs->assertOk();
        $this->assertCount(0, $logs->json('data'));
    }

    public function test_audit_logs_endpoint_filters_by_entity_type()
    {
        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'action' => 'POST',
            'entity_type' => 'customers',
            'entity_id' => 5,
            'description' => 'POST customers',
            'created_at' => now(),
        ]);
        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'action' => 'PUT',
            'entity_type' => 'leads',
            'entity_id' => 9,
            'description' => 'PUT leads/9',
            'created_at' => now(),
        ]);

        $filtered = $this->asAdmin()->getJson('/api/v1/admin/security/audit-logs?entity_type=customers');
        $filtered->assertOk();
        $this->assertCount(1, $filtered->json('data'));
        $this->assertSame('customers', $filtered->json('data.0.entity_type'));
    }

    public function test_failed_logins_endpoint_is_not_tenant_scoped_but_is_admin_gated()
    {
        FailedLoginAttempt::create([
            'email' => 'attacker@example.com',
            'ip_address' => '10.0.0.5',
            'reason' => 'unknown_email',
            'created_at' => now(),
        ]);

        $this->asAdmin()->getJson('/api/v1/admin/security/failed-logins')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // --- Self-registration role assignment (bug fix) ---

    public function test_self_registration_grants_the_new_admin_the_super_admin_role()
    {
        // The global system role must exist first, exactly as RoleSeeder
        // provisions it in production (tenant_id null, name super_admin).
        $response = $this->postJson('/api/v1/register', [
            'tenant_name' => 'Freshly Registered Co',
            'name' => 'New Admin',
            'email' => 'newadmin@freshco.local',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(201);
        $userId = $response->json('user.id');

        $user = User::find($userId);
        $this->assertTrue($user->hasRole('super_admin'));

        // And the gate actually opens for them.
        $token = JWTAuth::fromUser($user);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/backups')
            ->assertOk();
    }

    // --- Login lockout + failed-login recording ---

    public function test_repeated_bad_password_attempts_lock_out_and_are_recorded()
    {
        config(['security.login.max_failed_attempts' => 3, 'security.login.lockout_minutes' => 15]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $this->plainUser->email,
                'password' => 'WrongPassword!',
            ])->assertStatus(422);
        }

        // The 4th attempt (even with the CORRECT password) is locked out.
        $locked = $this->postJson('/api/v1/login', [
            'email' => $this->plainUser->email,
            'password' => 'Password@123',
        ]);
        $locked->assertStatus(429);

        $this->assertGreaterThanOrEqual(3, FailedLoginAttempt::where('email', $this->plainUser->email)->count());
    }

    public function test_a_successful_login_clears_the_failure_streak()
    {
        config(['security.login.max_failed_attempts' => 5, 'security.login.lockout_minutes' => 15]);

        $this->postJson('/api/v1/login', [
            'email' => $this->plainUser->email,
            'password' => 'WrongPassword!',
        ])->assertStatus(422);

        $this->postJson('/api/v1/login', [
            'email' => $this->plainUser->email,
            'password' => 'Password@123',
        ])->assertOk();

        $this->assertSame(0, FailedLoginAttempt::where('email', $this->plainUser->email)->count());
    }

    public function test_login_error_message_does_not_reveal_whether_the_email_exists()
    {
        $unknown = $this->postJson('/api/v1/login', [
            'email' => 'nobody-at-all@nowhere.local',
            'password' => 'WrongPassword!',
        ]);
        $wrongPassword = $this->postJson('/api/v1/login', [
            'email' => $this->plainUser->email,
            'password' => 'WrongPassword!',
        ]);

        $unknown->assertStatus(422);
        $wrongPassword->assertStatus(422);
        $this->assertSame($unknown->json('errors.email.0'), $wrongPassword->json('errors.email.0'));
    }

    // --- Audit + Access log middleware ---

    public function test_a_write_request_creates_a_real_audit_log_entry()
    {
        $this->asAdmin()->postJson('/api/v1/notes', [
            'notable_type' => 'customer',
            'notable_id' => 1,
            'body' => 'Audit trail smoke test note',
        ]);

        $this->assertGreaterThanOrEqual(1, AuditLog::where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->adminUser->id)
            ->where('action', 'POST')
            ->count());
    }

    public function test_a_request_creates_a_real_access_log_entry()
    {
        $this->asAdmin()->getJson('/api/v1/support-tickets');

        $this->assertGreaterThanOrEqual(1, AccessLog::where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->adminUser->id)
            ->count());
    }

    public function test_a_forbidden_request_is_flagged_suspicious_in_the_access_log()
    {
        $this->asPlain()->getJson('/api/v1/admin/backups')->assertStatus(403);

        $log = AccessLog::where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->plainUser->id)
            ->where('status_code', 403)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertTrue((bool) $log->is_suspicious);
        $this->assertSame('Forbidden — authenticated but not permitted', $log->suspicion_reason);
    }
}
