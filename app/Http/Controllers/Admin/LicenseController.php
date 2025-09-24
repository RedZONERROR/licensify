<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LicenseController extends Controller
{
    public function __construct(
        private LicenseService $licenseService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin,developer,reseller');
    }

    /**
     * Display a listing of licenses
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'product_id', 'search', 'per_page']);
        $licenses = $this->licenseService->getLicensesForUser(auth()->user(), $filters);
        $statistics = $this->licenseService->getLicenseStatistics(auth()->user());
        
        $products = Product::active()->get();
        
        return view('admin.licenses.index', compact('licenses', 'statistics', 'products', 'filters'));
    }

    /**
     * Show the form for creating a new license
     */
    public function create(): View
    {
        Gate::authorize('create', License::class);
        
        $products = Product::active()->get();
        $users = $this->getAvailableUsers();
        
        return view('admin.licenses.create', compact('products', 'users'));
    }

    /**
     * Store a newly created license
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', License::class);
        
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'owner_id' => 'required|exists:users,id',
            'user_id' => 'nullable|exists:users,id',
            'device_type' => 'nullable|string|max:100',
            'max_devices' => 'required|integer|min:1|max:100',
            'expires_at' => 'nullable|date|after:now',
            'status' => ['required', Rule::in([
                License::STATUS_ACTIVE,
                License::STATUS_SUSPENDED,
                License::STATUS_RESET,
            ])],
        ]);

        try {
            $license = $this->licenseService->generateLicense($validated);
            
            return redirect()
                ->route('admin.licenses.show', $license)
                ->with('success', 'License created successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create license: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified license
     */
    public function show(License $license): View
    {
        Gate::authorize('view', $license);
        
        $license->load(['product', 'owner', 'user', 'activations']);
        
        return view('admin.licenses.show', compact('license'));
    }

    /**
     * Show the form for editing the specified license
     */
    public function edit(License $license): View
    {
        Gate::authorize('update', $license);
        
        $license->load(['product', 'owner', 'user']);
        $products = Product::active()->get();
        $users = $this->getAvailableUsers();
        
        return view('admin.licenses.edit', compact('license', 'products', 'users'));
    }

    /**
     * Update the specified license
     */
    public function update(Request $request, License $license): RedirectResponse
    {
        Gate::authorize('update', $license);
        
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'device_type' => 'nullable|string|max:100',
            'max_devices' => 'required|integer|min:1|max:100',
            'expires_at' => 'nullable|date',
        ]);

        try {
            $license->update($validated);
            
            return redirect()
                ->route('admin.licenses.show', $license)
                ->with('success', 'License updated successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update license: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified license
     */
    public function destroy(License $license): RedirectResponse
    {
        Gate::authorize('delete', $license);
        
        try {
            $license->delete();
            
            return redirect()
                ->route('admin.licenses.index')
                ->with('success', 'License deleted successfully.');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete license: ' . $e->getMessage()]);
        }
    }

    /**
     * Suspend a license
     */
    public function suspend(License $license): JsonResponse
    {
        Gate::authorize('update', $license);
        
        try {
            $success = $this->licenseService->suspendLicense($license);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'License suspended successfully.' : 'Failed to suspend license.',
                'status' => $license->fresh()->status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unsuspend a license
     */
    public function unsuspend(License $license): JsonResponse
    {
        Gate::authorize('update', $license);
        
        try {
            $success = $this->licenseService->unsuspendLicense($license);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'License unsuspended successfully.' : 'Failed to unsuspend license.',
                'status' => $license->fresh()->status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset device bindings for a license
     */
    public function resetDevices(License $license): JsonResponse
    {
        Gate::authorize('update', $license);
        
        try {
            $success = $this->licenseService->resetDeviceBindings($license);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Device bindings reset successfully.' : 'Failed to reset device bindings.',
                'active_devices' => $license->fresh()->getActiveDeviceCount(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Expire a license
     */
    public function expire(License $license): JsonResponse
    {
        Gate::authorize('update', $license);
        
        try {
            $success = $this->licenseService->expireLicense($license);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'License expired successfully.' : 'Failed to expire license.',
                'status' => $license->fresh()->status,
                'expires_at' => $license->fresh()->expires_at?->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unbind a specific device from license
     */
    public function unbindDevice(License $license, Request $request): JsonResponse
    {
        Gate::authorize('update', $license);
        
        $request->validate([
            'device_hash' => 'required|string',
        ]);

        try {
            $success = $this->licenseService->unbindDevice($license, $request->device_hash);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Device unbound successfully.' : 'Failed to unbind device.',
                'active_devices' => $license->fresh()->getActiveDeviceCount(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get license statistics (AJAX)
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = $this->licenseService->getLicenseStatistics(auth()->user());
            
            return response()->json([
                'success' => true,
                'statistics' => $statistics,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available users based on current user's role
     */
    private function getAvailableUsers()
    {
        $user = auth()->user();
        
        if ($user->role === 'admin' || $user->role === 'developer') {
            return User::whereIn('role', ['user', 'reseller'])->get();
        } elseif ($user->role === 'reseller') {
            return User::where('reseller_id', $user->id)
                      ->orWhere('id', $user->id)
                      ->get();
        }
        
        return collect();
    }
}