<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return response()->json(['status' => 'ok']);
});

use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/user', [AuthController::class, 'user']);
Route::get('/logout', [AuthController::class, 'logout']);
Route::get('/logout-all', [AuthController::class, 'logoutAll']);
Route::post('/two-factor-auth', [AuthController::class, 'twoFactorAuth']);
Route::post('/two-factor-auth-setup/{step}', [AuthController::class, 'twoFactorAuthSetup']);
Route::post('/two-factor-auth-verify', [AuthController::class, 'twoFactorAuthVerify']);
Route::get('/two-factor-auth-status', [AuthController::class, 'twoFactorAuthStatus']);
Route::get('/two-factor-auth-request', [AuthController::class, 'twoFactorAuthRequest']);
Route::post('/two-factor-auth-request-accept', [AuthController::class, 'twoFactorAuthRequestAccept']);