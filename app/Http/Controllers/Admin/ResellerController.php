<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateResellerRequest;
use App\Http\Requests\Admin\UpdateResellerRequest;
use App\Models\User;
use App\Services\ResellerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerController extends Controller
{
    public function __construct(
        private ResellerService $resellerService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of resellers
     */
    public function index(Request $request): View|JsonResponse
    {
        $resellers = $this->resellerService->getPaginatedResellers(
            $request->get('per_page', 15)
        );

        $statistics = $this->resellerService->getResellerStatistics();

        if ($request->wantsJson()) {
            return response()->json([
                'resellers' => $resellers,
                'statistics' => $statistics
            ]);
        }

        return view('admin.resellers.index', compact('resellers', 'statistics'));
    }

    /**
     * Show the form for creating a new reseller
     */
    public function create(): View
    {
        return view('admin.resellers.create');
    }

    /**
     * Store a newly created reseller
     */
    public function store(CreateResellerRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $reseller = $this->resellerService->createReseller($request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Reseller created successfully.',
                    'reseller' => $reseller
                ], 201);
            }

            return redirect()
                ->route('admin.resellers.show', $reseller)
                ->with('success', 'Reseller created successfully.');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to create reseller.',
                    'error' => $e->getMessage()
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create reseller: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified reseller
     */
    public function show(User $reseller, Request $request): View|JsonResponse
    {
        $this->authorize('view', $reseller);

        if (!$reseller->isReseller()) {
            abort(404);
        }

        $dashboardData = $this->resellerService->getResellerDashboardData($reseller);

        if ($request->wantsJson()) {
            return response()->json($dashboardData);
        }

        return view('admin.resellers.show', $dashboardData);
    }

    /**
     * Show the form for editing the specified reseller
     */
    public function edit(User $reseller): View
    {
        $this->authorize('update', $reseller);

        if (!$reseller->isReseller()) {
            abort(404);
        }

        return view('admin.resellers.edit', compact('reseller'));
    }

    /**
     * Update the specified reseller
     */
    public function update(UpdateResellerRequest $request, User $reseller): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $reseller);

        if (!$reseller->isReseller()) {
            abort(404);
        }

        try {
            $updatedReseller = $this->resellerService->updateReseller($reseller, $request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Reseller updated successfully.',
                    'reseller' => $updatedReseller
                ]);
            }

            return redirect()
                ->route('admin.resellers.show', $updatedReseller)
                ->with('success', 'Reseller updated successfully.');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to update reseller.',
                    'error' => $e->getMessage()
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update reseller: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified reseller
     */
    public function destroy(User $reseller, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $reseller);

        if (!$reseller->isReseller()) {
            abort(404);
        }

        try {
            $this->resellerService->deleteReseller($reseller);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Reseller deleted successfully.'
                ]);
            }

            return redirect()
                ->route('admin.resellers.index')
                ->with('success', 'Reseller deleted successfully.');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to delete reseller.',
                    'error' => $e->getMessage()
                ], 422);
            }

            return back()
                ->withErrors(['error' => 'Failed to delete reseller: ' . $e->getMessage()]);
        }
    }

    /**
     * Get reseller users
     */
    public function users(User $reseller, Request $request): View|JsonResponse
    {
        $this->authorize('view', $reseller);

        if (!$reseller->isReseller()) {
            abort(404);
        }

        $users = $this->resellerService->getResellerUsers(
            $reseller,
            $request->get('per_page', 15)
        );

        if ($request->wantsJson()) {
            return response()->json(['users' => $users]);
        }

        return view('admin.resellers.users', compact('reseller', 'users'));
    }

    /**
     * Get reseller licenses
     */
    public function licenses(User $reseller, Request $request): View|JsonResponse
    {
        $this->authorize('view', $reseller);

        if (!$reseller->isReseller()) {
            abort(404);
        }

        $licenses = $this->resellerService->getResellerLicenses(
            $reseller,
            $request->get('per_page', 15)
        );

        if ($request->wantsJson()) {
            return response()->json(['licenses' => $licenses]);
        }

        return view('admin.resellers.licenses', compact('reseller', 'licenses'));
    }

    /**
     * Assign user to reseller
     */
    public function assignUser(User $reseller, Request $request): JsonResponse
    {
        $this->authorize('update', $reseller);

        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $user = User::findOrFail($request->user_id);

        try {
            $this->resellerService->assignUserToReseller($reseller, $user);

            return response()->json([
                'message' => 'User assigned to reseller successfully.',
                'user' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign user to reseller.',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove user from reseller
     */
    public function removeUser(User $reseller, User $user, Request $request): JsonResponse
    {
        $this->authorize('update', $reseller);

        if ($user->reseller_id !== $reseller->id) {
            return response()->json([
                'message' => 'User is not assigned to this reseller.'
            ], 422);
        }

        try {
            $this->resellerService->removeUserFromReseller($user);

            return response()->json([
                'message' => 'User removed from reseller successfully.',
                'user' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove user from reseller.',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get available users for assignment
     */
    public function availableUsers(Request $request): JsonResponse
    {
        $users = $this->resellerService->getAvailableUsers();

        return response()->json(['users' => $users]);
    }

    /**
     * Update reseller counts
     */
    public function updateCounts(User $reseller, Request $request): JsonResponse
    {
        $this->authorize('update', $reseller);

        if (!$reseller->isReseller()) {
            abort(404);
        }

        $this->resellerService->updateResellerCounts($reseller);

        return response()->json([
            'message' => 'Reseller counts updated successfully.',
            'reseller' => $reseller->fresh()
        ]);
    }

    /**
     * Get reseller statistics for dashboard widgets
     */
    public function statistics(Request $request): JsonResponse
    {
        $statistics = $this->resellerService->getResellerStatistics();

        return response()->json(['statistics' => $statistics]);
    }
}