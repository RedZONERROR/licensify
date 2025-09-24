<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ProfileController extends Controller
{
    /**
     * Show the profile edit form
     */
    public function edit()
    {
        return view('profile.edit', [
            'user' => Auth::user()
        ]);
    }

    /**
     * Update the user's profile information
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validationRules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'avatar' => ['nullable', 'image', 'max:2048'], // 2MB max
            'developer_notes' => ['nullable', 'string', 'max:1000'],
        ];

        // Only require privacy policy acceptance if user hasn't accepted it yet
        if (!$user->privacy_policy_accepted_at) {
            $validationRules['privacy_policy_accepted_at'] = ['required', 'accepted'];
        }

        $request->validate($validationRules);

        $originalData = $user->toArray();
        
        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        // Handle password update
        if ($request->filled('password')) {
            $updateData['password'] = $request->password;
            
            // Log password change
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'profile_password_changed',
                'auditable_type' => 'App\Models\User',
                'auditable_id' => $user->id,
                'new_values' => ['password' => 'changed'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $updateData['avatar'] = $avatarPath;
        }

        // Handle privacy policy acceptance
        if ($request->has('privacy_policy_accepted_at') && !$user->privacy_policy_accepted_at) {
            $updateData['privacy_policy_accepted_at'] = now();
            
            // Log privacy policy acceptance
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'privacy_policy_accepted',
                'auditable_type' => 'App\Models\User',
                'auditable_id' => $user->id,
                'new_values' => ['privacy_policy_accepted_at' => now()->toISOString()],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Handle developer notes (only for developer role)
        if ($user->isDeveloper() && $request->has('developer_notes')) {
            $updateData['developer_notes'] = $request->developer_notes;
        }

        $user->update($updateData);

        // Log profile update
        $changes = array_diff_assoc($updateData, $originalData);
        if (!empty($changes)) {
            // Remove sensitive data from audit log
            unset($changes['password']);
            
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'profile_updated',
                'auditable_type' => 'App\Models\User',
                'auditable_id' => $user->id,
                'new_values' => $changes,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->route('profile.edit')
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Delete avatar
     */
    public function deleteAvatar(Request $request)
    {
        $user = Auth::user();
        
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
            
            // Log avatar deletion
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'avatar_deleted',
                'auditable_type' => 'App\Models\User',
                'auditable_id' => $user->id,
                'new_values' => ['avatar' => 'deleted'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Accept privacy policy
     */
    public function acceptPrivacyPolicy(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->privacy_policy_accepted_at) {
            $user->update(['privacy_policy_accepted_at' => now()]);
            
            // Log privacy policy acceptance
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'privacy_policy_accepted',
                'auditable_type' => 'App\Models\User',
                'auditable_id' => $user->id,
                'new_values' => ['privacy_policy_accepted_at' => now()->toISOString()],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Export user data (GDPR compliance)
     */
    public function exportData(Request $request)
    {
        $user = Auth::user();
        
        // Collect all user data
        $userData = [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
                'privacy_policy_accepted_at' => $user->privacy_policy_accepted_at?->toISOString(),
                'developer_notes' => $user->developer_notes,
                '2fa_enabled' => $user->{'2fa_enabled'},
                'oauth_providers' => $user->oauth_providers,
            ],
            'licenses_owned' => $user->ownedLicenses()->get()->map(function ($license) {
                return [
                    'id' => $license->id,
                    'license_key' => $license->license_key,
                    'status' => $license->status,
                    'device_type' => $license->device_type,
                    'max_devices' => $license->max_devices,
                    'expires_at' => $license->expires_at?->toISOString(),
                    'created_at' => $license->created_at->toISOString(),
                ];
            }),
            'licenses_assigned' => $user->assignedLicenses()->get()->map(function ($license) {
                return [
                    'id' => $license->id,
                    'license_key' => $license->license_key,
                    'status' => $license->status,
                    'device_type' => $license->device_type,
                    'max_devices' => $license->max_devices,
                    'expires_at' => $license->expires_at?->toISOString(),
                    'created_at' => $license->created_at->toISOString(),
                ];
            }),
            'chat_messages_sent' => $user->sentMessages()->get()->map(function ($message) {
                return [
                    'id' => $message->id,
                    'receiver_id' => $message->receiver_id,
                    'body' => $message->body,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at->toISOString(),
                ];
            }),
            'chat_messages_received' => $user->receivedMessages()->get()->map(function ($message) {
                return [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'body' => $message->body,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at->toISOString(),
                ];
            }),
            'audit_logs' => $user->auditLogs()->get()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'model_type' => $log->model_type,
                    'model_id' => $log->model_id,
                    'changes' => $log->changes,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->toISOString(),
                ];
            }),
        ];

        // If user is reseller, include managed users
        if ($user->isReseller()) {
            $userData['managed_users'] = $user->managedUsers()->get()->map(function ($managedUser) {
                return [
                    'id' => $managedUser->id,
                    'name' => $managedUser->name,
                    'email' => $managedUser->email,
                    'role' => $managedUser->role->value,
                    'created_at' => $managedUser->created_at->toISOString(),
                ];
            });
        }

        // Log data export
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'data_exported',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => $user->id,
            'new_values' => ['export_requested' => true],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $filename = 'user_data_export_' . $user->id . '_' . now()->format('Y-m-d_H-i-s') . '.json';
        
        return response()->json($userData)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Type', 'application/json');
    }

    /**
     * Request account deletion (GDPR compliance)
     */
    public function requestDeletion(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'confirmation' => ['required', 'string', 'in:DELETE'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Log deletion request
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'account_deletion_requested',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => $user->id,
            'new_values' => [
                'reason' => $request->reason,
                'requested_at' => now()->toISOString(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account deletion request has been submitted. You will receive an email confirmation within 24 hours.'
        ]);
    }

    /**
     * Anonymize user data (GDPR compliance)
     */
    public function anonymizeData(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'confirmation' => ['required', 'string', 'in:ANONYMIZE'],
        ]);

        // Store original data for audit
        $originalData = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        // Anonymize user data
        $anonymizedData = [
            'name' => 'Anonymous User ' . $user->id,
            'email' => 'anonymized_' . $user->id . '@deleted.local',
            'avatar' => null,
            'developer_notes' => null,
            '2fa_enabled' => false,
            '2fa_secret' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'oauth_providers' => null,
        ];

        // Delete avatar file if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update($anonymizedData);

        // Log anonymization
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'account_anonymized',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => $user->id,
            'new_values' => [
                'original_name' => $originalData['name'],
                'original_email' => $originalData['email'],
                'anonymized_at' => now()->toISOString(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Logout user after anonymization
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Your account has been anonymized successfully. You have been logged out.',
            'redirect' => route('login')
        ]);
    }
}