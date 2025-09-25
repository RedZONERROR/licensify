<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reseller\CreateUserRequest;
use App\Http\Requests\Reseller\UpdateUserRequest;
use App\Models\User;
use App\Services\ResellerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerDashboardController extends Controller
{
    public function __construct(
        private ResellerService $resellerService
    ) {
        $this->middleware('auth');
        $this->middleware('role:reseller');
    }

    /**
     * Display reseller dashboard
     */
    public function index(Request $request): View|JsonResponse
    {
        $dashboardData = $this->resellerService->getResellerDashboardData(auth()->user());

        if ($request->wantsJson()) {
            return response()->json($dashboardData);
        }

        return view('reseller.dashboard', $dashboardData);
    }

    /**
     * Display managed users
     */
    public function users(Request $request): View|JsonResponse
    {
        $users = $this->resellerService->getResellerUsers(
            auth()->user(),
            $request->get('per_page', 15)
        );

        if ($request->wantsJson()) {
            return response()->json(['users' => $users]);
        }

        return view('reseller.users.index', compact('users'));
    }

    /**
     * Show form for creating a new user
     */
    public function createUser(): View|RedirectResponse
    {
        $reseller = auth()->user();
        
        if (!$reseller->canAddUser()) {
            return redirect()
                ->route('reseller.users')
                ->withErrors(['quota' => 'You have reached your maximum user quota.']);
        }

        return view('reseller.users.create', compact('reseller'));
    }

    /**
     * Store a newly created user
     */
    public function storeUser(CreateUserRequest $request): RedirectResponse|JsonResponse
    {
        $reseller = auth()->user();

        try {
            $user = $this->resellerService->createUserForReseller($reseller, $request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'User created successfully.',
                    'user' => $user
                ], 201);
            }

            return redirect()
                ->route('reseller.users')
                ->with('success', 'User created successfully.');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to create user.',
                    'error' => $e->getMessage()
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified user
     */
    public function showUser(User $user, Request $request): View|JsonResponse
    {
        $reseller = auth()->user();

        if ($user->reseller_id !== $reseller->id) {
            abort(403, 'You can only view users assigned to you.');
        }

        $userLicenses = $user->assignedLicenses()
            ->with(['product'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        if ($request->wantsJson()) {
            return response()->json([
                'user' => $user,
                'licenses' => $userLicenses
            ]);
        }

        return view('reseller.users.show', compact('user', 'userLicenses'));
    }

    /**
     * Show form for editing user
     */
    public function editUser(User $user): View
    {
        $reseller = auth()->user();

        if ($user->reseller_id !== $reseller->id) {
            abort(403, 'You can only edit users assigned to you.');
        }

        return view('reseller.users.edit', compact('user'));
    }

    /**
     * Update the specified user
     */
    public function updateUser(UpdateUserRequest $request, User $user): RedirectResponse|JsonResponse
    {
        $reseller = auth()->user();

        if ($user->reseller_id !== $reseller->id) {
            abort(403, 'You can only edit users assigned to you.');
        }

        try {
            $data = $request->validated();
            
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'User updated successfully.',
                    'user' => $user->fresh()
                ]);
            }

            return redirect()
                ->route('reseller.users.show', $user)
                ->with('success', 'User updated successfully.');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to update user.',
                    'error' => $e->getMessage()
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()]);
        }
    }

    /**
     * Display managed licenses
     */
    public function licenses(Request $request): View|JsonResponse
    {
        $licenses = $this->resellerService->getResellerLicenses(
            auth()->user(),
            $request->get('per_page', 15)
        );

        if ($request->wantsJson()) {
            return response()->json(['licenses' => $licenses]);
        }

        return view('reseller.licenses.index', compact('licenses'));
    }

    /**
     * Get dashboard statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $dashboardData = $this->resellerService->getResellerDashboardData(auth()->user());

        return response()->json([
            'stats' => $dashboardData['stats'],
            'quota_warnings' => $dashboardData['quota_warnings']
        ]);
    }

    /**
     * Get quota information
     */
    public function quotaInfo(Request $request): JsonResponse
    {
        $reseller = auth()->user();

        return response()->json([
            'user_quota' => [
                'current' => $reseller->current_users_count,
                'max' => $reseller->max_users_quota,
                'remaining' => $reseller->getRemainingUserQuota(),
                'percentage' => $reseller->getUserQuotaUsagePercentage(),
                'near_limit' => $reseller->isUserQuotaNearLimit(),
                'can_add' => $reseller->canAddUser(),
            ],
            'license_quota' => [
                'current' => $reseller->current_licenses_count,
                'max' => $reseller->max_licenses_quota,
                'remaining' => $reseller->getRemainingLicenseQuota(),
                'percentage' => $reseller->getLicenseQuotaUsagePercentage(),
                'near_limit' => $reseller->isLicenseQuotaNearLimit(),
                'can_add' => $reseller->canAddLicense(),
            ]
        ]);
    }

    /**
     * Remove user from reseller (unassign)
     */
    public function removeUser(User $user, Request $request): JsonResponse
    {
        $reseller = auth()->user();

        if ($user->reseller_id !== $reseller->id) {
            return response()->json([
                'message' => 'You can only manage users assigned to you.'
            ], 403);
        }

        try {
            $this->resellerService->removeUserFromReseller($user);

            return response()->json([
                'message' => 'User removed successfully.',
                'user' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove user.',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get recent activity for dashboard
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $reseller = auth()->user();
        
        $recentUsers = $reseller->managedUsers()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentLicenses = $reseller->ownedLicenses()
            ->with(['user', 'product'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'recent_users' => $recentUsers,
            'recent_licenses' => $recentLicenses
        ]);
    }
}