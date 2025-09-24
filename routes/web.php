<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'session.security'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    // Profile routes
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/avatar', [App\Http\Controllers\ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    Route::post('/profile/privacy-policy/accept', [App\Http\Controllers\ProfileController::class, 'acceptPrivacyPolicy'])->name('profile.privacy-policy.accept');
    Route::get('/profile/export-data', [App\Http\Controllers\ProfileController::class, 'exportData'])->name('profile.export-data');
    Route::post('/profile/request-deletion', [App\Http\Controllers\ProfileController::class, 'requestDeletion'])->name('profile.request-deletion');
    Route::post('/profile/anonymize', [App\Http\Controllers\ProfileController::class, 'anonymizeData'])->name('profile.anonymize');
    
    // Two-Factor Authentication routes
    Route::get('/two-factor', [App\Http\Controllers\TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('/two-factor', [App\Http\Controllers\TwoFactorController::class, 'store'])->name('two-factor.store');
    Route::delete('/two-factor', [App\Http\Controllers\TwoFactorController::class, 'destroy'])->name('two-factor.destroy');
    Route::get('/two-factor/recovery-codes', [App\Http\Controllers\TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
    Route::post('/two-factor/recovery-codes', [App\Http\Controllers\TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes.regenerate');
});

// Two-Factor Challenge routes (outside auth middleware to allow challenge)
Route::middleware(['auth'])->group(function () {
    Route::get('/two-factor/challenge', [App\Http\Controllers\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [App\Http\Controllers\TwoFactorController::class, 'verify'])->name('two-factor.verify');
});

// OAuth Authentication routes
Route::prefix('auth')->name('oauth.')->group(function () {
    Route::get('/{provider}', [App\Http\Controllers\Auth\OAuthController::class, 'redirectToProvider'])->name('redirect');
    Route::get('/{provider}/callback', [App\Http\Controllers\Auth\OAuthController::class, 'handleProviderCallback'])->name('callback');
    
    // OAuth account linking (requires authentication)
    Route::middleware('auth')->group(function () {
        Route::get('/{provider}/link', [App\Http\Controllers\Auth\OAuthController::class, 'linkProvider'])->name('link');
        Route::delete('/{provider}/unlink', [App\Http\Controllers\Auth\OAuthController::class, 'unlinkProvider'])->name('unlink');
    });
});

// Admin routes
Route::middleware(['auth', 'role:admin,developer,reseller'])->prefix('admin')->name('admin.')->group(function () {
    // License Management
    Route::resource('licenses', App\Http\Controllers\Admin\LicenseController::class);
    Route::post('/licenses/{license}/suspend', [App\Http\Controllers\Admin\LicenseController::class, 'suspend'])->name('licenses.suspend');
    Route::post('/licenses/{license}/unsuspend', [App\Http\Controllers\Admin\LicenseController::class, 'unsuspend'])->name('licenses.unsuspend');
    Route::post('/licenses/{license}/reset-devices', [App\Http\Controllers\Admin\LicenseController::class, 'resetDevices'])->name('licenses.reset-devices');
    Route::post('/licenses/{license}/expire', [App\Http\Controllers\Admin\LicenseController::class, 'expire'])->name('licenses.expire');
    Route::post('/licenses/{license}/unbind-device', [App\Http\Controllers\Admin\LicenseController::class, 'unbindDevice'])->name('licenses.unbind-device');
    Route::get('/licenses-statistics', [App\Http\Controllers\Admin\LicenseController::class, 'statistics'])->name('licenses.statistics');
});

// Admin-only routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Role Management
    Route::get('/roles', [App\Http\Controllers\Admin\RoleManagementController::class, 'index'])->name('roles.index');
    Route::get('/roles/{user}', [App\Http\Controllers\Admin\RoleManagementController::class, 'show'])->name('roles.show');
    Route::put('/roles/{user}', [App\Http\Controllers\Admin\RoleManagementController::class, 'update'])->name('roles.update');
    Route::post('/roles/bulk-update', [App\Http\Controllers\Admin\RoleManagementController::class, 'bulkUpdate'])->name('roles.bulk-update');
    Route::get('/roles-permissions', [App\Http\Controllers\Admin\RoleManagementController::class, 'permissions'])->name('roles.permissions');
    Route::get('/roles-export', [App\Http\Controllers\Admin\RoleManagementController::class, 'export'])->name('roles.export');
});

// Test routes for role and permission middleware
Route::middleware(['auth'])->group(function () {
    Route::get('/test-admin-route', function () {
        return response('Admin access granted');
    })->middleware('role:admin');
    
    Route::get('/test-developer-route', function () {
        return response('Developer access granted');
    })->middleware('role:developer,admin');
    
    Route::get('/test-permission-route', function () {
        return response('Permission granted');
    })->middleware('permission:manage_users');
});

require __DIR__.'/auth.php';