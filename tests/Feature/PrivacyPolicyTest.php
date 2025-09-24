<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_has_no_privacy_policy_acceptance()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => null]);

        $this->assertNull($user->privacy_policy_accepted_at);
    }

    public function test_privacy_policy_acceptance_is_tracked()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => null]);

        $response = $this->actingAs($user)->post(route('profile.privacy-policy.accept'));

        $response->assertJson(['success' => true]);
        
        $user->refresh();
        $this->assertNotNull($user->privacy_policy_accepted_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->privacy_policy_accepted_at);
    }

    public function test_privacy_policy_acceptance_creates_audit_log()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => null]);

        $this->actingAs($user)->post(route('profile.privacy-policy.accept'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'privacy_policy_accepted',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => $user->id,
        ]);

        $auditLog = AuditLog::where('user_id', $user->id)
            ->where('action', 'privacy_policy_accepted')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertArrayHasKey('privacy_policy_accepted_at', $auditLog->new_values);
    }

    public function test_privacy_policy_acceptance_via_profile_update()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => null]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'privacy_policy_accepted_at' => 'on',
        ]);

        $response->assertRedirect(route('profile.edit'));
        
        $user->refresh();
        $this->assertNotNull($user->privacy_policy_accepted_at);

        // Should create audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'privacy_policy_accepted',
        ]);
    }

    public function test_privacy_policy_not_updated_if_already_accepted()
    {
        $originalAcceptanceTime = now()->subDays(5);
        $user = User::factory()->create(['privacy_policy_accepted_at' => $originalAcceptanceTime]);

        $response = $this->actingAs($user)->post(route('profile.privacy-policy.accept'));

        $response->assertJson(['success' => true]);
        
        $user->refresh();
        $this->assertEquals($originalAcceptanceTime->toDateTimeString(), $user->privacy_policy_accepted_at->toDateTimeString());
    }

    public function test_privacy_policy_not_updated_via_profile_if_already_accepted()
    {
        $originalAcceptanceTime = now()->subDays(5);
        $user = User::factory()->create(['privacy_policy_accepted_at' => $originalAcceptanceTime]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'privacy_policy_accepted_at' => 'on',
        ]);

        $response->assertRedirect(route('profile.edit'));
        
        $user->refresh();
        $this->assertEquals($originalAcceptanceTime->toDateTimeString(), $user->privacy_policy_accepted_at->toDateTimeString());

        // Should not create duplicate audit log
        $auditLogs = AuditLog::where('user_id', $user->id)
            ->where('action', 'privacy_policy_accepted')
            ->count();
        
        $this->assertEquals(0, $auditLogs);
    }

    public function test_privacy_policy_modal_shown_for_users_without_acceptance()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => null]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('privacy_policy_accepted_at');
        $response->assertSee('Action Required');
        $response->assertSee('Please review and accept our privacy policy');
    }

    public function test_privacy_policy_section_not_shown_for_users_with_acceptance()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => now()]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertStatus(200);
        $response->assertDontSee('Action Required');
        $response->assertDontSee('Please review and accept our privacy policy');
    }

    public function test_guest_cannot_accept_privacy_policy()
    {
        $response = $this->post(route('profile.privacy-policy.accept'));

        $response->assertRedirect(route('login'));
    }
}