<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\ChatMessage;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GdprComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_export_personal_data()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => Role::USER,
            'privacy_policy_accepted_at' => now(),
            '2fa_enabled' => true,
        ]);

        // Create some related data
        $license = License::factory()->create(['owner_id' => $user->id]);
        $assignedLicense = License::factory()->create(['user_id' => $user->id]);
        
        $sentMessage = ChatMessage::factory()->create(['sender_id' => $user->id]);
        $receivedMessage = ChatMessage::factory()->create(['receiver_id' => $user->id]);
        
        $auditLog = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'test_action',
            'model_type' => 'User',
            'model_id' => $user->id,
            'changes' => ['test' => 'data'],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($user)->get(route('profile.export-data'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        
        $data = $response->json();

        // Check profile data
        $this->assertArrayHasKey('profile', $data);
        $this->assertEquals($user->id, $data['profile']['id']);
        $this->assertEquals('John Doe', $data['profile']['name']);
        $this->assertEquals('john@example.com', $data['profile']['email']);
        $this->assertEquals('user', $data['profile']['role']);
        $this->assertTrue($data['profile']['2fa_enabled']);

        // Check licenses data
        $this->assertArrayHasKey('licenses_owned', $data);
        $this->assertCount(1, $data['licenses_owned']);
        $this->assertEquals($license->id, $data['licenses_owned'][0]['id']);

        $this->assertArrayHasKey('licenses_assigned', $data);
        $this->assertCount(1, $data['licenses_assigned']);
        $this->assertEquals($assignedLicense->id, $data['licenses_assigned'][0]['id']);

        // Check chat messages
        $this->assertArrayHasKey('chat_messages_sent', $data);
        $this->assertCount(1, $data['chat_messages_sent']);
        $this->assertEquals($sentMessage->id, $data['chat_messages_sent'][0]['id']);

        $this->assertArrayHasKey('chat_messages_received', $data);
        $this->assertCount(1, $data['chat_messages_received']);
        $this->assertEquals($receivedMessage->id, $data['chat_messages_received'][0]['id']);

        // Check audit logs
        $this->assertArrayHasKey('audit_logs', $data);
        $this->assertGreaterThanOrEqual(1, count($data['audit_logs']));

        // Verify export is logged
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'data_exported',
        ]);
    }

    public function test_reseller_export_includes_managed_users_data()
    {
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $managedUser1 = User::factory()->create(['reseller_id' => $reseller->id]);
        $managedUser2 = User::factory()->create(['reseller_id' => $reseller->id]);

        $response = $this->actingAs($reseller)->get(route('profile.export-data'));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('managed_users', $data);
        $this->assertCount(2, $data['managed_users']);
        
        $managedUserIds = collect($data['managed_users'])->pluck('id')->toArray();
        $this->assertContains($managedUser1->id, $managedUserIds);
        $this->assertContains($managedUser2->id, $managedUserIds);
    }

    public function test_non_reseller_export_does_not_include_managed_users()
    {
        $user = User::factory()->create(['role' => Role::USER]);

        $response = $this->actingAs($user)->get(route('profile.export-data'));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayNotHasKey('managed_users', $data);
    }

    public function test_user_can_request_account_deletion()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.request-deletion'), [
            'confirmation' => 'DELETE',
            'reason' => 'No longer need the service',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Account deletion request has been submitted. You will receive an email confirmation within 24 hours.'
        ]);

        // Verify deletion request is logged
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'account_deletion_requested',
        ]);

        $auditLog = AuditLog::where('user_id', $user->id)
            ->where('action', 'account_deletion_requested')
            ->first();

        $this->assertEquals('No longer need the service', $auditLog->new_values['reason']);
        $this->assertArrayHasKey('requested_at', $auditLog->new_values);
    }

    public function test_account_deletion_request_requires_correct_confirmation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('profile.request-deletion'), [
                'confirmation' => 'WRONG',
                'reason' => 'Test reason',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['confirmation']);

        // Should not create audit log for invalid request
        $this->assertDatabaseMissing('audit_logs', [
            'user_id' => $user->id,
            'action' => 'account_deletion_requested',
        ]);
    }

    public function test_account_deletion_request_reason_is_optional()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.request-deletion'), [
            'confirmation' => 'DELETE',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'account_deletion_requested',
        ]);
    }

    public function test_user_can_anonymize_account()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'developer_notes' => 'Some developer notes',
            '2fa_enabled' => true,
            '2fa_secret' => encrypt('secret'),
            'oauth_providers' => ['google' => ['email' => 'john@gmail.com']],
        ]);

        // Create avatar file
        $avatarPath = 'avatars/test-avatar.jpg';
        Storage::disk('public')->put($avatarPath, 'fake avatar content');
        $user->update(['avatar' => $avatarPath]);

        $response = $this->actingAs($user)->post(route('profile.anonymize'), [
            'confirmation' => 'ANONYMIZE',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Your account has been anonymized successfully. You have been logged out.',
            'redirect' => route('login')
        ]);

        $user->refresh();

        // Check anonymized data
        $this->assertEquals('Anonymous User ' . $user->id, $user->name);
        $this->assertEquals('anonymized_' . $user->id . '@deleted.local', $user->email);
        $this->assertNull($user->avatar);
        $this->assertNull($user->developer_notes);
        $this->assertFalse($user->{'2fa_enabled'});
        $this->assertNull($user->{'2fa_secret'});
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertNull($user->oauth_providers);

        // Check avatar file was deleted
        Storage::disk('public')->assertMissing($avatarPath);

        // Verify anonymization is logged
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'account_anonymized',
        ]);

        $auditLog = AuditLog::where('user_id', $user->id)
            ->where('action', 'account_anonymized')
            ->first();

        $this->assertEquals('John Doe', $auditLog->new_values['original_name']);
        $this->assertEquals('john@example.com', $auditLog->new_values['original_email']);
        $this->assertArrayHasKey('anonymized_at', $auditLog->new_values);
    }

    public function test_account_anonymization_requires_correct_confirmation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('profile.anonymize'), [
                'confirmation' => 'WRONG',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['confirmation']);

        // User data should remain unchanged
        $user->refresh();
        $this->assertNotEquals('Anonymous User ' . $user->id, $user->name);
    }

    public function test_anonymization_handles_missing_avatar_gracefully()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'avatar' => null,
        ]);

        $response = $this->actingAs($user)->post(route('profile.anonymize'), [
            'confirmation' => 'ANONYMIZE',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $user->refresh();
        $this->assertEquals('Anonymous User ' . $user->id, $user->name);
    }

    public function test_anonymization_handles_nonexistent_avatar_file()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'avatar' => 'avatars/nonexistent.jpg',
        ]);

        $response = $this->actingAs($user)->post(route('profile.anonymize'), [
            'confirmation' => 'ANONYMIZE',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $user->refresh();
        $this->assertEquals('Anonymous User ' . $user->id, $user->name);
        $this->assertNull($user->avatar);
    }

    public function test_data_export_filename_includes_timestamp()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.export-data'));

        $response->assertStatus(200);
        
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('user_data_export_' . $user->id, $contentDisposition);
        $this->assertStringContainsString('.json', $contentDisposition);
    }

    public function test_sensitive_data_excluded_from_export()
    {
        $user = User::factory()->create([
            'password' => 'hashed_password',
            '2fa_secret' => encrypt('secret'),
        ]);

        $response = $this->actingAs($user)->get(route('profile.export-data'));

        $response->assertStatus(200);
        $data = $response->json();

        // Sensitive fields should not be included
        $this->assertArrayNotHasKey('password', $data['profile']);
        $this->assertArrayNotHasKey('2fa_secret', $data['profile']);
        $this->assertArrayNotHasKey('remember_token', $data['profile']);
    }

    public function test_guest_cannot_access_gdpr_endpoints()
    {
        $this->get(route('profile.export-data'))->assertRedirect(route('login'));
        $this->post(route('profile.request-deletion'))->assertRedirect(route('login'));
        $this->post(route('profile.anonymize'))->assertRedirect(route('login'));
    }

    public function test_data_export_includes_correct_timestamps()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.export-data'));

        $response->assertStatus(200);
        $data = $response->json();

        // Check that timestamps are in ISO format
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['profile']['created_at']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['profile']['updated_at']);
    }

    public function test_anonymization_preserves_user_id_for_referential_integrity()
    {
        $user = User::factory()->create();
        $originalId = $user->id;

        $this->actingAs($user)->post(route('profile.anonymize'), [
            'confirmation' => 'ANONYMIZE',
        ]);

        $user->refresh();
        $this->assertEquals($originalId, $user->id);
    }
}