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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/send-verification-code', [VerificationController::class, 'sendVerificationCode']);
Route::post('/verify-code', [VerificationController::class, 'verifyCode']);
Route::post('/create-account', [UserController::class, 'createAccount']);
Route::post('/login', [UserController::class, 'login']);




Route::post('/contacts/check', [UserController::class, 'checkContacts'])->name('contacts.check');
Route::post('/transaction/transfer', [TransactionController::class, 'transfer'])->name('transaction.transfer');
Route::post('/transaction/withdraw', [TransactionController::class, 'withdraw'])->name('transaction.withdraw');
Route::get('/transactions', [TransactionController::class, 'listTransactions'])->name('transactions.list');

