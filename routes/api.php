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

        // Transaction Status Result Callback
    Route::post('/status/result', [MpesaTheController::class, 'handleTransactionStatusResult'])
        ->name('mpesa.status.result')
        ->middleware('safaricom.ip');

    // Transaction Status Timeout Callback
    Route::post('/status/timeout', [MpesaTheController::class, 'handleTransactionStatusTimeout'])
        ->name('mpesa.status.timeout')
        ->middleware('safaricom.ip');
});

 