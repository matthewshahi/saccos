<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MpesaTheController;
use App\Http\Controllers\PaymentInquiryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MemberDashboardController;
use App\Http\Controllers\Api\LoanApplicationController;


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


Route::middleware(['auth.api'])->prefix('auth/loan-applications')->group(function () {

    // Fetch available loan products for application
    Route::get('/products', [LoanApplicationController::class, 'products']);

    // (Future) Submit a loan application
    // Route::post('/apply', [LoanApplicationController::class, 'apply']);

    // (Future) View member’s loan applications
    // Route::get('/', [LoanApplicationController::class, 'index']);

});



