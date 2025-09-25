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
    
    // Reseller Management
    Route::resource('resellers', App\Http\Controllers\Admin\ResellerController::class);
    Route::get('/resellers/{reseller}/users', [App\Http\Controllers\Admin\ResellerController::class, 'users'])->name('resellers.users');
    Route::get('/resellers/{reseller}/licenses', [App\Http\Controllers\Admin\ResellerController::class, 'licenses'])->name('resellers.licenses');
    Route::post('/resellers/{reseller}/assign-user', [App\Http\Controllers\Admin\ResellerController::class, 'assignUser'])->name('resellers.assign-user');
    Route::delete('/resellers/{reseller}/users/{user}', [App\Http\Controllers\Admin\ResellerController::class, 'removeUser'])->name('resellers.remove-user');
    Route::get('/available-users', [App\Http\Controllers\Admin\ResellerController::class, 'availableUsers'])->name('resellers.available-users');
    Route::post('/resellers/{reseller}/update-counts', [App\Http\Controllers\Admin\ResellerController::class, 'updateCounts'])->name('resellers.update-counts');
    Route::get('/reseller-statistics', [App\Http\Controllers\Admin\ResellerController::class, 'statistics'])->name('resellers.statistics');
});

// Admin and Developer payment management routes
Route::middleware(['auth', 'role:admin,developer'])->prefix('admin')->name('admin.')->group(function () {
    // Payment Management
    Route::get('/payments', [App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{transaction}', [App\Http\Controllers\Admin\PaymentController::class, 'show'])->name('payments.show');
    Route::get('/payments-statistics', [App\Http\Controllers\Admin\PaymentController::class, 'statistics'])->name('payments.statistics');
    Route::get('/payments-export', [App\Http\Controllers\Admin\PaymentController::class, 'export'])->name('payments.export');
    
    // Wallet Management
    Route::get('/wallets', [App\Http\Controllers\Admin\PaymentController::class, 'wallets'])->name('wallets.index');
    Route::get('/wallets/{wallet}', [App\Http\Controllers\Admin\PaymentController::class, 'showWallet'])->name('wallets.show');
    Route::post('/wallets/credit', [App\Http\Controllers\Admin\PaymentController::class, 'creditWallet'])->name('wallets.credit');
    Route::post('/wallets/debit', [App\Http\Controllers\Admin\PaymentController::class, 'debitWallet'])->name('wallets.debit');
    Route::get('/users/search', [App\Http\Controllers\Admin\PaymentController::class, 'searchUsers'])->name('users.search');
    Route::get('/users/{user}/wallet', [App\Http\Controllers\Admin\PaymentController::class, 'getUserWallet'])->name('users.wallet');
    
    // Refund Management
    Route::post('/payments/{transaction}/refund', [App\Http\Controllers\Admin\PaymentController::class, 'refund'])->name('payments.refund');
});

// Reseller Dashboard routes
Route::middleware(['auth', 'role:reseller'])->prefix('reseller')->name('reseller.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\ResellerDashboardController::class, 'index'])->name('dashboard');
    
    // User Management
    Route::get('/users', [App\Http\Controllers\ResellerDashboardController::class, 'users'])->name('users');
    Route::get('/users/create', [App\Http\Controllers\ResellerDashboardController::class, 'createUser'])->name('users.create');
    Route::post('/users', [App\Http\Controllers\ResellerDashboardController::class, 'storeUser'])->name('users.store');
    Route::get('/users/{user}', [App\Http\Controllers\ResellerDashboardController::class, 'showUser'])->name('users.show');
    Route::get('/users/{user}/edit', [App\Http\Controllers\ResellerDashboardController::class, 'editUser'])->name('users.edit');
    Route::put('/users/{user}', [App\Http\Controllers\ResellerDashboardController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}', [App\Http\Controllers\ResellerDashboardController::class, 'removeUser'])->name('users.remove');
    
    // License Management
    Route::get('/licenses', [App\Http\Controllers\ResellerDashboardController::class, 'licenses'])->name('licenses');
    
    // API endpoints for AJAX
    Route::get('/statistics', [App\Http\Controllers\ResellerDashboardController::class, 'statistics'])->name('statistics');
    Route::get('/quota-info', [App\Http\Controllers\ResellerDashboardController::class, 'quotaInfo'])->name('quota-info');
    Route::get('/recent-activity', [App\Http\Controllers\ResellerDashboardController::class, 'recentActivity'])->name('recent-activity');
});

// Chat routes
Route::middleware(['auth', 'session.security'])->prefix('chat')->name('chat.')->group(function () {
    Route::get('/', [App\Http\Controllers\ChatController::class, 'index'])->name('index');
    Route::get('/messages/{user}', [App\Http\Controllers\ChatController::class, 'getMessages'])->name('messages');
    Route::post('/send/{user}', [App\Http\Controllers\ChatController::class, 'sendMessage'])->name('send');
    Route::post('/mark-read/{user}', [App\Http\Controllers\ChatController::class, 'markAsRead'])->name('mark-read');
    Route::get('/unread-counts', [App\Http\Controllers\ChatController::class, 'getUnreadCounts'])->name('unread-counts');
    Route::get('/history/{user}', [App\Http\Controllers\ChatController::class, 'getConversationHistory'])->name('history');
    
    // Moderation routes (Admin/Developer only)
    Route::middleware('role:admin,developer')->group(function () {
        Route::post('/enable-slow-mode/{user}', [App\Http\Controllers\ChatController::class, 'enableSlowMode'])->name('enable-slow-mode');
        Route::post('/disable-slow-mode/{user}', [App\Http\Controllers\ChatController::class, 'disableSlowMode'])->name('disable-slow-mode');
        Route::post('/block/{user}', [App\Http\Controllers\ChatController::class, 'blockUser'])->name('block');
        Route::post('/unblock/{user}', [App\Http\Controllers\ChatController::class, 'unblockUser'])->name('unblock');
    });
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