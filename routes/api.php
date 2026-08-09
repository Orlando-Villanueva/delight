<?php

use App\Http\Controllers\Api\V1\MobileBootstrapController;
use App\Http\Controllers\Api\V1\MobileTokenController;
use App\Http\Controllers\Api\V1\ReadingLogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/token', [MobileTokenController::class, 'store'])
        ->middleware('throttle:mobile-login')
        ->name('auth.token.store');

    Route::middleware(['auth:sanctum', 'abilities:mobile'])->group(function (): void {
        Route::delete('/auth/token', [MobileTokenController::class, 'destroy'])
            ->name('auth.token.destroy');

        Route::get('/bootstrap', MobileBootstrapController::class)
            ->name('bootstrap');

        Route::post('/reading-logs', [ReadingLogController::class, 'store'])
            ->name('reading-logs.store');

        Route::get('/reading-logs', [ReadingLogController::class, 'index'])
            ->name('reading-logs.index');
    });
});
