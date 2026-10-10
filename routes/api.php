<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SalonSettingController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StylistController;
use App\Http\Controllers\Api\WaitlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| On Laravel 11+, run `php artisan install:api` once to generate this
| file's registration in bootstrap/app.php and pull in Sanctum. Then
| replace the generated routes/api.php with this file.
*/

// Public - the landing page reads these before anyone logs in.
Route::get('/settings', [SalonSettingController::class, 'show']);
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/stylists', [StylistController::class, 'index']);
Route::get('/reviews', [ReviewController::class, 'index']);

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:6,1');
Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);

    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update']);
    });

    Route::get('/clients', [ClientController::class, 'index']);

    Route::post('/stylists', [StylistController::class, 'store']);
    Route::patch('/stylists/{stylist}', [StylistController::class, 'update']);

    Route::post('/services', [ServiceController::class, 'store']);
    Route::patch('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'store']);
    Route::patch('/inventory/{inventoryItem}', [InventoryController::class, 'update']);

    Route::patch('/settings', [SalonSettingController::class, 'update']);

    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::patch('/reviews/{review}', [ReviewController::class, 'update']);

    Route::get('/waitlist', [WaitlistController::class, 'index']);
    Route::post('/waitlist', [WaitlistController::class, 'store']);
    Route::delete('/waitlist/{waitlistEntry}', [WaitlistController::class, 'destroy']);

    Route::get('/audit-log', [AuditLogController::class, 'index']);
});
