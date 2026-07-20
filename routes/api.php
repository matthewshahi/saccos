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
use App\Http\Controllers\Api\GuaranteeRequestController;
use App\Http\Controllers\Api\MpesaB2cCallbackController;


// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

/*
|--------------------------------------------------------------------------
| M-Pesa-related Routes
|--------------------------------------------------------------------------
*/

Route::prefix('mobile')->group(function () {

    Route::match(
        ['get', 'post'],
        '/stkpush/callback/{unique_number?}',
        [MpesaTheController::class, 'handleSTKPushCallback']
    )
        ->name('stkpush.callback')
        ->middleware('safaricom.ip');

    Route::post(
        '/pay/validation',
        [MpesaTheController::class, 'validationRequest']
    )
        ->name('mpesa.pay.validation')
        ->middleware('safaricom.ip');

    Route::post(
        '/pay/stk_confirmation',
        [MpesaTheController::class, 'handleC2BPayment']
    )
        ->name('mpesa.pay.confirmation')
        ->middleware('safaricom.ip');

    /*
    |--------------------------------------------------------------------------
    | Transaction Status Query Callbacks
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/status/result',
        [
            PaymentInquiryController::class,
            'handleTransactionStatusResult',
        ]
    )
        ->name('mpesa.status.result')
        ->middleware('safaricom.ip');

    Route::post(
        '/status/timeout',
        [
            PaymentInquiryController::class,
            'handleTransactionStatusTimeout',
        ]
    )
        ->name('mpesa.status.timeout')
        ->middleware('safaricom.ip');

    /*
    |--------------------------------------------------------------------------
    | M-Pesa B2C Loan Disbursement Callbacks
    |--------------------------------------------------------------------------
    |
    | These routes receive asynchronous B2C responses from Safaricom.
    |
    | Result:
    | Safaricom sends the final success or failure response.
    |
    | Timeout:
    | Safaricom sends this when the transaction result cannot be delivered
    | or the request processing outcome is uncertain.
    |
    */

    Route::post(
        '/b2c/result',
        [MpesaB2cCallbackController::class, 'result']
    )
        ->name('mpesa.b2c.result')
        ->middleware([
            'throttle:120,1',
            'safaricom.ip',
        ]);

    Route::post(
        '/b2c/timeout',
        [MpesaB2cCallbackController::class, 'timeout']
    )
        ->name('mpesa.b2c.timeout')
        ->middleware([
            'throttle:120,1',
            'safaricom.ip',
        ]);
});


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::post(
    '/auth/login',
    [AuthController::class, 'login']
)
    ->middleware('throttle:5,1');

Route::post(
    '/auth/refresh',
    [AuthController::class, 'refresh']
)
    ->middleware('throttle:10,1');


/*
|--------------------------------------------------------------------------
| Authenticated Member Dashboard Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth.api'])->group(function () {

    Route::get(
        '/auth/dashboard',
        [MemberDashboardController::class, 'index']
    );

    // Share Savings
    Route::get(
        '/auth/savings',
        [MemberDashboardController::class, 'savings']
    );

    // FOSA Savings
    Route::get(
        '/auth/fosa',
        [MemberDashboardController::class, 'fosaSavings']
    );

    // Loans
    Route::get(
        '/auth/loans',
        [MemberDashboardController::class, 'loans']
    );

    Route::get(
        '/auth/profile',
        [MemberDashboardController::class, 'profile']
    );
});


/*
|--------------------------------------------------------------------------
| Guarantee Requests
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth.api',
    'throttle:30,1',
])
    ->prefix('auth/guarantee-requests')
    ->group(function () {

        // Pending requests for the logged-in guarantor
        Route::get(
            '/',
            [GuaranteeRequestController::class, 'index']
        );

        // Small summary for dashboard alert/badge
        Route::get(
            '/summary',
            [GuaranteeRequestController::class, 'summary']
        );

        // Optional single request details
        Route::get(
            '/{id}',
            [GuaranteeRequestController::class, 'show']
        )
            ->whereNumber('id');

        // Approve request
        Route::post(
            '/{id}/approve',
            [GuaranteeRequestController::class, 'approve']
        )
            ->whereNumber('id');

        // Decline request
        Route::post(
            '/{id}/decline',
            [GuaranteeRequestController::class, 'decline']
        )
            ->whereNumber('id');
    });


/*
|--------------------------------------------------------------------------
| Loan Applications
|--------------------------------------------------------------------------
*/

Route::middleware(['auth.api'])
    ->prefix('auth/loan-applications')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Loan Products
        |--------------------------------------------------------------------------
        |
        | Returns the available loan products and their rules.
        |
        */

        Route::get(
            '/products',
            [LoanApplicationController::class, 'products']
        );

        /*
        |--------------------------------------------------------------------------
        | Loan Application Context
        |--------------------------------------------------------------------------
        |
        | Returns authenticated member details needed for the application UI,
        | including name, phone, SACCO ID and joining date.
        |
        */

        Route::get(
            '/context',
            [LoanApplicationController::class, 'context']
        );

        /*
        |--------------------------------------------------------------------------
        | Top-Up Eligible Loans
        |--------------------------------------------------------------------------
        |
        | Returns the member's outstanding loans that qualify for top-up.
        |
        */

        Route::get(
            '/topup-loans',
            [LoanApplicationController::class, 'topupLoans']
        );

        /*
        |--------------------------------------------------------------------------
        | Future: Submit Loan Application
        |--------------------------------------------------------------------------
        |
        | Route::post(
        |     '/apply',
        |     [LoanApplicationController::class, 'apply']
        | );
        |
        */

        /*
        |--------------------------------------------------------------------------
        | Future: View Member Loan Applications
        |--------------------------------------------------------------------------
        |
        | Route::get(
        |     '/',
        |     [LoanApplicationController::class, 'index']
        | );
        |
        */

        Route::post(
            '/apply',
            [LoanApplicationController::class, 'apply']
        );
    });


/*
|--------------------------------------------------------------------------
| Authenticated STK Push from Mobile Application
|--------------------------------------------------------------------------
*/

Route::middleware(['auth.api'])->group(function () {

    Route::post(
        '/auth/payments/stkpush',
        [MpesaApiTheController::class, 'initiateStkPushFromApp']
    );
});


/*
|--------------------------------------------------------------------------
| Public Registration
|--------------------------------------------------------------------------
*/

Route::prefix('public/registration')->group(function () {

    Route::get(
        '/meta',
        [PublicRegistrationApiController::class, 'meta']
    )
        ->middleware('throttle:60,1');

    Route::post(
        '/submit',
        [PublicRegistrationApiController::class, 'submit']
    )
        ->middleware('throttle:3,1');
});