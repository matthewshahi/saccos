<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MpesaTheController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

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

 