<?php

use App\Http\Controllers\Api\LicenseValidationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// License Validation API
Route::prefix('license')->group(function () {
    Route::post('/validate', [LicenseValidationController::class, 'validateLicense'])
        ->middleware(['api.auth:' . \App\Models\ApiClient::SCOPE_LICENSE_VALIDATE, 'api.rate_limit']);
    
    Route::get('/{license_key}', [LicenseValidationController::class, 'show'])
        ->middleware(['api.auth:' . \App\Models\ApiClient::SCOPE_LICENSE_READ, 'api.rate_limit'])
        ->where('license_key', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
});