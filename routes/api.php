<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\UserController;
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


// Public authentication routes
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

// Routes requiring authentication
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // Auth routes
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/me', [LoginController::class, 'me']);
    
    // Debug routes (remove in production)
    Route::get('/debug/redis', [DebugController::class, 'testRedis']);
    Route::get('/debug/auth', [DebugController::class, 'testAuth']);
    Route::get('/debug/groups', [DebugController::class, 'testGroups']);
    Route::get('/debug/expenses/{group}', [DebugController::class, 'testExpenses']);
    
    // User routes
    Route::get('/users/search', [UserController::class, 'search']);
    Route::get('/users/invitations', [UserController::class, 'invitations']);
    
    // Group routes
    Route::apiResource('groups', GroupController::class);
    Route::post('/groups/{group}/members', [GroupController::class, 'addMember']);
    Route::post('/groups/{group}/accept', [GroupController::class, 'acceptInvitation']);
    Route::post('/groups/{group}/reject', [GroupController::class, 'rejectInvitation']);
    Route::get('/groups/{group}/balances', [GroupController::class, 'balances']);
    
    // Expense routes
    Route::apiResource('groups.expenses', ExpenseController::class);
    Route::post('/groups/{group}/expenses/{expense}/shares/{share}/pay', [ExpenseController::class, 'markShareAsPaid']);
    Route::post('/groups/{group}/expenses/{expense}/shares/{share}/partial-payment', [ExpenseController::class, 'recordPartialPayment']);
    
    // Payment history routes
    Route::get('/payment-history', [PaymentHistoryController::class, 'index']);
    Route::get('/groups/{group}/expenses/{expense}/shares/{share}/payments', [PaymentHistoryController::class, 'shareHistory']);
});
