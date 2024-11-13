<?php

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
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

Route::post('/send-verification-code', [VerificationController::class, 'sendVerificationCode']);
Route::post('/verify-code', [VerificationController::class, 'verifyCode']);
Route::post('/create-account', [UserController::class, 'createAccount']);
Route::post('/login', [UserController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/transfer', [UserController::class, 'transfer'])->name('transfer');
    Route::get('/profile', [UserController::class, 'getProfile']);  
    Route::post('/checkcontact', [UserController::class, 'checkContacts']);
    Route::post('/multiple-transfer', [TransactionController::class, 'multipleTransfer']); 

});