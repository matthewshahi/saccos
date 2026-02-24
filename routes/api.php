<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MpesaTheController;
use App\Http\Controllers\PaymentInquiryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MemberDashboardController;
use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\MpesaApiTheController;
use App\Http\Controllers\Api\PublicRegistrationApiController;

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// M-Pesa-related routes
Route::prefix('mobile')->group(function () {
    Route::match(['get', 'post'], '/stkpush/callback/{unique_number?}', [MpesaTheController::class, 'handleSTKPushCallback'])
        ->name('stkpush.callback')
        ->middleware('safaricom.ip'); // Apply IP filtering middleware

    Route::post('/pay/validation', [MpesaTheController::class, 'validationRequest'])
        ->name('mpesa.pay.validation')
        ->middleware('safaricom.ip'); // Apply IP filtering middleware

    Route::post('/pay/stk_confirmation', [MpesaTheController::class, 'handleC2BPayment'])
        ->name('mpesa.pay.confirmation')
        ->middleware('safaricom.ip'); // Apply IP filtering middleware

    // ✅ Fix: Point to PaymentInquiryController
    Route::post('/status/result', [PaymentInquiryController::class, 'handleTransactionStatusResult'])
        ->name('mpesa.status.result')
        ->middleware('safaricom.ip');

    Route::post('/status/timeout', [PaymentInquiryController::class, 'handleTransactionStatusTimeout'])
        ->name('mpesa.status.timeout')
        ->middleware('safaricom.ip');
});


Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])
    ->middleware('throttle:10,1');

Route::middleware(['auth.api'])->group(function () {
    Route::get('/auth/dashboard', [MemberDashboardController::class, 'index']);

    // ✅ Share Savings
    Route::get('/auth/savings', [MemberDashboardController::class, 'savings']);

    // ✅ FOSA Savings
    Route::get('/auth/fosa', [MemberDashboardController::class, 'fosaSavings']);

    // ✅ Loans (NEW)
    Route::get('/auth/loans', [MemberDashboardController::class, 'loans']);
    Route::get('/auth/profile', [MemberDashboardController::class, 'profile']);
});


Route::middleware(['auth.api'])
    ->prefix('auth/loan-applications')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Loan Products (Catalog)
        |--------------------------------------------------------------------------
        | What loan types exist and their rules.
        | Stateless. Cacheable. No member state.
        */
        Route::get('/products', [
            LoanApplicationController::class,
            'products'
        ]);

        /*
        |--------------------------------------------------------------------------
        | Loan Application Context (Member Info)
        |--------------------------------------------------------------------------
        | Returns authenticated member details needed for application UI
        | (name, phone, sacco id, join date, etc.)
        */
        Route::get('/context', [
            LoanApplicationController::class,
            'context'
        ]);

        /*
        |--------------------------------------------------------------------------
        | Top-Up Eligible Loans
        |--------------------------------------------------------------------------
        | Returns member's outstanding loans eligible for top-up
        | (loan id, type, balance).
        */
        Route::get('/topup-loans', [
            LoanApplicationController::class,
            'topupLoans'
        ]);

        /*
        |--------------------------------------------------------------------------
        | (Future) Submit Loan Application
        |--------------------------------------------------------------------------
        | Route::post('/apply', [LoanApplicationController::class, 'apply']);
        */

        /*
        |--------------------------------------------------------------------------
        | (Future) View Member Loan Applications
        |--------------------------------------------------------------------------
        | Route::get('/', [LoanApplicationController::class, 'index']);
        */

        Route::post('/apply', [LoanApplicationController::class, 'apply']);
    });


Route::middleware(['auth.api'])->group(function () {

    Route::post(
        '/auth/payments/stkpush',
        [MpesaApiTheController::class, 'initiateStkPushFromApp']
    );
});


Route::prefix('public/registration')->group(function () {
    // ✅ Meta needed by mobile UI (paybill, fee, kin types)
    Route::get('/meta', [PublicRegistrationApiController::class, 'meta'])
        ->middleware('throttle:60,1');

    // ✅ Submit membership application (JSON only, no login)
    Route::post('/submit', [PublicRegistrationApiController::class, 'submit'])
        ->middleware('throttle:5,1');
});