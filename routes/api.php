<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MpesaTheController;
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

Route::prefix('mobile')->group(function () {

    Route::match(['get', 'post'], '/stkpush/callback/{unique_number?}', [MpesaTheController::class, 'handleSTKPushCallback'])->name('stkpush.callback');
    Route::post('/pay/validation', [MpesaTheController::class, 'validationRequest'])->name('mpesa.pay.validation');
    Route::post('/pay/stk_confirmation', [MpesaTheController::class, 'handleC2BPayment'])->name('mpesa.pay.confirmation');

});