<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_has_action_constants()
    {
        $this->assertEquals('create', AuditLog::ACTION_CREATE);
        $this->assertEquals('update', AuditLog::ACTION_UPDATE);
        $this->assertEquals('delete', AuditLog::ACTION_DELETE);
        $this->assertEquals('login', AuditLog::ACTION_LOGIN);
        $this->assertEquals('logout', AuditLog::ACTION_LOGOUT);
        $this->assertEquals('password_reset', AuditLog::ACTION_PASSWORD_RESET);
        $this->assertEquals('2fa_enable', AuditLog::ACTION_2FA_ENABLE);
        $this->assertEquals('2fa_disable', AuditLog::ACTION_2FA_DISABLE);
        $this->assertEquals('license_validate', AuditLog::ACTION_LICENSE_VALIDATE);
        $this->assertEquals('license_suspend', AuditLog::ACTION_LICENSE_SUSPEND);
        $this->assertEquals('license_reset', AuditLog::ACTION_LICENSE_RESET);
        $this->assertEquals('backup_create', AuditLog::ACTION_BACKUP_CREATE);
        $this->assertEquals('backup_restore', AuditLog::ACTION_BACKUP_RESTORE);
        $this->assertEquals('settings_update', AuditLog::ACTION_SETTINGS_UPDATE);
    }

    public function test_audit_log_belongs_to_user()
    {
        $user = User::factory()->create();
        $auditLog = AuditLog::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $auditLog->user);
        $this->assertEquals($user->id, $auditLog->user->id);
    }

    public function test_audit_log_has_polymorphic_auditable_relationship()
    {
        $user = User::factory()->create();
        $auditLog = AuditLog::factory()->create([
            'auditable_type' => User::class,
            'auditable_id' => $user->id
        ]);

        $this->assertInstanceOf(User::class, $auditLog->auditable);
        $this->assertEquals($user->id, $auditLog->auditable->id);
    }

    public function test_audit_log_formats_action_descriptions()
    {
        $createLog = AuditLog::factory()->create(['action' => AuditLog::ACTION_CREATE]);
        $updateLog = AuditLog::factory()->create(['action' => AuditLog::ACTION_UPDATE]);
        $customLog = AuditLog::factory()->create(['action' => 'custom_action']);

        $this->assertEquals('Created', $createLog->getActionDescription());
        $this->assertEquals('Updated', $updateLog->getActionDescription());
        $this->assertEquals('Custom action', $customLog->getActionDescription());
    }

    public function test_audit_log_gets_model_name()
    {
        $userLog = AuditLog::factory()->create(['auditable_type' => User::class]);
        $systemLog = AuditLog::factory()->create(['auditable_type' => null]);

        $this->assertEquals('User', $userLog->getModelName());
        $this->assertEquals('System', $systemLog->getModelName());
    }

    public function test_audit_log_gets_changes_summary()
    {
        $auditLog = AuditLog::factory()->create([
            'old_values' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'role' => 'user'
            ],
            'new_values' => [
                'name' => 'John Smith',
                'email' => 'john@example.com',
                'role' => 'admin'
            ]
        ]);

        $changes = $auditLog->getChangesSummary();

        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayHasKey('role', $changes);
        $this->assertArrayNotHasKey('email', $changes); // No change

        $this->assertEquals('John Doe', $changes['name']['old']);
        $this->assertEquals('John Smith', $changes['name']['new']);
        $this->assertEquals('user', $changes['role']['old']);
        $this->assertEquals('admin', $changes['role']['new']);
    }

    public function test_audit_log_identifies_sensitive_actions()
    {
        $sensitiveLog = AuditLog::factory()->create(['action' => AuditLog::ACTION_PASSWORD_RESET]);
        $normalLog = AuditLog::factory()->create(['action' => AuditLog::ACTION_LOGIN]);

        $this->assertTrue($sensitiveLog->isSensitiveAction());
        $this->assertFalse($normalLog->isSensitiveAction());
    }

    public function test_audit_log_scopes()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $loginLog = AuditLog::factory()->create([
            'action' => AuditLog::ACTION_LOGIN,
            'user_id' => $user1->id
        ]);

        $updateLog = AuditLog::factory()->create([
            'action' => AuditLog::ACTION_UPDATE,
            'user_id' => $user2->id,
            'auditable_type' => User::class
        ]);

        $sensitiveLog = AuditLog::factory()->create([
            'action' => AuditLog::ACTION_PASSWORD_RESET,
            'user_id' => $user1->id
        ]);

        $recentLog = AuditLog::factory()->create([
            'created_at' => now()->subDays(15)
        ]);

        $oldLog = AuditLog::factory()->create([
            'created_at' => now()->subDays(45)
        ]);

        // Test action scope
        $loginLogs = AuditLog::action(AuditLog::ACTION_LOGIN)->get();
        $this->assertTrue($loginLogs->contains($loginLog));
        $this->assertFalse($loginLogs->contains($updateLog));

        // Test by user scope
        $user1Logs = AuditLog::byUser($user1->id)->get();
        $this->assertTrue($user1Logs->contains($loginLog));
        $this->assertTrue($user1Logs->contains($sensitiveLog));
        $this->assertFalse($user1Logs->contains($updateLog));

        // Test for model scope
        $userModelLogs = AuditLog::forModel(User::class)->get();
        $this->assertTrue($userModelLogs->contains($updateLog));
        $this->assertFalse($userModelLogs->contains($loginLog));

        // Test sensitive scope
        $sensitiveLogs = AuditLog::sensitive()->get();
        $this->assertTrue($sensitiveLogs->contains($sensitiveLog));
        $this->assertFalse($sensitiveLogs->contains($loginLog));

        // Test recent scope
        $recentLogs = AuditLog::recent(30)->get();
        $this->assertTrue($recentLogs->contains($recentLog));
        $this->assertFalse($recentLogs->contains($oldLog));
    }

    public function test_audit_log_can_create_log_entry()
    {
        $user = User::factory()->create();
        $auditable = User::factory()->create();

        $oldValues = ['name' => 'Old Name'];
        $newValues = ['name' => 'New Name'];
        $metadata = ['ip' => '127.0.0.1'];

        $auditLog = AuditLog::log(
            AuditLog::ACTION_UPDATE,
            $user,
            $auditable,
            $oldValues,
            $newValues,
            $metadata
        );

        $this->assertEquals(AuditLog::ACTION_UPDATE, $auditLog->action);
        $this->assertEquals($user->id, $auditLog->user_id);
        $this->assertEquals(User::class, $auditLog->auditable_type);
        $this->assertEquals($auditable->id, $auditLog->auditable_id);
        $this->assertEquals($oldValues, $auditLog->old_values);
        $this->assertEquals($newValues, $auditLog->new_values);
        $this->assertEquals($metadata, $auditLog->metadata);
    }

    public function test_audit_log_casts_attributes_correctly()
    {
        $auditLog = AuditLog::factory()->create([
            'old_values' => ['key' => 'old_value'],
            'new_values' => ['key' => 'new_value'],
            'metadata' => ['ip' => '127.0.0.1']
        ]);

        $this->assertIsArray($auditLog->old_values);
        $this->assertIsArray($auditLog->new_values);
        $this->assertIsArray($auditLog->metadata);
        $this->assertEquals(['key' => 'old_value'], $auditLog->old_values);
        $this->assertEquals(['key' => 'new_value'], $auditLog->new_values);
        $this->assertEquals(['ip' => '127.0.0.1'], $auditLog->metadata);
    }
}