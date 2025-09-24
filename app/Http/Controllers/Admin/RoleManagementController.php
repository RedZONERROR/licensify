<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin']);
    }

    /**
     * Display role management interface
     */
    public function index()
    {
        Gate::authorize('manage-roles');

        $users = User::with('reseller')
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20);

        $roles = Role::cases();
        $roleStats = $this->getRoleStatistics();

        return view('admin.roles.index', compact('users', 'roles', 'roleStats'));
    }

    /**
     * Show role assignment form for a specific user
     */
    public function show(User $user)
    {
        Gate::authorize('manage-roles');
        Gate::authorize('view', $user);

        $availableRoles = collect(auth()->user()->role->canManageRoles())
            ->map(fn($role) => $role->value)
            ->toArray();

        return view('admin.roles.show', compact('user', 'availableRoles'));
    }

    /**
     * Update user role
     */
    public function update(Request $request, User $user)
    {
        Gate::authorize('manage-roles');
        
        $currentUser = auth()->user();
        $newRole = Role::from($request->role);

        // Check if current user can assign this role
        Gate::authorize('changeRole', [$user, $newRole]);

        $request->validate([
            'role' => ['required', Rule::in(Role::values())],
            'reason' => 'required|string|max:500',
        ]);

        $oldRole = $user->role;

        // Update the role
        $user->update(['role' => $newRole]);

        // Log the role change
        activity()
            ->performedOn($user)
            ->causedBy($currentUser)
            ->withProperties([
                'old_role' => $oldRole->value,
                'new_role' => $newRole->value,
                'reason' => $request->reason,
            ])
            ->log('Role changed from ' . $oldRole->label() . ' to ' . $newRole->label());

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "User role updated to {$newRole->label()}");
    }

    /**
     * Bulk role assignment
     */
    public function bulkUpdate(Request $request)
    {
        Gate::authorize('manage-roles');

        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'role' => ['required', Rule::in(Role::values())],
            'reason' => 'required|string|max:500',
        ]);

        $newRole = Role::from($request->role);
        $currentUser = auth()->user();
        $users = User::whereIn('id', $request->user_ids)->get();

        $updated = 0;
        $errors = [];

        foreach ($users as $user) {
            try {
                // Check permissions for each user
                if (!$currentUser->canManageUser($user) || 
                    !in_array($newRole, $currentUser->role->canManageRoles())) {
                    $errors[] = "Cannot change role for {$user->name}";
                    continue;
                }

                $oldRole = $user->role;
                $user->update(['role' => $newRole]);

                // Log the change
                activity()
                    ->performedOn($user)
                    ->causedBy($currentUser)
                    ->withProperties([
                        'old_role' => $oldRole->value,
                        'new_role' => $newRole->value,
                        'reason' => $request->reason,
                        'bulk_operation' => true,
                    ])
                    ->log('Role changed from ' . $oldRole->label() . ' to ' . $newRole->label() . ' (bulk operation)');

                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Error updating {$user->name}: " . $e->getMessage();
            }
        }

        $message = "Updated {$updated} user(s) to {$newRole->label()}";
        if (!empty($errors)) {
            $message .= '. Errors: ' . implode(', ', $errors);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with($updated > 0 ? 'success' : 'error', $message);
    }

    /**
     * Get role statistics
     */
    protected function getRoleStatistics(): array
    {
        $stats = [];
        
        foreach (Role::cases() as $role) {
            $stats[$role->value] = [
                'count' => User::where('role', $role)->count(),
                'label' => $role->label(),
                'description' => $role->description(),
                'permissions' => $role->permissions(),
            ];
        }

        return $stats;
    }

    /**
     * Show role permissions matrix
     */
    public function permissions()
    {
        Gate::authorize('manage-roles');

        $roles = Role::cases();
        $allPermissions = collect($roles)
            ->flatMap(fn($role) => $role->permissions())
            ->unique()
            ->sort()
            ->values();

        $permissionMatrix = [];
        foreach ($roles as $role) {
            $rolePermissions = $role->permissions();
            foreach ($allPermissions as $permission) {
                $permissionMatrix[$role->value][$permission] = in_array($permission, $rolePermissions);
            }
        }

        return view('admin.roles.permissions', compact('roles', 'allPermissions', 'permissionMatrix'));
    }

    /**
     * Export role assignments
     */
    public function export()
    {
        Gate::authorize('manage-roles');

        $users = User::select('id', 'name', 'email', 'role', 'created_at', 'reseller_id')
            ->with('reseller:id,name')
            ->get();

        $filename = 'role-assignments-' . now()->format('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, ['ID', 'Name', 'Email', 'Role', 'Role Label', 'Reseller', 'Created At']);
            
            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->role->value,
                    $user->role->label(),
                    $user->reseller?->name ?? 'N/A',
                    $user->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}