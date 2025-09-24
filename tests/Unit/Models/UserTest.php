<?php

namespace Tests\Unit\Models;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\ChatMessage;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_role_constants()
    {
        $this->assertEquals('admin', Role::ADMIN->value);
        $this->assertEquals('developer', Role::DEVELOPER->value);
        $this->assertEquals('reseller', Role::RESELLER->value);
        $this->assertEquals('user', Role::USER->value);
    }

    public function test_user_can_check_roles()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        $this->assertTrue($admin->hasRole(Role::ADMIN));
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isDeveloper());

        $this->assertTrue($developer->isDeveloper());
        $this->assertFalse($developer->isAdmin());

        $this->assertTrue($reseller->isReseller());
        $this->assertFalse($reseller->isAdmin());

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());
        $this->assertFalse($user->isReseller());
    }

    public function test_user_requires_2fa_for_admin_and_developer()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        $this->assertTrue($admin->requires2FA());
        $this->assertTrue($developer->requires2FA());
        $this->assertFalse($reseller->requires2FA());
        $this->assertFalse($user->requires2FA());
    }

    public function test_user_can_manage_2fa()
    {
        $user = User::factory()->create(['2fa_enabled' => false]);
        $secret = 'test-secret-key';

        $this->assertFalse($user->{'2fa_enabled'});
        $this->assertNull($user->get2FASecret());

        $user->enable2FA($secret);
        $user->refresh();

        $this->assertTrue($user->{'2fa_enabled'});
        $this->assertEquals($secret, $user->get2FASecret());

        $user->disable2FA();
        $user->refresh();

        $this->assertFalse($user->{'2fa_enabled'});
        $this->assertNull($user->get2FASecret());
    }

    public function test_user_has_reseller_relationship()
    {
        $reseller = User::factory()->create(['role' => User::ROLE_RESELLER]);
        $user = User::factory()->create(['reseller_id' => $reseller->id]);

        $this->assertInstanceOf(User::class, $user->reseller);
        $this->assertEquals($reseller->id, $user->reseller->id);
    }

    public function test_reseller_has_managed_users_relationship()
    {
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user1 = User::factory()->create(['reseller_id' => $reseller->id]);
        $user2 = User::factory()->create(['reseller_id' => $reseller->id]);

        $managedUsers = $reseller->managedUsers;

        $this->assertCount(2, $managedUsers);
        $this->assertTrue($managedUsers->contains($user1));
        $this->assertTrue($managedUsers->contains($user2));
    }

    public function test_user_has_license_relationships()
    {
        $owner = User::factory()->create();
        $assignee = User::factory()->create();
        
        $ownedLicense = License::factory()->create(['owner_id' => $owner->id]);
        $assignedLicense = License::factory()->create(['user_id' => $assignee->id]);

        $this->assertCount(1, $owner->ownedLicenses);
        $this->assertEquals($ownedLicense->id, $owner->ownedLicenses->first()->id);

        $this->assertCount(1, $assignee->assignedLicenses);
        $this->assertEquals($assignedLicense->id, $assignee->assignedLicenses->first()->id);
    }

    public function test_user_has_chat_message_relationships()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        
        $sentMessage = ChatMessage::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id
        ]);

        $this->assertCount(1, $sender->sentMessages);
        $this->assertEquals($sentMessage->id, $sender->sentMessages->first()->id);

        $this->assertCount(1, $receiver->receivedMessages);
        $this->assertEquals($sentMessage->id, $receiver->receivedMessages->first()->id);
    }

    public function test_user_has_audit_log_relationship()
    {
        $user = User::factory()->create();
        $auditLog = AuditLog::factory()->create(['user_id' => $user->id]);

        $this->assertCount(1, $user->auditLogs);
        $this->assertEquals($auditLog->id, $user->auditLogs->first()->id);
    }

    public function test_user_has_backup_relationship()
    {
        $user = User::factory()->create();
        $backup = Backup::factory()->create(['created_by' => $user->id]);

        $this->assertCount(1, $user->backups);
        $this->assertEquals($backup->id, $user->backups->first()->id);
    }

    public function test_user_casts_attributes_correctly()
    {
        $user = User::factory()->create([
            'privacy_policy_accepted_at' => '2023-01-01 12:00:00',
            '2fa_enabled' => 1
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $user->privacy_policy_accepted_at);
        $this->assertIsBool($user->{'2fa_enabled'});
        $this->assertTrue($user->{'2fa_enabled'});
    }

    public function test_user_hides_sensitive_attributes()
    {
        $user = User::factory()->create([
            'password' => 'secret',
            '2fa_secret' => encrypt('secret-key')
        ]);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayNotHasKey('2fa_secret', $array);
    }
}