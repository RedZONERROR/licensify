<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_view_profile_edit_page()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertStatus(200);
        $response->assertViewIs('profile.edit');
        $response->assertViewHas('user', $user);
    }

    public function test_user_can_update_basic_profile_information()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'privacy_policy_accepted_at' => now(), // User has already accepted privacy policy
        ]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success', 'Profile updated successfully!');

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@example.com', $user->email);

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'profile_updated',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_user_can_update_password()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => now()]);
        $oldPassword = $user->password;

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        
        $this->assertNotEquals($oldPassword, $user->password);

        // Check password change audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'profile_password_changed',
        ]);
    }

    public function test_user_can_upload_avatar()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => now()]);
        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $file,
        ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_user_can_delete_avatar()
    {
        $user = User::factory()->create();
        $avatarPath = 'avatars/test-avatar.jpg';
        Storage::disk('public')->put($avatarPath, 'fake content');
        $user->update(['avatar' => $avatarPath]);

        $response = $this->actingAs($user)->delete(route('profile.avatar.delete'));

        $response->assertJson(['success' => true]);
        $user->refresh();
        
        $this->assertNull($user->avatar);
        Storage::disk('public')->assertMissing($avatarPath);

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'avatar_deleted',
        ]);
    }

    public function test_user_can_accept_privacy_policy()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => null]);

        $response = $this->actingAs($user)->post(route('profile.privacy-policy.accept'));

        $response->assertJson(['success' => true]);
        $user->refresh();
        
        $this->assertNotNull($user->privacy_policy_accepted_at);

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'privacy_policy_accepted',
        ]);
    }

    public function test_privacy_policy_acceptance_is_tracked_in_profile_update()
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

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'privacy_policy_accepted',
        ]);
    }

    public function test_developer_can_update_developer_notes()
    {
        $user = User::factory()->create([
            'role' => Role::DEVELOPER,
            'privacy_policy_accepted_at' => now()
        ]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'developer_notes' => 'These are my developer notes',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        
        $this->assertEquals('These are my developer notes', $user->developer_notes);
    }

    public function test_non_developer_cannot_update_developer_notes()
    {
        $user = User::factory()->create([
            'role' => Role::USER,
            'privacy_policy_accepted_at' => now()
        ]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'developer_notes' => 'These should not be saved',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        
        $this->assertNull($user->developer_notes);
    }

    public function test_user_can_export_data()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.export-data'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('Content-Disposition');
        
        $data = $response->json();
        $this->assertArrayHasKey('profile', $data);
        $this->assertArrayHasKey('licenses_owned', $data);
        $this->assertArrayHasKey('licenses_assigned', $data);
        $this->assertArrayHasKey('chat_messages_sent', $data);
        $this->assertArrayHasKey('chat_messages_received', $data);
        $this->assertArrayHasKey('audit_logs', $data);

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'data_exported',
        ]);
    }

    public function test_reseller_export_includes_managed_users()
    {
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $managedUser = User::factory()->create(['reseller_id' => $reseller->id]);

        $response = $this->actingAs($reseller)->get(route('profile.export-data'));

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertArrayHasKey('managed_users', $data);
        $this->assertCount(1, $data['managed_users']);
        $this->assertEquals($managedUser->id, $data['managed_users'][0]['id']);
    }

    public function test_user_can_request_account_deletion()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.request-deletion'), [
            'confirmation' => 'DELETE',
            'reason' => 'No longer need the service',
        ]);

        $response->assertJson([
            'success' => true,
            'message' => 'Account deletion request has been submitted. You will receive an email confirmation within 24 hours.'
        ]);

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'account_deletion_requested',
        ]);
    }

    public function test_account_deletion_request_requires_confirmation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('profile.request-deletion'), [
                'confirmation' => 'WRONG',
                'reason' => 'No longer need the service',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['confirmation']);
    }

    public function test_user_can_anonymize_account()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'avatar' => 'avatars/test.jpg',
            'developer_notes' => 'Some notes',
            '2fa_enabled' => true,
        ]);

        // Create avatar file
        Storage::disk('public')->put('avatars/test.jpg', 'fake content');

        $response = $this->actingAs($user)->post(route('profile.anonymize'), [
            'confirmation' => 'ANONYMIZE',
        ]);

        $response->assertJson([
            'success' => true,
            'message' => 'Your account has been anonymized successfully. You have been logged out.',
            'redirect' => route('login')
        ]);

        $user->refresh();
        
        $this->assertEquals('Anonymous User ' . $user->id, $user->name);
        $this->assertEquals('anonymized_' . $user->id . '@deleted.local', $user->email);
        $this->assertNull($user->avatar);
        $this->assertNull($user->developer_notes);
        $this->assertFalse($user->{'2fa_enabled'});
        $this->assertNull($user->{'2fa_secret'});
        $this->assertNull($user->oauth_providers);

        // Check avatar file was deleted
        Storage::disk('public')->assertMissing('avatars/test.jpg');

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'account_anonymized',
        ]);
    }

    public function test_account_anonymization_requires_confirmation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('profile.anonymize'), [
                'confirmation' => 'WRONG',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['confirmation']);
    }

    public function test_profile_update_validation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_email_uniqueness_validation_excludes_current_user()
    {
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'privacy_policy_accepted_at' => now()
        ]);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        // User1 should be able to keep their own email
        $response = $this->actingAs($user1)->put(route('profile.update'), [
            'name' => $user1->name,
            'email' => 'user1@example.com',
        ]);

        $response->assertRedirect(route('profile.edit'));

        // User1 should not be able to use user2's email
        $response = $this->actingAs($user1)->put(route('profile.update'), [
            'name' => $user1->name,
            'email' => 'user2@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_avatar_upload_validation()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => now()]);

        // Test file size limit
        $largeFile = UploadedFile::fake()->create('large.jpg', 3000); // 3MB

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $largeFile,
        ]);

        $response->assertSessionHasErrors(['avatar']);

        // Test file type validation
        $textFile = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $textFile,
        ]);

        $response->assertSessionHasErrors(['avatar']);
    }

    public function test_old_avatar_is_deleted_when_uploading_new_one()
    {
        $user = User::factory()->create(['privacy_policy_accepted_at' => now()]);
        
        // Create old avatar file
        Storage::disk('public')->put('avatars/old-avatar.jpg', 'old content');
        $user->update(['avatar' => 'avatars/old-avatar.jpg']);

        // Upload new avatar
        $newFile = UploadedFile::fake()->create('new-avatar.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $newFile,
        ]);

        $response->assertRedirect(route('profile.edit'));
        
        // Old avatar should be deleted
        Storage::disk('public')->assertMissing('avatars/old-avatar.jpg');
        
        // New avatar should exist
        $user->refresh();
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_guest_cannot_access_profile_routes()
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->put(route('profile.update'))->assertRedirect(route('login'));
        $this->delete(route('profile.avatar.delete'))->assertRedirect(route('login'));
        $this->post(route('profile.privacy-policy.accept'))->assertRedirect(route('login'));
        $this->get(route('profile.export-data'))->assertRedirect(route('login'));
        $this->post(route('profile.request-deletion'))->assertRedirect(route('login'));
        $this->post(route('profile.anonymize'))->assertRedirect(route('login'));
    }
}