<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Auth\CustomAuthController;
use App\Http\Controllers\LoanPerformanceController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\RandController;
use App\Http\Controllers\LoanEndMonthController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\PublicFileController;
use App\Http\Controllers\PublicLoansController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\PublicRegistrationActionsController;
use App\Http\Controllers\MemberDashboardController;
use App\Http\Controllers\MemberImportController;
use App\Http\Controllers\MpesaConfigController;
use App\Http\Controllers\MpesaTheController;
use App\Http\Controllers\MpesaReportController;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\PublicRegistrationActionsImportController;
// use App\Http\Controllers\TempCapitalImportController;
use App\Http\Controllers\LoanApplicationSelfServiceController;
use App\Http\Controllers\ReportLedgerController;
use App\Http\Controllers\LoanPaymentController;
use App\Http\Controllers\MemberReportController;
use App\Http\Controllers\KinTypeController;
// use App\Http\Controllers\LoanLedgerController;
use App\Http\Controllers\MemberAddImages;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportsShareController;
use App\Http\Controllers\PaymentInquiryController;
use App\Http\Controllers\LoanPDFController;
use App\Http\Controllers\FosaTypeController;
use App\Http\Controllers\FosaTransactionController;
use App\Http\Controllers\FosaImportController;
use App\Http\Controllers\FosaEndMonthController;
use App\Http\Controllers\SharesClearanceController;
use App\Http\Controllers\ShareTransactionController;
use App\Http\Controllers\CapitalShareTransactionController;
// use App\Http\Controllers\TxnImportController;
use App\Http\Controllers\RegistrationFeeController;
use App\Http\Controllers\TrialBalanceController;
use App\Http\Controllers\LoansActiveReportController;
use App\Http\Controllers\TempLoanCalController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\InsuranceLoanReportController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\LoanRepaymentImportController;
use App\Http\Controllers\ManualMpesaRecoveryController;
use App\Http\Controllers\FosaMembersReportController;
use App\Http\Controllers\MemberFinancialPositionController;
use App\Http\Controllers\FosaTransferController;
use App\Http\Controllers\Reports\FinalAccountsController;
use App\Http\Controllers\Reports\LedgerController;
use App\Http\Controllers\LoanDeductionTypeController;
use App\Http\Controllers\ShareReductionController;
use App\Http\Controllers\LoanReprocessController;
use App\Http\Controllers\KassMigrationController;
use App\Http\Controllers\KasMemberImportController;
use App\Http\Controllers\MigrationStatementCleanupController;
use App\Http\Controllers\KassContributionsImportController;

use App\Http\Controllers\KassMemberMasterImportController;

use App\Http\Controllers\PayrollDeductionsImportController;
use App\Http\Controllers\MpesaStatementReconciliationController;

use App\Http\Controllers\MinimumCapitalContributionController;
use App\Http\Controllers\Auth\MemberPasswordResetController;
use App\Http\Controllers\SpecialSavingController;

/* good imports
Route::prefix('kass')->group(function () {
    Route::get('/member-master-import', [KassMemberMasterImportController::class, 'index'])->name('kass.member_master_import.index');
    Route::post('/member-master-import/preview', [KassMemberMasterImportController::class, 'preview'])->name('kass.member_master_import.preview');
    Route::post('/member-master-import/confirm', [KassMemberMasterImportController::class, 'confirm'])->name('kass.member_master_import.confirm');
});


Route::get(
    '/kass/import/contributions',
    [KassContributionsImportController::class, 'index']
)->name('kass.import.contributions.form');

Route::post(
    '/kass/import/contributions',
    [KassContributionsImportController::class, 'import']
)->name('kass.import.contributions.run');
Route::get(
    '/kass/import/contributions/run',
    [KassContributionsImportController::class, 'run']
);


Route::prefix('kass_loan_imports')->name('kassloan.')->group(function () {

    // Dashboard
    Route::get('/', [\App\Http\Controllers\KassLoanImportController::class, 'index'])
        ->name('index');

    // Run full import
    Route::post('/run', [\App\Http\Controllers\KassLoanImportController::class, 'run'])
        ->name('run');

    // Clean tables for fresh import
    Route::post('/reset', [\App\Http\Controllers\KassLoanImportController::class, 'reset'])
        ->name('reset');
});

//use this for loans, the other one isnt good
Route::get('kass_loan_imports_loan_only', [\App\Http\Controllers\KassLoanOnlyImportController::class, 'run'])
        ->name('index_import');



Route::prefix('kas-member-import')->name('kasmember.')->group(function () {

    Route::get('/', [KasMemberImportController::class, 'index'])
        ->name('index');

    Route::post('/run', [KasMemberImportController::class, 'run'])
        ->name('run');   // ✅ FIXED

    Route::post('/upload', [KasMemberImportController::class, 'upload'])
        ->name('upload');

    Route::get('/process/start', [KasMemberImportController::class, 'start'])
        ->name('process.start');

    Route::get('/process/companies', [KasMemberImportController::class, 'processCompanies'])
        ->name('process.companies');

    Route::get('/process/departments', [KasMemberImportController::class, 'processDepartments'])
        ->name('process.departments');

    Route::get('/process/members', [KasMemberImportController::class, 'processMembers'])
        ->name('process.members');

    Route::get('/process/contributions', [KasMemberImportController::class, 'processContributions'])
        ->name('process.contributions');

    Route::get('/process/cleanup', [KasMemberImportController::class, 'cleanup'])
        ->name('process.cleanup');
});



Route::prefix('kass-migration')->group(function () {

    // Upload page
    Route::get('/', [KassMigrationController::class, 'index'])
        ->name('kass.index');

    // Upload one sheet
    Route::post('/upload', [KassMigrationController::class, 'uploadSingle'])
        ->name('kass.upload.single');

    // Upload a ZIP containing all company sheets
    Route::post('/upload-batch', [KassMigrationController::class, 'uploadBatch'])
        ->name('kass.upload.batch');

    // Process migration (parse → normalize → staging)
    Route::post('/process', [KassMigrationController::class, 'process'])
        ->name('kass.process');

    // View staging results
    Route::get('/staging', [KassMigrationController::class, 'staging'])
        ->name('kass.staging');

    // Clear staging tables
    Route::delete('/staging/clear', [KassMigrationController::class, 'clearStaging'])
        ->name('kass.staging.clear');

    Route::post('/kass/process-all', [KassMigrationController::class, 'processAll'])
        ->name('kass.process.all');



    // 🚀 PROCESS ALL FILES (GET) — THIS ONE WAS MISSING
    Route::get('/kass/process-all', [KassMigrationController::class, 'processAll'])
        ->name('kass.process.all');

    Route::get('/kass/preview', [KassMigrationController::class, 'preview'])
        ->name('kass.preview');
});

*/
// use App\Http\Controllers\LedgerRebuildController;

// Route::get('/rebuild/ledgers', [LedgerRebuildController::class, 'rebuild'])
//     ->name('rebuild.ledgers')
//     ->middleware('check_user_rights:rpt_acc_trans');

// use App\Http\Controllers\TxnImportController;

// // Upload form
// Route::get('/transactions/import', [TxnImportController::class, 'showForm'])
//     ->name('transactions.import.form');

// // // Process CSV and import
// Route::post('/transactions/import', [TxnImportController::class, 'import'])
//     ->name('transactions.import');


// use App\Http\Controllers\ReportsShareController;

Route::get('/members/update-totals', action: [\App\Http\Controllers\MemberTotalsController::class, 'recalculateAll'])->name(name: 'members.recalculate.totals');

// routes/web.php (or your admin routes file)
// Route::get(
//     '/members/import-reconciliations',
//     action: [\App\Http\Controllers\MemberShareCapReconciliation::class, 'importReconciliations']
// )->name(name: 'members.import.reconciliations');

// // Route::get('/members/import-transactions', [MemberImportController::class, 'showImportTransactionsForm'])->name('members.import.transactions.form');
// // Route::post('/members/import-transactions', [MemberImportController::class, 'importSavingsAndShares'])->name('members.import.transactions');


//  use App\Http\Controllers\MemberImportController;

// Route::get('/import/members', [MemberImportController::class, 'showImportForm'])->name('import.members.form');
// Route::post('/import/members', [MemberImportController::class, 'import'])->name('import.members.run');

// Route::get('/import/shares', [MemberImportController::class, 'showSharesImportForm'])
//     ->name('import.shares.form');
// Route::post('/import/shares', [MemberImportController::class, 'importShares'])
//     ->name('import.shares.run');

//     // Loans (Taken) import
// Route::get('/import/loans/taken', [MemberImportController::class, 'showLoansTakenImportForm'])
//     ->name('import.loans.taken.form');

// Route::post('/import/loans/taken', [MemberImportController::class, 'importLoansTaken'])
//     ->name('import.loans.taken.run');

// // Loans (Taken) import
// Route::get('/import/loans/taken', [MemberImportController::class, 'showLoansTakenImportForm'])
//     ->name('import.loans.taken.form');

// Route::post('/import/loans/taken', [MemberImportController::class, 'importLoansTaken'])
//     ->name('import.loans.taken.run');


// use App\Http\Controllers\RoamWelfareImportController;

// Route::get('/welfare/import', [RoamWelfareImportController::class, 'showImportForm'])->name('roamwelfare.form');
// Route::post('/welfare/import', [RoamWelfareImportController::class, 'import'])->name('roamwelfare.import');

// Route::get('/members/import', [MemberImportController::class, 'showImportForm'])->name('members.import.form');
// Route::post('/members/import', [MemberImportController::class, 'import'])->name('members.import');


// // Show patch form
// Route::get('/members/patch', [MemberImportController::class, 'showPatchForm'])
//     ->name('members.patch.form');

// // // Handle patch CSV upload and update members
// Route::post('/members/patch', [MemberImportController::class, 'patch'])
//     ->name('members.patch');

// Route::view('/members/import-kin-form', 'import.next_of_kin')->name('members.import.kin.form');
// Route::post('/members/import-next-of-kin', [MemberImportController::class, 'importNextOfKin'])->name('members.import.kin');
//=============

Route::get('/home', [HomeController::class, 'redirectBasedOnAuth'])->name('home');
Route::get('/', [HomeController::class, 'redirectBasedOnAuth'])->name('home1');

Route::get('login', [CustomAuthController::class, 'showLoginForm'])->name('login');
Route::post('login', [CustomAuthController::class, 'login']);
Route::post('logout', [CustomAuthController::class, 'logout'])->name('logout');



Route::get('/forgot-password', [MemberPasswordResetController::class, 'showForgotForm'])
    ->name('member.password.request');

Route::post('/forgot-password', [MemberPasswordResetController::class, 'sendResetInstructions'])
    ->name('member.password.email')
    ->middleware('throttle:6,1');

Route::get('/reset-password/{token}', [MemberPasswordResetController::class, 'showResetForm'])
    ->name('member.password.reset');

Route::post('/reset-password', [MemberPasswordResetController::class, 'resetPassword'])
    ->name('member.password.update')
    ->middleware('throttle:6,1');

Route::get('/loans/calculator', [HomeController::class, 'loansCalculator'])->name('loans.calculator');

Route::get('/register', [PublicRegistrationController::class, 'showForm'])->name('register.form');
Route::post('/register', [PublicRegistrationController::class, 'submit'])->name('register.submit');
Route::get('/register/{code}', [PublicRegistrationController::class, 'completeRegistrationForm'])->name('register.unique');
Route::post('/register/complete', [PublicRegistrationController::class, 'completeSubmit'])->name('register.complete.submit');


Route::get('/loans/types/list', [PublicLoansController::class, 'loansTypesList'])->name('loans.types.list');
// Route::get('/loans/types/list', [PublicLoansController::class, 'loansTypesList'])->name('loans.types.list');
Route::get('/public/loans/types/list', [PublicLoansController::class, 'loansTypesList'])->name('loans.types.list1');
Route::get('/public/loans/details/{id}', [PublicLoansController::class, 'loanDetails'])->name('loan.details');
Route::get('/public/loan-calculator/{id}', [PublicLoansController::class, 'showLoanCalculator'])->name('loan.calculator');
Route::post('/public/loan-calculator/{id}/calculate', [PublicLoansController::class, 'calculateLoan'])->name('loan.calculate');


Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard/member_dashboard', [MemberDashboardController::class, 'index'])->name('member_dashboard');
    Route::get('contributions/member-shares', [MemberDashboardController::class, 'shareListings'])->name('member.sharelistings');
    Route::get('/contributions/member-capital', [MemberDashboardController::class, 'capitalListings'])->name('capital.listings');
    Route::get('/contributions/fosa', [MemberDashboardController::class, 'fosaListings'])->name('fosa.listings');
    Route::get('/loans/taken', [MemberDashboardController::class, 'loansTaken'])->name('loans.taken');

    // Member statement route for non-officials
    Route::get('/members/statement/self', [HomeController::class, 'viewStatement']);

    Route::get('/members/status/{self}', [HomeController::class, 'memberStatus']);
    Route::get('/members/status/{id}', [HomeController::class, 'memberStatus'])->name('members.status')->middleware('check_user_rights:rpt_loans_issued');

    // Route::get('/loans/apply', [HomeController::class, 'loansApply'])->name('loans.apply');
    // Route::post('/loans/apply', [HomeController::class, 'submitLoanApplication'])->name('loans.application.submit');
    Route::get('/loans/apply', [LoanApplicationSelfServiceController::class, 'loansApply'])
        ->name('loans.apply');

    Route::post('/loans/apply', [LoanApplicationSelfServiceController::class, 'submitLoanApplication'])
        ->name('loans.application.submit');

    Route::get('loans/pending/approval/self', [LoanApplicationSelfServiceController::class, 'listLoansPendingApprovalSelf'])
        ->name('loans.pending.approval.self');
    Route::get('loans/pending/approval/selfedit/{id}', [LoanApplicationSelfServiceController::class, 'listLoansPendingApprovalSelfedit'])
        ->name('loans.pending.approval.selfedit');
    Route::post('/loans/process-application', [LoanApplicationSelfServiceController::class, 'processLoanApplication'])
        ->name('loans.process.application');
    Route::put('loans/application/update/{id}', [LoanApplicationSelfServiceController::class, 'updateLoanApplication'])
        ->name('loans.application.update');
    Route::delete('/loans/delete-guarantor/{id}', [LoanApplicationSelfServiceController::class, 'deleteGuarantor'])->name('loans.delete.guarantor');
    Route::post('/loans/add-guarantor/{id}', [LoanApplicationSelfServiceController::class, 'addGuarantor'])->name('loans.add.guarantor');
    Route::get('/search/guarantors', [LoanApplicationSelfServiceController::class, 'search'])->name('search.guarantors');

    Route::get('/search/members', [HomeController::class, 'searchMembers'])->name('search.members');


    Route::get('/loans/guarantee/requests', [HomeController::class, 'listGuaranteeRequests'])->name('loans.guarantee.requests');
    // Route::get('/loans/pending/approval', [HomeController::class, 'listLoansPendingApproval'])->name('loans.pending.approval')->middleware('check_user_rights:rpt_loans_issued');
    // Route::get('/loans/types/list', [HomeController::class, 'loansTypesList'])->name('loans.types.list');

    // routes/web.php
    Route::get('loans/pending/pdf/{loanId}', [LoanPDFController::class, 'downloadLoanForm'])
        ->name('loans.pending.getPDF');


    Route::get('/profile/password', [HomeController::class, 'showChangeSelfPasswordForm'])->name('profile.password');
    Route::post('/profile/password', [HomeController::class, 'updateSelfPassword'])->name('profile.updatePassword');

    Route::get('/downloads', [PublicFileController::class, 'publicDownloads'])->name('public.downloads');
    Route::get('/downloads/{file}/download', [PublicFileController::class, 'downloadFile'])->name('public.download.file');

    Route::get('/members/juniors/create', [MemberController::class, 'createJunior'])->name('members.juniors.create');
    Route::post('/members/juniors/store', [MemberController::class, 'storeJunior'])->name('members.juniors.store');

    Route::get('/check-payment', [PaymentInquiryController::class, 'index'])->name('payment.check');
    Route::post('/check-payment', [PaymentInquiryController::class, 'check'])->name('payment.check.submit');

    Route::post(
        '/transport/operators/add-from-dashboard',
        [\App\Http\Controllers\Transport\OperatorDashBoardSlefAddController::class, 'addFromDashboard']
    )->name('operators.addFromDashboard');
});


Route::prefix('mobile')->group(function () {

    // Route::post('/pay/validation', [MpesaTheController::class, 'validationRequest'])->name('mpesa.pay.validation');
    // Route::post('/pay/stk_confirmation', [MpesaTheController::class, 'handleC2BPayment'])->name('mpesa.pay.confirmation');
    // Route::post('/stkpush/callback', [MpesaTheController::class, 'handleSTKPushCallback'])->name('stkpush.callback');
    // Route::post('/stkpush/callback/{unique_number?}', [MpesaTheController::class, 'handleSTKPushCallback'])->name('stkpush.callback');
    // Route::match(['get', 'post'], '/stkpush/callback/{unique_number?}', [MpesaTheController::class, 'handleSTKPushCallback'])->name('stkpush.callback');

    Route::middleware(['auth'])->group(function () {
        Route::get('/stkpush/{unique_number?}', [MpesaTheController::class, 'showSTKPushForm'])->name('stkpush.form'); // Show STK Push form
        Route::post('/stkpush{unique_number?}', [MpesaTheController::class, 'storeStkPush'])->name('stkpush.store');
        Route::get('/stk/check/{id}', [MpesaTheController::class, 'checkPayment'])->name('stkpush.check'); // Check payment status
        //Route::get('/payment/success', [MpesaTheController::class, 'paymentSuccess'])->name('payment.success'); // Payment success
        Route::get('/payment/success/{unique_number?}', [MpesaTheController::class, 'paymentSuccess'])->name('payment.success');
        Route::get('/payment/failed/{unique_number?}', [MpesaTheController::class, 'paymentFailed'])->name('payment.failed');
        //Route::get('/payment/failed', [MpesaTheController::class, 'paymentFailed'])->name('payment.failed'); // Payment failure (optional)
        Route::post('/payment-status', [MpesaTheController::class, 'checkStatus'])->name('payment.status');
        Route::get('/stk/wait/{checkoutRequestId}', [MpesaTheController::class, 'waitForPayment'])->name('stkpush.wait');
    });


    Route::middleware(['auth', 'check_member_position'])->group(function () {
        Route::get('/register-urls', [MpesaTheController::class, 'registerUrls'])->name('mpesa.register.urls')->middleware('check_user_rights:mpesa_admin'); // Register URLs for validation/confirmation


        Route::prefix('config')->group(function () {
            Route::get('/', [MpesaConfigController::class, 'index'])->name('mpesa_config.index')->middleware('check_user_rights:mpesa_admin');; // List all configurations
            Route::get('/create', [MpesaConfigController::class, 'create'])->name('mpesa_config.create')->middleware('check_user_rights:mpesa_admin');; // Show create form
            Route::post('/store', [MpesaConfigController::class, 'store'])->name('mpesa_config.store')->middleware('check_user_rights:mpesa_admin');; // Store new configuration
            Route::get('/edit/{id}', [MpesaConfigController::class, 'edit'])->name('mpesa_config.edit')->middleware('check_user_rights:mpesa_admin');; // Show edit form
            Route::post('/update/{id}', [MpesaConfigController::class, 'update'])->name('mpesa_config.update')->middleware('check_user_rights:mpesa_admin');; // Update configuration
        });
    });
});


Route::middleware(['auth', 'check_member_position'])->group(function () {


    Route::prefix('minimum-capital')
        ->name('minimum_capital.')
        ->middleware('check_user_rights:transfer_member_shares')
        ->group(function () {
            Route::get('/', [MinimumCapitalContributionController::class, 'index'])
                ->name('index');

            Route::post('/process', [MinimumCapitalContributionController::class, 'process'])
                ->name('process');
        });

    Route::prefix('payroll-deductions-import')
        ->name('payroll.deductions.import.')
        ->middleware('check_user_rights:end_month_processing_loans')
        ->group(function () {

            // 1. Upload form
            Route::get('/', [PayrollDeductionsImportController::class, 'index'])
                ->name('index');

            // 2. Upload Excel and validate/preview
            Route::post('/preview', [PayrollDeductionsImportController::class, 'preview'])
                ->name('preview');

            // 3. Confirm and process final import
            Route::post('/process', [PayrollDeductionsImportController::class, 'process'])
                ->name('process');

            // 4. Cancel/clear current preview session
            Route::post('/cancel', [PayrollDeductionsImportController::class, 'cancel'])
                ->name('cancel');

            // 5. Download sample template later, optional
            Route::get('/sample', [PayrollDeductionsImportController::class, 'sample'])
                ->name('sample');
        });
});


Route::middleware(['auth', 'check_member_position'])->group(function () {

    Route::post('/loans/selfservice/{id}/adjust-charges', [LoanApplicationSelfServiceController::class, 'saveAdjustedCharges'])
        ->name('loans.selfservice.adjust.charges.save')->middleware('check_user_rights:adjust_loan_charges');


    // Show manual recovery form
    Route::get(
        '/mpesa/manual-recovery',
        [ManualMpesaRecoveryController::class, 'showForm']
    )->name('mpesa.manual.form')->middleware('check_user_rights:add_mpesa_manual_recovery');

    // Validate SMS message and show preview
    Route::post(
        '/mpesa/manual-recovery/validate',
        [ManualMpesaRecoveryController::class, 'validateSms']
    )->name('mpesa.manual.validate')->middleware('check_user_rights:add_mpesa_manual_recovery');

    // Final approve & post transaction
    Route::post(
        '/mpesa/manual-recovery/process',
        [ManualMpesaRecoveryController::class, 'processSms']
    )->name('mpesa.manual.process')->middleware('check_user_rights:add_mpesa_manual_recovery');
});


// Route::post('/mpesa/pay/validation', [MpesaTheController::class, 'validationRequest'])->name('mpesa.pay.validation');
// Route::post('/mpesa/pay/confirmation', [MpesaTheController::class, 'handleC2BPayment'])->name('mpesa.pay.confirmation');
// Route::post('/mpesa/stkpush/callback', [MpesaController::class, 'handleSTKPushCallback'])->name('stkpush.callback');

// Route::middleware(['auth'])->group(function () {
//     Route::get('/mpesa/stkpush', [MpesaTheController::class, 'showSTKPushForm'])->name('stkpush.form');
//     Route::post('/mpesa/stkpush', [MpesaController::class, 'storeStkPush'])->name('stkpush.store');
//     Route::post('/mpesa/register-urls', [MpesaTheController::class, 'registerUrls'])->name('mpesa.register.urls');
//     Route::get('/mpesa/payment/success', [MpesaController::class, 'paymentSuccess'])->name('payment.success');
//     Route::get('/mpesa/payment/failed', [MpesaController::class, 'paymentFailed'])->name('payment.failed');
// });



// Route::prefix('mpesa/config')->middleware(['auth', 'check_member_position'])->group(function () {
//     Route::get('/', [MpesaConfigController::class, 'index'])->name('mpesa_config.index');
//     Route::get('/create', [MpesaConfigController::class, 'create'])->name('mpesa_config.create');
//     Route::post('/store', [MpesaConfigController::class, 'store'])->name('mpesa_config.store');
//     Route::get('/edit/{id}', [MpesaConfigController::class, 'edit'])->name('mpesa_config.edit');
//     Route::post('/update/{id}', [MpesaConfigController::class, 'update'])->name('mpesa_config.update');
// });

// Route::get('/import/members', [MemberImportController::class, 'showForm'])->name('import.members.form'); //->middleware('check_user_rights:new_member_applications_updateXXX');
// Route::post('/import/members', [MemberImportController::class, 'import'])->name('import.members.process'); //->middleware('check_user_rights:new_member_applications_updateXXX');
// Route::get('/update/ledgers', [MemberImportController::class, 'updatefLedgers'])->name('update.ledgers');


Route::middleware(['auth', 'check_member_position'])->group(function () {



    Route::get('/mpesa/statement-reconciliation', [MpesaStatementReconciliationController::class, 'index'])
        ->name('mpesa.statement.reconciliation.index')->middleware('check_user_rights:mpesa-reconcile');

    Route::post('/mpesa/statement-reconciliation', [MpesaStatementReconciliationController::class, 'store'])
        ->name('mpesa.statement.reconciliation.store')->middleware('check_user_rights:mpesa-reconcile');


    Route::prefix('loans/reprocess')
        ->name('loans.reprocess.')
        ->middleware(['auth', 'check_member_position', 'check_user_rights:end_month_processing_loans'])
        ->group(function () {

            Route::get('/', [LoanReprocessController::class, 'index'])
                ->name('index');

            Route::post('/recalculate-loan-paid', [LoanReprocessController::class, 'recalculateLoanPaid'])
                ->name('recalculate-loan-paid');

            Route::post('/reset-guarantors', [LoanReprocessController::class, 'resetGuarantors'])
                ->name('reset-guarantors');

            Route::post('/update-member-loan-balances', [LoanReprocessController::class, 'updateMemberLoanBalances'])
                ->name('update-member-loan-balances');

            Route::post('/run-all', [LoanReprocessController::class, 'runAll'])
                ->name('run-all');
        });



    // 📊 Main Insurance Loan Report (view + filter)
    Route::get('/reports/loans/insurance', [InsuranceLoanReportController::class, 'index'])
        ->name('reports.loans.insurance')
        ->middleware('check_user_rights:rpt_loans_insurance');

    // 📤 Export to CSV
    Route::get('/reports/loans/insurance/export', [InsuranceLoanReportController::class, 'export'])
        ->name('reports.loans.insurance.export')
        ->middleware('check_user_rights:rpt_loans_insurance');

    Route::get('/members/mass-password-reset', [PasswordResetController::class, 'confirm'])
        ->name('members.mass_password_reset.confirm')
        ->middleware('check_user_rights:mass_password_reset');

    Route::post('/members/mass-password-reset', [PasswordResetController::class, 'execute'])
        ->name('members.mass_password_reset.execute')
        ->middleware('check_user_rights:mass_password_reset');



    Route::get('/emails/bulk', [EmailController::class, 'index'])
        ->name('emails.bulk')
        ->middleware('check_user_rights:bulk_emails');


    Route::get('/fosa/transactions/export', [FosaTransactionController::class, 'export'])
        ->name('fosa.transactions.export')
        ->middleware('check_user_rights:fosa_transactions_export');

    Route::post('/emails/bulk/send', [EmailController::class, 'sendBulk'])
        ->name('emails.bulk.send')
        ->middleware('check_user_rights:bulk_emails_send');

    Route::get('/proc/recalc/loans', [TempLoanCalController::class, 'recalcAllLoansAndMembers'])
        ->name('proc.recalc.loans.process')
        ->middleware('check_user_rights:recalc_loans');

    Route::prefix('reports/loans/active')
        ->middleware(['auth', 'check_user_rights:LoansReport']) // 👈 adjust to your rights string
        ->group(function () {
            Route::get('/', [LoansActiveReportController::class, 'index'])
                ->name('reports.loans.active.index');

            // Inline updates (restrict more tightly)
            Route::patch('/{loan}/principal', [LoansActiveReportController::class, 'updateMonthlyPrincipal'])
                ->name('reports.loans.active.updatePrincipal')
                ->middleware('check_user_rights:LoansReportEdit');

            Route::patch('/{loan}/amount', [LoansActiveReportController::class, 'updateMonthlyAmount'])
                ->name('reports.loans.active.updateAmount')
                ->middleware('check_user_rights:LoansReportEdit');

            Route::get('/export/{format}', [LoansActiveReportController::class, 'export'])
                ->name('reports.loans.active.export')
                ->middleware(['check_user_rights:LoansReportView']);
        });


    Route::middleware(['check_user_rights:RegistrationFees'])
        ->prefix('registration-fees')
        ->group(function () {
            // 📋 Listing page
            Route::get('/', [RegistrationFeeController::class, 'index'])
                ->name('registrationfees.index');

            // ➕ Create
            Route::get('/create', [RegistrationFeeController::class, 'create'])
                ->name('registrationfees.create');

            // 💾 Store
            Route::post('/store', [RegistrationFeeController::class, 'store'])
                ->name('registrationfees.store');

            // 🧾 Receipt
            Route::get('/{id}/receipt', [RegistrationFeeController::class, 'receipt'])
                ->name('registrationfees.receipt');
        });
    // ========================
    // Shares Clearance Module
    // ========================
    Route::prefix('shares-clearance')
        ->middleware(['check_user_rights:SharesClearance'])
        ->group(function () {

            // 1️⃣ Listing/Search page (searchable members, max 20 results)
            Route::get('/', [SharesClearanceController::class, 'index'])
                ->name('shares.clearance.index');

            // 2️⃣ Search API (AJAX smart search for members)
            Route::get('/search', [SharesClearanceController::class, 'searchMembers'])
                ->name('shares.clearance.search');

            // 3️⃣ Get loans for a member (modal load)
            Route::get('/{member}/loans', [SharesClearanceController::class, 'getLoans'])
                ->name('shares.clearance.loans');

            // 4️⃣ Process clearance (apply selected shares to loans)
            Route::post('/process', [SharesClearanceController::class, 'process'])
                ->name('shares.clearance.process');
        });


    Route::prefix('fosa-types')
        ->middleware('check_user_rights:FosaTypesEdit')
        ->group(function () {
            Route::get('/', [FosaTypeController::class, 'index'])->name('fosa.index');
            Route::get('/create', [FosaTypeController::class, 'create'])->name('fosa.create');
            Route::post('/store', [FosaTypeController::class, 'store'])->name('fosa.store');
            Route::post('/{id}/toggle', [FosaTypeController::class, 'toggle'])->name('fosa.toggle');
            Route::get('/{id}/edit', [FosaTypeController::class, 'edit'])->name('fosa.edit');
            Route::post('/{id}/update', [FosaTypeController::class, 'update'])->name('fosa.update');
        });


    // Route::middleware(['check_user_rights:FosaTransactions'])
    //     ->prefix('fosa')
    //     ->group(function () {
    //         Route::get('/transactions', [FosaTransactionController::class, 'index'])->name('fosa.transactions.index');
    //         Route::get('/transactions/create', [FosaTransactionController::class, 'create'])->name('fosa.transactions.create');
    //         Route::post('/transactions/store', [FosaTransactionController::class, 'store'])->name('fosa.transactions.store');
    //         Route::get('/transactions/{id}/receipt', [FosaTransactionController::class, 'receipt'])->name('fosa.transactions.receipt');
    //     });



    Route::middleware(['check_user_rights:FosaTransactions'])->group(function () {
        Route::get('/api/members/search', [FosaTransactionController::class, 'searchMembers'])
            ->name('api.members.search');

        Route::get('/api/accounts/search', [FosaTransactionController::class, 'searchAccounts'])
            ->name('api.accounts.search');
    });


    Route::middleware(['check_user_rights:FosaTransactions'])
        ->prefix('fosa')
        ->group(function () {
            Route::get('/transactions', [FosaTransactionController::class, 'index'])
                ->name('fosa.transactions.index');

            Route::get('/transactions/create', [FosaTransactionController::class, 'create'])
                ->name('fosa.transactions.create');

            Route::post('/transactions/store', [FosaTransactionController::class, 'store'])
                ->name('fosa.transactions.store');

            Route::get('/transactions/{id}/receipt', [FosaTransactionController::class, 'receipt'])
                ->name('fosa.transactions.receipt');

            // 📌 Import routes
            Route::get('/transactions/import', [FosaImportController::class, 'showForm'])
                ->name('fosa.transactions.import');
            Route::get('/transactions/import/preview/{csv}', [FosaImportController::class, 'showPreview'])
                ->name('fosa.transactions.import.preview.show');

            Route::post('/transactions/import/preview', [FosaImportController::class, 'preview'])
                ->name('fosa.transactions.import.preview');


            Route::get('/transactions/import/preview/{csv}', [FosaImportController::class, 'showPreview'])
                ->name('fosa.transactions.import.preview.show');

            Route::put('/transactions/{id}/type', [FosaTransactionController::class, 'updateType'])
                ->name('fosa.transactions.updateType');
        });

    Route::post('/transactions/import/process', [FosaImportController::class, 'process'])
        ->name('fosa.transactions.import.process'); // ✅ Added

    Route::get('/transactions/import/preview', function () {
        return redirect()->route('fosa.transactions.import')
            ->with('error', 'Please upload a CSV file first.');
    });



    Route::middleware(['check_user_rights:ShareTransactions'])
        ->prefix('shares')
        ->group(function () {
            Route::get('/transactions', [ShareTransactionController::class, 'index'])
                ->name('shares.transactions.index');

            Route::get('/transactions/{id}/receipt', [ShareTransactionController::class, 'receipt'])
                ->name('shares.transactions.receipt');
        });

    Route::middleware(['check_user_rights:CapitalShareTransactions'])
        ->prefix('capital-shares')
        ->group(function () {
            Route::get('/transactions', [CapitalShareTransactionController::class, 'index'])
                ->name('capitalshares.transactions.index');

            Route::get('/transactions/{id}/receipt', [CapitalShareTransactionController::class, 'receipt'])
                ->name('capitalshares.transactions.receipt');
        });

    Route::get('/new_members/list', [PublicRegistrationActionsController::class, 'listMembers'])
        ->name('members.list')
        ->middleware('check_user_rights:new_member_applications_list');

    Route::post('/new_members/update/{id}', [PublicRegistrationActionsController::class, 'updateField'])
        ->middleware('check_user_rights:new_member_applications_update');

    Route::get('/new_members/details/{id}', [PublicRegistrationActionsController::class, 'getMemberDetails'])
        ->name('members.details')
        ->middleware('check_user_rights:new_member_applications_update');

    Route::post('/new_members/export/live', [PublicRegistrationActionsController::class, 'exportLive'])
        ->name('members.exportLive')
        ->middleware('check_user_rights:new_member_applications_update');

    Route::post('/new_members/delete', [PublicRegistrationActionsController::class, 'deleteMember'])
        ->name('new_members.delete')
        ->middleware('check_user_rights:new_member_applications_update');



    Route::middleware(['check_user_rights:FosaTransactions'])
        ->prefix('fosa/endmonth')
        ->group(function () {
            Route::get('/', [FosaEndMonthController::class, 'index'])->name('fosa.endmonth.index');
            Route::post('/update/{id}', [FosaEndMonthController::class, 'update'])->name('fosa.endmonth.update'); // AJAX update monthly contr.
            Route::post('/process', [FosaEndMonthController::class, 'process'])->name('fosa.endmonth.process');
        });


    // Route::get('/import/members', [MemberImportController::class, 'showForm'])->name('import.members.form');//->middleware('check_user_rights:new_member_applications_updateXXX');
    // Route::post('/import/members', [MemberImportController::class, 'import'])->name('import.members.process');//->middleware('check_user_rights:new_member_applications_updateXXX');
    // Route::get('/update/ledgers', [MemberImportController::class, 'updatefLedgers'])->name('update.ledgers');


    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard')->middleware('auth');

    //Route::get('/members/list', [HomeController::class, 'membersList'])->name('members.list')->middleware('check_user_rights:list_sacco_member');
    Route::get('/members/list', [HomeController::class, 'membersList'])->name('members.listing')->middleware('check_user_rights:list_sacco_member');
    Route::get('/members/list/csv', [HomeController::class, 'membersListCsv'])
        ->name('members.list.csv')
        ->middleware('check_user_rights:list_sacco_member');


    Route::get('/members/add', [HomeController::class, 'addNewMember'])->name('members.add')->middleware('check_user_rights:add_new_sacco_member');
    Route::post('/members/store', [HomeController::class, 'storeNewMember'])->name('members.store')->middleware('check_user_rights:add_new_sacco_member');
    Route::get('/members/active/{status}', [HomeController::class, 'membersActive'])->name('members.active')->middleware('check_user_rights:list_sacco_member');
    Route::get('/members/edit/{id}', [HomeController::class, 'editMember'])->name('members.edit')->middleware('check_user_rights:edit_member');
    Route::get('/members/download/{member_id}/{filename}', [\App\Http\Controllers\MemberAddImages::class, 'download'])
        ->name('members.download')
        ->middleware('check_user_rights:edit_member');



    Route::get('/members/protectedfiles/{path}', [\App\Http\Controllers\MemberAddImages::class, 'serveProtectedFile'])
        ->where('path', '.*')
        ->middleware('check_user_rights:edit_member')
        ->name('members.protectedfile');


    Route::get('/members/edit/images/{id}', [MemberAddImages::class, 'index'])->name('members.edit.image')->middleware('check_user_rights:edit_member');
    Route::post('/members/edit/images/{id}', [MemberAddImages::class, 'update'])->name('members.edit.update.image')->middleware('check_user_rights:edit_member');

    Route::post('/members/update/{id}', [HomeController::class, 'updateMember'])->name('members.update')->middleware('check_user_rights:edit_member');
    Route::get('/members/next-of-kin/{id}', [HomeController::class, 'editNextOfKin'])->name('members.nextOfKin')->middleware('check_user_rights:add_next_of_kin');
    Route::post('/members/next-of-kin/update/{id}', [HomeController::class, 'updateNextOfKin'])->name('members.updateNextOfKin')->middleware('check_user_rights:add_next_of_kin');
    Route::get('/members/delete-next-of-kin/{member_id}/{kin_id}', [HomeController::class, 'deleteNextOfKin'])->name('members.deleteNextOfKin')->middleware('check_user_rights:add_next_of_kin');






    Route::delete('/members/{member_id}/guarantors/{guarantor_id}', [HomeController::class, 'deleteGuarantor'])->name('deleteGuarantor')->middleware('check_user_rights:loan_guarantors_change');
    Route::get('/changeGuarantors/{member_id}/{guarantor_id}', [HomeController::class, 'changeGuarantors'])->name('changeGuarantors')->middleware('check_user_rights:loan_guarantors_change');
    Route::post('/updateGuarantors/{member_id}/{guarantor_id}', [HomeController::class, 'updateGuarantors'])->name('updateGuarantors')->middleware('check_user_rights:loan_guarantors_change');

    Route::get('/ajax-get-members', [HomeController::class, 'ajaxGetMembers'])->name('ajaxGetMembers')->middleware('check_user_rights:list_sacco_member');
    Route::get('/members/statement/{id?}', [HomeController::class, 'viewStatement'])->name('members.statement')->middleware('check_user_rights:list_member_statement');

    Route::prefix('migration/statement-cleanup')
        ->name('migration.statement.')
        ->middleware('check_user_rights:end_month_processing_loans')
        ->group(function () {
            Route::delete('/loans/{loan}', [MigrationStatementCleanupController::class, 'destroyLoan'])
                ->name('loan.destroy');

            Route::delete('/loan-payments/{payment}', [MigrationStatementCleanupController::class, 'destroyPayment'])
                ->name('payment.destroy');
        });

    Route::match(['get', 'post'], '/members/contributions/{id}', [HomeController::class, 'viewContributions'])->name('members.contributions')->middleware('check_user_rights:edit_member_share_contribution');
    Route::get('/members/change-password/{id}', [HomeController::class, 'changePassword'])->name('members.changePassword')->middleware('check_user_rights:edit_password');
    Route::post('/members/change-password/{id}', [HomeController::class, 'updatePassword'])->name('members.updatePassword')->middleware('check_user_rights:edit_password');

    Route::get('/institutions/add', [HomeController::class, 'addInstitution'])->name('institutions.add')->middleware('check_user_rights:add_company');
    Route::post('/institutions/store', [HomeController::class, 'storeInstitution'])->name('institutions.store')->middleware('check_user_rights:add_company');
    Route::get('/institutions/list', [HomeController::class, 'listInstitutions'])->name('institutions.list')->middleware('check_user_rights:edit_company');

    Route::get('/institutions/edit/{id}', [HomeController::class, 'editInstitution'])
        ->name('institutions.edit')
        ->middleware('check_user_rights:edit_company');

    Route::post('/institutions/update/{id}', [HomeController::class, 'updateInstitution'])
        ->name('institutions.update')
        ->middleware('check_user_rights:edit_company');

    Route::get('/modify/member/shares', [HomeController::class, 'modifyShares'])->name('modify.member.shares')->middleware('check_user_rights:modify_member_shares_journal');
    Route::post('/modify/member/shares', [HomeController::class, 'modifyShares'])->middleware('check_user_rights:modify_member_shares_journal');
    Route::get('/search/accounts', [HomeController::class, 'searchAccounts'])->name('search.accounts')->middleware('check_user_rights:modify_member_shares_journal');

    Route::match(['get', 'post'], '/transfer/member/shares', [HomeController::class, 'transferShares'])->name('transfer.member.shares')->middleware('check_user_rights:transfer_member_shares');
    Route::match(['get', 'post'], '/proc/end/month/shares', [HomeController::class, 'processEndMonthShares'])->name('proc.end.month.shares')->middleware('check_user_rights:end_month_processing_shares');
    Route::match(['get', 'post'], '/modify/member/share/capital', [HomeController::class, 'modifyShareCapital'])->name('modify.member.share.capital')->middleware('check_user_rights:modify_member_shares_capital');
    Route::match(['get', 'post'], '/transfer/member/capital/shares', [HomeController::class, 'transferCapitalShares'])->name('transfer.member.capital.shares')->middleware('check_user_rights:modify_member_shares_capital');
    Route::match(['get', 'post'], '/transfer/share/to/capital/shares', [HomeController::class, 'transferShareToCapitalShares'])->name('transfer.share.to.capital.shares')->middleware('check_user_rights:transfer_member_shares');

    Route::get('/modify/member/fosas', [HomeController::class, 'modifyFosas'])->name('modify.member.fosas')->middleware('check_user_rights:modify_member_shares_journal');
    Route::match(['get', 'post'], '/transfer/member/fosa', [HomeController::class, 'transferFosa'])->name('transfer.member.fosa')->middleware('check_user_rights:modify_member_shares_journal');
    Route::get('/proc/end/month/fosa', [HomeController::class, 'endMonthFosa'])->name('proc.end.month.fosa')->middleware('check_user_rights:modify_member_shares_journal');

    Route::prefix('reports/mpesa')->middleware('check_user_rights:rpt_loans_issued')->group(function () {
        Route::get('/', [MpesaReportController::class, 'paymentsReceived'])->name('reports.mpesa.paymentsreceived');
        Route::get('/c2b', [MpesaReportController::class, 'c2bPayments'])->name('reports.mpesa.paymentsreceived.c2b');
    });

    // List manually recovered M-Pesa transactions
    Route::get(
        '/mpesa/manual-recoveries',
        [ManualMpesaRecoveryController::class, 'manualRecoveries']
    )->name('mpesa.manual.list')
        ->middleware(['auth', 'check_member_position', 'check_user_rights:add_mpesa_manual_recovery']);



    Route::get('/reports/sasra/outstandingloans/{status}/{active?}', [HomeController::class, 'reportSasraLoans'])->name('reports.sasra.loans')->middleware('check_user_rights:rpt_loans_issued');
    Route::post('/reports/sasra/outstandingloans/{status}/{active?}', [HomeController::class, 'reportSasraLoans'])->name('reports.sasra.loans.post')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/sasra/loans/data', [HomeController::class, 'fetchSasraLoansData'])->name('reports.sasra.loans.data')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/sasra/share', [HomeController::class, 'reportSasraShareBalances'])->name('reports.sasra.share.balances')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/sasra/share/data', [HomeController::class, 'fetchSasraShareData'])->name('reports.sasra.share.data')->middleware('check_user_rights:rpt_loans_issued');

    Route::get('/admin/access-rights', [HomeController::class, 'adminAccessRights'])->name('admin.access-rights')->middleware('check_user_rights:modify_useraccessrights');
    Route::post('/admin/access-rights/save', [HomeController::class, 'user_rights_save'])->name('admin.access-rights.save')->middleware('check_user_rights:modify_useraccessrights');
    Route::match(['get', 'post'], '/admin/access-rights/add_modules', [HomeController::class, 'user_rights_add_module'])->name('admin.access-rights.add.module')->middleware('check_user_rights:modify_useraccessrights');

    Route::match(['get', 'post'], '/reports/profit_and_loss', [HomeController::class, 'reportSasraProfitAndLoss'])->name('reports.sasra.profitandloss')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/profit_and_loss/data', [HomeController::class, 'fetchSasraProfitAndLossData'])->name('reports.sasra.profitandloss.data')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/sasra/finance_position_report', [HomeController::class, 'reportSasraFinancePositionReport'])->name('reports.sasra.financeposition')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/sasra/income_report', [HomeController::class, 'reportSasraIncomeReport'])->name('reports.sasra.income')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/sasra/loan_performance/data', [HomeController::class, 'fetchLoanPerformanceData'])->name('reports.sasra.loanperformance.data')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/sasra/loan_performance/{version?}', [LoanPerformanceController::class, 'reportLoanPerformance'])->name('reports.sasra.loanperformance')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/sasra/insider_lending', [HomeController::class, 'reportSasraInsiderLending'])->name('reports.sasra.insiderlending')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/sasra/outstandingloans/y', [HomeController::class, 'reportSasraLoansAllOutstanding'])->name('reports.sasra.loans.alloutstanding')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/sasra/deposits', [HomeController::class, 'reportSasraCapitalBalances'])->name('reports.sasra.capitalbalances')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/sasra/return_on_investment_report', [HomeController::class, 'reportSasraReturnOnInvestmentReport'])->name('reports.sasra.returnoninvestment')->middleware('check_user_rights:rpt_profit_loss');

    Route::get('/reports/sasra/member_contributions/data', [ReportsShareController::class, 'fetchMemberContributionsData'])
        ->name('reports.sasra.membercontributions.data')
        ->middleware('check_user_rights:rpt_profit_loss');

    Route::get('/reports/sasra/share_compliance', [ReportsShareController::class, 'index'])
        ->name('reports.sasra.sharecompliance')
        ->middleware('check_user_rights:rpt_profit_loss');

    Route::get('/reports/shares/top-members', [ReportsShareController::class, 'topShareholdingMembers'])
        ->name('reports.shares.top_members')
        ->middleware('check_user_rights:rpt_profit_loss');

    Route::get('/reports/shares/aging', [ReportsShareController::class, 'shareAgingReport'])
        ->name('reports.shares.aging')
        ->middleware('check_user_rights:rpt_profit_loss');



    Route::get('/list/contribution', [HomeController::class, 'listContribution'])->name('list.contribution')->middleware('check_user_rights:list_sacco_member_contributions');
    Route::get('/proc/end/month/loans', [LoanEndMonthController::class, 'endMonthLoans'])->name('proc.end.month.loans')->middleware('check_user_rights:end_month_processing_loans');
    Route::post('/proc/end/month/loans', [LoanEndMonthController::class, 'processEndMonthLoans'])->name('proc.end.month.loans.process')->middleware('check_user_rights:end_month_processing_loans');
    Route::get('/loans/approval', [HomeController::class, 'listLoansForApproval'])->name('loans.approval')->middleware('check_user_rights:end_month_processing_loans');
    Route::get('/loans/approve/{loanId}', [HomeController::class, 'approveLoan'])->name('loans.approve')->middleware('check_user_rights:end_month_processing_loans');
    Route::get('/loans/delete/{loanId}', [HomeController::class, 'deleteLoan'])->name('loans.delete')->middleware('check_user_rights:end_month_processing_loans');

    // Route::get('/admin/loans/pending/approval', [HomeController::class, 'adminListLoansPendingApproval'])->name('admin.loans.pending.approval')->middleware('check_user_rights:end_month_processing_loans');
    Route::get('admin/loans/pending/approval', [LoanApplicationSelfServiceController::class, 'listLoansPendingApproval'])
        ->name('loans.pending.approval')
        ->middleware('check_user_rights:end_month_processing_loans');

    Route::patch('admin/loans/pending/approval/{id}/doc-no', [LoanApplicationSelfServiceController::class, 'updatePendingLoanDocNo'])
        ->name('loans.pending.approval.docno.update')
        ->middleware('check_user_rights:end_month_processing_loans');

    Route::post(
        'admin/loans/approve/{loanId}',
        [LoanApplicationSelfServiceController::class, 'approveLoan']
    )->name('loans.approve.self')
        ->middleware('check_user_rights:end_month_processing_loans');

    // Route::post('admin/loans/approve/{id}', [LoanApplicationSelfServiceController::class, 'approveLoanself'])
    //     ->name('loans.approve.self')->middleware('check_user_rights:end_month_processing_loans');

    Route::post('admin/loans/reject/{id}', [LoanApplicationSelfServiceController::class, 'rejectLoan'])
        ->name('loans.reject')->middleware('check_user_rights:end_month_processing_loans');


    Route::get('/admin/end-of-year-processing', [HomeController::class, 'showEndOfYearProcessingForm'])->name('admin.show-end-of-year-processing-form')->middleware('check_user_rights:end_of_year_processing');
    Route::post('/admin/end-of-year-processing', [HomeController::class, 'endOfYearProcessing'])->name('admin.end-of-year-processing')->middleware('check_user_rights:end_of_year_processing');

    Route::get('/loans/issued', [HomeController::class, 'loansIssued'])->name('loans.issued');
    Route::get('/loans/batches', [LoanController::class, 'loans_batches'])->name('loans.batches')->middleware('check_user_rights:add_loan_batch');
    Route::get('/loans/batch', [LoanController::class, 'loans_batch'])->name('loans.batch')->middleware('check_user_rights:add_loan_type');
    Route::post('/loans/batch/store', [LoanController::class, 'loans_batch_store'])->name('loans.batch.store')->middleware('check_user_rights:add_loan_type');
    Route::get('/loans/batch/edit/{batch_id}', [LoanController::class, 'loans_batch_edit'])->name('loans.batch.edit')->middleware('check_user_rights:add_loan_batch');
    Route::post('/loans/batch/update/{batch_id}', [LoanController::class, 'loans_batch_update'])->name('loans.batch.update')->middleware('check_user_rights:add_loan_batch');
    Route::get('/loans/batch/delete/{batch_id}', [LoanController::class, 'loans_batch_delete'])->name('loans.batch.delete')->middleware('check_user_rights:add_loan_batch');
    Route::get('/loans/batch/loans/{batch_id}', [LoanController::class, 'loans_batch_loans'])->name('loans.batch.loans')->middleware('check_user_rights:add_loan_batch');
    Route::post('/loans/batch/loans/add/{batch_id}', [LoanController::class, 'loans_batch_loans_add'])->name('loans.batch.loans.add')->middleware('check_user_rights:add_loan_batch');
    Route::get('/loans/batch/transactions/{batch_id}', [LoanController::class, 'loans_batch_transactions'])->name('loans.batch.transactions')->middleware('check_user_rights:add_loan_batch');
    Route::get('/loans/batch/transactions/add/{batch_id}', [LoanController::class, 'loans_batch_transactions_add_view'])->name('loans.batch.transactions.add_view')->middleware('check_user_rights:add_loan_batch');
    Route::post('/loans/batch/transactions/add/{batch_id}', [LoanController::class, 'loans_batch_transactions_add'])->name('loans.batch.transactions.add')->middleware('check_user_rights:add_loan_batch');
    Route::get('/loans/batch/transactions/delete/{transaction_id}', [LoanController::class, 'loans_batch_transactions_delete'])->name('loans.batch.transactions.delete')->middleware('check_user_rights:add_loan_batch');
    Route::get('/search/loan_batch/members', [LoanController::class, 'searchMembers'])
        ->name('search.loan_batch.members')
        ->middleware([
            'check_user_rights:add_loan_batch',
            'throttle:40,1'
        ]);
    Route::get('/search/loan_batch/member_loans', [LoanController::class, 'searchMemberLoans'])
        ->name('loan_batch.member_loans')
        ->middleware([
            'check_user_rights:add_loan_batch',
            'throttle:40,1'
        ]);
    Route::get('/loans/get-free-shares', [LoanController::class, 'LoanGetFreeShares'])->name('loan.get.free.shares')->middleware('check_user_rights:add_loan_batch');
    Route::post('/loans/batch/transactions/update/{batch_id}/{transaction_id}', [LoanController::class, 'loans_batch_transactions_update'])->name('loans.batch.transactions.update')->middleware('check_user_rights:add_loan_batch');

    Route::get('/loans/batch/transactions/edit/{batch_id}/{transaction_id}', [LoanController::class, 'loans_batch_transactions_edit'])->name('loans.batch.transactions.edit')->middleware('check_user_rights:add_loan_batch');
    Route::get('/loans/batch/transactions/delete/{transaction_id}', [LoanController::class, 'loans_batch_transactions_delete'])->name('loans.batch.transactions.delete')->middleware('check_user_rights:add_loan_batch');

    Route::get('/loans/end-month', [HomeController::class, 'loansEndMonth'])->name('loans.end-month')->middleware('check_user_rights:end_month_processing_loans');
    Route::get('/loans/un-finished', [HomeController::class, 'loansUnFinished'])->name('loans.un-finished')->middleware('check_user_rights:end_month_processing_loans');
    Route::get('/loans/shares-to-loans', [HomeController::class, 'loansSharesToLoans'])->name('loans.shares-to-loans')->middleware('check_user_rights:end_month_processing_loans');
    Route::get('/loans/types', [HomeController::class, 'loansTypes'])->name('loans.types')->middleware('check_user_rights:list_loan_types');
    Route::get('/loans/types/edit/{id}', [HomeController::class, 'editLoanType'])->name('loans.types.edit')->middleware('check_user_rights:add_loan_type');
    Route::put('/loans/types/update/{id}', [HomeController::class, 'updateLoanType'])->name('loans.types.update')->middleware('check_user_rights:add_loan_type');
    Route::get('/loans/types/add', [HomeController::class, 'createLoanType'])->name('loans.types.add')->middleware('check_user_rights:add_loan_type');
    Route::post('/loans/types/store', [HomeController::class, 'storeLoanType'])->name('loans.types.store')->middleware('check_user_rights:add_loan_type');
    Route::delete('/loans/types/delete/{id}', [HomeController::class, 'deleteLoanType'])->name('loans.types.delete')->middleware('check_user_rights:add_loan_type');

    Route::get('/loans/deduction-types', [LoanDeductionTypeController::class, 'index'])
        ->name('loans.deduction-types')
        ->middleware('check_user_rights:list_loan_deduction_types');

    Route::get('/loans/deduction-types/add', [LoanDeductionTypeController::class, 'create'])
        ->name('loans.deduction-types.add')
        ->middleware('check_user_rights:add_loan_deduction_type');

    Route::post('/loans/deduction-types/store', [LoanDeductionTypeController::class, 'store'])
        ->name('loans.deduction-types.store')
        ->middleware('check_user_rights:add_loan_deduction_type');

    Route::get('/loans/deduction-types/edit/{id}', [LoanDeductionTypeController::class, 'edit'])
        ->name('loans.deduction-types.edit')
        ->middleware('check_user_rights:add_loan_deduction_type');

    Route::put('/loans/deduction-types/update/{id}', [LoanDeductionTypeController::class, 'update'])
        ->name('loans.deduction-types.update')
        ->middleware('check_user_rights:add_loan_deduction_type');

    Route::delete('/loans/deduction-types/delete/{id}', [LoanDeductionTypeController::class, 'destroy'])
        ->name('loans.deduction-types.delete')
        ->middleware('check_user_rights:add_loan_deduction_type');

    Route::get('/loans/categories', [HomeController::class, 'loansCategories'])->name('loans.categories')->middleware('check_user_rights:add_loan_type');
    Route::get('/loans/categories/create', [HomeController::class, 'createLoanCategory'])->name('loans.categories.create')->middleware('check_user_rights:add_loan_type');
    Route::post('/loans/categories/store', [HomeController::class, 'storeLoanCategory'])->name('loans.categories.store')->middleware('check_user_rights:add_loan_type');
    Route::get('/loans/categories/edit/{id}', [HomeController::class, 'editLoanCategory'])->name('loans.categories.edit')->middleware('check_user_rights:add_loan_type');
    Route::post('/loans/categories/update/{id}', [HomeController::class, 'updateLoanCategory'])->name('loans.categories.update')->middleware('check_user_rights:add_loan_type');
    Route::delete('/loans/categories/delete/{id}', [HomeController::class, 'deleteLoanCategory'])->name('loans.categories.delete')->middleware('check_user_rights:add_loan_type');


    // Loan Repayments CSV Upload
    Route::get(
        '/loans/repayments/import',
        [LoanRepaymentImportController::class, 'index']
    )
        ->name('loans.repayments.import')
        ->middleware('check_user_rights:add_loan_payment');

    Route::post(
        '/loans/repayments/import/upload',
        [LoanRepaymentImportController::class, 'upload']
    )
        ->name('loans.repayments.csv.upload')
        ->middleware('check_user_rights:add_loan_payment');

    Route::post(
        '/loans/repayments/import/process',
        [LoanRepaymentImportController::class, 'process']
    )
        ->name('loans.repayments.csv.process')
        ->middleware('check_user_rights:add_loan_payment');

    // Download Sample CSV
    Route::get(
        '/loans/repayments/sample',
        [LoanRepaymentImportController::class, 'sample']
    )
        ->name('loans.repayments.csv.sample');




    Route::get('/modify/member/loans', [LoanPaymentController::class, 'index'])
        ->name('modify.member.loans')
        ->middleware('check_user_rights:add_loan_batch_transactions');

    Route::post('/modify/member/loans/update', [LoanPaymentController::class, 'updateLoanPayment'])
        ->name('modify.member.loans.update')
        ->middleware('check_user_rights:add_loan_batch_transactions');

    // Additional Routes
    Route::post('/modify/member/loans/pay', [LoanPaymentController::class, 'payLoan'])
        ->name('modify.member.loans.pay')
        ->middleware('check_user_rights:add_loan_batch_transactions');

    Route::post('/modify/member/loans/reduce', [LoanPaymentController::class, 'reduceLoan'])
        ->name('modify.member.loans.reduce')
        ->middleware('check_user_rights:add_loan_batch_transactions');



    Route::get('/modify/member/loans/asset-accounts', [LoanPaymentController::class, 'getAssetAccounts'])
        ->name('modify.member.loans.asset.accounts')
        ->middleware('check_user_rights:add_loan_batch_transactions');



    Route::get('/guarantors/deduction', [HomeController::class, 'guarantorsDeduction'])->name('guarantors.deduction')->name('loans.categories.delete')->middleware('check_user_rights:loan_guarantors_change');
    Route::get('/guarantors/reset', [HomeController::class, 'guarantorsReset'])->name('guarantors.reset')->name('loans.categories.delete')->middleware('check_user_rights:loan_guarantors_change');

    Route::get('/reports/members/status', [HomeController::class, 'reportsMembersStatus'])->name('reports.members.status')->middleware('check_user_rights:list_member_statement');
    Route::get('/reports/loans/issued', [HomeController::class, 'reportsLoansIssued'])->name('reports.loans.issued')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/issued/data', [HomeController::class, 'getLoansIssued'])->name('reports.loans.issued.data')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/issued/download', action: [HomeController::class, 'downloadLoansIssuedReport'])->name('reports.loans.issued.download')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/repayments', [HomeController::class, 'reportsLoansRepayments'])->name('reports.loans.repayments')->middleware('check_user_rights:rpt_reports');
    Route::get('/reports/loans/repayments/data', [HomeController::class, 'getLoansRepayments'])->name('reports.loans.repayments.data')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/repayments/download', [HomeController::class, 'downloadLoansRepaymentsReport'])->name('reports.loans.repayments.download')->middleware('check_user_rights:rpt_loans_issued');

    Route::get('/reports/members/status/data', [HomeController::class, 'reportsMembersStatusData'])->name('reports.members.status.data')->middleware('check_user_rights:list_member_statement');
    Route::get('/reports/members/status/download', [HomeController::class, 'downloadMembersStatus'])->name('reports.members.status.download')->middleware('check_user_rights:list_member_statement');

    Route::get('/reports/shares/contribution', [HomeController::class, 'reportsSharesContribution'])->name('reports.shares.contribution')->middleware('check_user_rights:rpt_reports');
    Route::get('/reports/shares/capital', [HomeController::class, 'reportsSharesCapital'])->name('reports.shares.capital')->middleware('check_user_rights:rpt_reports');
    Route::get('/reports/fosa/contribution', [HomeController::class, 'reportsFosaContribution'])->name('reports.fosa.contribution')->middleware('check_user_rights:rpt_reports');
    Route::get('/reports/loans/balances', [HomeController::class, 'reportsLoansBalances'])->name('reports.loans.balances')->middleware('check_user_rights:rpt_reports');
    Route::get('/reports/shares/period', [HomeController::class, 'reportsSharesPeriod'])->name('reports.shares.period')->middleware('check_user_rights:rpt_reports');

    Route::get('/reports/loans/member', [HomeController::class, 'reportsLoansMember'])->name('reports.loans.member')->middleware('check_user_rights:rpt_reports');
    Route::get('/reports/guarantors', [HomeController::class, 'reportsGuarantors'])->name('reports.guarantors')->middleware('check_user_rights:rpt_reports');
    Route::get('/reports/contributions', [HomeController::class, 'reportsContributions'])->name('reports.contributions')->middleware('check_user_rights:rpt_reports');
    Route::get('/reports/contributions/principal', [HomeController::class, 'reportsContributionsPrincipal'])->name('reports.contributions.principal')->middleware('check_user_rights:rpt_reports');



    Route::get('/members/report', [MemberReportController::class, 'index'])->name('members.report')->middleware('check_user_rights:rpt_reports');
    Route::post('/members/report/data', [MemberReportController::class, 'fetchReportData'])->name('members.report.data')->middleware('check_user_rights:rpt_reports');
    Route::get('/members/report/export', [MemberReportController::class, 'exportReport'])->name('members.report.export')->middleware('check_user_rights:rpt_reports');

    // Route::get('/reports/loans/repayments', [HomeController::class, 'reportsLoansRepayments'])->name('reports.loans.repayments')->middleware('check_user_rights:rpt_reports');
    // Route::get('/reports/loans/repayments', [HomeController::class, 'reportsLoansRepayments'])->name('reports.loans.repayments')->middleware('check_user_rights:rpt_loans_repayments');

    Route::get('/reports/loans/balances/period', [HomeController::class, 'reportsLoansBalancesPeriod'])->name('reports.loans.balances.period')->middleware('check_user_rights:rpt_reports');

    Route::get('/reports/accounts/ledger', [ReportLedgerController::class, 'reportsAccountsLedger'])->name('reports.accounts.ledger')->middleware('check_user_rights:rpt_acc_trans');
    Route::put('/reports/accounts/ledger/update/{id}', [ReportLedgerController::class, 'updateTransaction'])->name('reports.accounts.update')->middleware('check_user_rights:rpt_acc_trans');
    Route::put('/reports/accounts/ledger/update/{id}', [ReportLedgerController::class, 'updateTransaction'])->name('reports.accounts.update')->middleware('check_user_rights:modify_member_shares_journal');
    Route::get('/reports/accounts/ledger/export', [ReportLedgerController::class, 'export'])->name('reports.accounts.ledger.export');

    // Route::get('/reports/accounts/trial-balance', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.trial-balance')->middleware('check_user_rights:rpt_trial_balance');
    Route::get('/reports/accounts/trial-balance', [TrialBalanceController::class, 'index'])->name('reports.accounts.trial-balance')->middleware('check_user_rights:rpt_trial_balance');

    Route::get(
        'reports/accounts/trial-balance/excel',
        [TrialBalanceController::class, 'exportExcel']
    )->name('reports.accounts.trial-balance.excel')->middleware('check_user_rights:rpt_trial_balance');

    Route::get(
        'reports/accounts/trial-balance/pdf',
        [TrialBalanceController::class, 'exportPdf']
    )->name('reports.accounts.trial-balance.pdf')->middleware('check_user_rights:rpt_trial_balance');

    Route::get(
        'reports/accounts/balance-sheet/pdf',
        [TrialBalanceController::class, 'exportBalanceSheetPdf']
    )->name('reports.accounts.balance-sheet.pdf')->middleware('check_user_rights:rpt_trial_balance');


    Route::get(
        'reports/accounts/profit-loss/excel',
        [TrialBalanceController::class, 'exportProfitLossExcel']
    )->name('reports.accounts.profit-loss.excel')->middleware('check_user_rights:rpt_profit_loss');

    Route::get(
        'reports/accounts/profit-loss/pdf',
        [TrialBalanceController::class, 'exportProfitLossPdf']
    )->name('reports.accounts.profit-loss.pdf')->middleware('check_user_rights:rpt_profit_loss');

    Route::get(
        'reports/accounts/balance-sheet/excel',
        [TrialBalanceController::class, 'exportBalanceSheetExcel']
    )->name('reports.accounts.balance-sheet.excel')->middleware('check_user_rights:rpt_profit_loss');


    Route::get('/reports/accounts/profit-loss', [TrialBalanceController::class, 'profitLoss'])
        ->name('reports.accounts.profit-loss')
        ->middleware('check_user_rights:rpt_profit_loss');

    Route::get('/reports/accounts/profit-loss/budget', [HomeController::class, 'reportsAccountsTrialBalanceBudget'])->name('reports.accounts.profit-loss.budget')->middleware('check_user_rights:rpt_profit_loss');
    // Route::get('/reports/accounts/balance-sheet', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.balance-sheet')->middleware('check_user_rights:rpt_balance_sheet');
    Route::get('/reports/accounts/balance-sheet', [TrialBalanceController::class, 'balanceSheet'])
        ->name('reports.accounts.balance-sheet')
        ->middleware('check_user_rights:rpt_balance_sheet');
    Route::get('/reports/accounts/budget-vs-actuals', [HomeController::class, 'reportsAccountsBudgetVsActuals'])->name('reports.accounts.budget-vs-actuals')->middleware('check_user_rights:rpt_balance_sheet');
    Route::get('/reports/accounts/trial-balance-horizontal', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.trial-balance-horizontal')->middleware('check_user_rights:rpt_balance_sheet');
    Route::get('/reports/accounts/profit-loss-horizontal', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.profit-loss-horizontal')->middleware('check_user_rights:rpt_balance_sheet');
    Route::get('/reports/accounts/balance-sheet-horizontal', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.balance-sheet-horizontal')->middleware('check_user_rights:rpt_balance_sheet');

    Route::get('/interest/shares', [HomeController::class, 'interestShares'])->name('interest.shares')->middleware('check_user_rights:calc_dividends');
    Route::get('/dividends/shares', [HomeController::class, 'dividendsShares'])->name('dividends.shares')->middleware('check_user_rights:calc_dividends');
    Route::get('/interest/fosa', [HomeController::class, 'interestFosa'])->name('interest.fosa')->middleware('check_user_rights:calc_dividends');

    Route::get('/accounts/main', [HomeController::class, 'accountsMain'])->name('accounts.main')->middleware('check_user_rights:add_main_account');
    Route::get('/accounts/main/create', [HomeController::class, 'createMainAccount'])->name('accounts.main.create')->middleware('check_user_rights:add_main_account');
    Route::post('/accounts/main/store', [HomeController::class, 'storeMainAccount'])->name('accounts.main.store')->middleware('check_user_rights:add_main_account');
    Route::get('/accounts/main/edit/{id}', [HomeController::class, 'editMainAccount'])->name('accounts.main.edit')->middleware('check_user_rights:add_main_account');
    Route::post('/accounts/main/update/{id}', [HomeController::class, 'updateMainAccount'])->name('accounts.main.update')->middleware('check_user_rights:add_main_account');

    Route::get('/accounts/sub', [HomeController::class, 'accountsSub'])->name('accounts.sub')->middleware('check_user_rights:add_main_account');
    Route::get('/accounts/sub/create', [HomeController::class, 'createSubAccount'])->name('accounts.sub.create')->middleware('check_user_rights:add_main_account');
    Route::post('/accounts/sub/store', [HomeController::class, 'storeSubAccount'])->name('accounts.sub.store')->middleware('check_user_rights:add_main_account');
    Route::get('/accounts/sub/edit/{id}', [HomeController::class, 'editSubAccount'])->name('accounts.sub.edit')->middleware('check_user_rights:add_main_account');
    Route::put('/accounts/sub/update/{id}', [HomeController::class, 'updateSubAccount'])->name('accounts.sub.update')->middleware('check_user_rights:add_main_account');

    Route::get('/accounts/transfer', [HomeController::class, 'accountsTransfer'])->name('accounts.transfer')->middleware('check_user_rights:modify_member_shares_journal');
    Route::post('/accounts/transfer', [HomeController::class, 'storeAccountsTransfer'])->name('accounts.transfer.store')->middleware('check_user_rights:modify_member_shares_journal');

    // Route::get('/downloads/view', [HomeController::class, 'downloadsView'])->name('downloads.view')->middleware('check_user_rights:downloads');
    // Route::get('/downloads/add-type', [HomeController::class, 'downloadsAddType'])->name('downloads.add-type')->middleware('check_user_rights:downloads');

    Route::get('/admin/users', [HomeController::class, 'adminUsers'])->name('admin.users')->middleware('check_user_rights:modify_useraccessrights');
    Route::get('/admin/user-types', [HomeController::class, 'adminUserTypes'])->name('admin.user-types')->middleware('check_user_rights:modify_useraccessrights');

    Route::get('/admin/defaults', [HomeController::class, 'adminDefaults'])->name('admin.defaults')->middleware('check_user_rights:add_default');
    Route::post('/admin/defaults/update', [HomeController::class, 'updateDefaults'])->name('admin.defaults.update')->middleware('check_user_rights:add_default');
    Route::post('/admin/defaults/store', [HomeController::class, 'storeDefault'])->name('admin.defaults.store')->middleware('check_user_rights:add_default');
    Route::delete('/admin/defaults/{id}', [HomeController::class, 'destroyDefault'])
        ->name('admin.defaults.destroy')
        ->middleware('check_user_rights:add_default');
    Route::get('/admin/budget', [HomeController::class, 'adminBudget'])->name('admin.budget')->middleware('check_user_rights:list_budgets');
    Route::post('/admin/budget/store', [HomeController::class, 'adminBudget_store'])->name('admin.budget.store')->middleware('check_user_rights:add_budget');
    Route::get('/admin/year-end', [HomeController::class, 'adminYearEnd'])->name('admin.year-end')->middleware('check_user_rights:proc_end_year');
    Route::get('/admin/periods', [HomeController::class, 'adminPeriods'])->name('admin.periods')->middleware('check_user_rights:add_period');
    Route::get('/admin/periods/create', [HomeController::class, 'adminPeriodsCreate'])->name('admin.periods.create')->middleware('check_user_rights:add_period');
    Route::post('/admin/periods/store', [HomeController::class, 'adminPeriodsStore'])->name('admin.periods.store')->middleware('check_user_rights:add_period');
    Route::get('/admin/periods/activate/{id}', [HomeController::class, 'adminPeriodsActivate'])->name('admin.periods.activate')->middleware('check_user_rights:add_period');

    // Route::get('/admin/mpesa', [MpesaController::class, 'showMpesaConfig'])->name('admin.mpesa')->middleware('check_user_rights:add_mpesa_details');
    // Route::post('/admin/mpesa/store', [MpesaController::class, 'storeMpesaConfig'])->name('mpesa.config.store')->middleware('check_user_rights:add_mpesa_details');

    Route::post('/file-upload', [FileUploadController::class, 'upload'])->name('file.upload')->middleware('check_user_rights:file_upload');
    Route::get('/file-upload', [FileUploadController::class, 'showUploadForm'])->name('file.upload.form')->middleware('check_user_rights:file_upload');
    Route::get('/files', [FileUploadController::class, 'listFiles'])->name('files.list')->middleware('check_user_rights:file_upload');
    Route::get('/files/download/{file}', [FileUploadController::class, 'download'])->name('file.download')->middleware('check_user_rights:file_upload');
    Route::delete('/files/delete/{file}', [FileUploadController::class, 'delete'])->name('file.delete')->middleware('check_user_rights:file_delete');

    Route::get('/reports/transport/mpesa', [TransportReportsController::class, 'mpesa'])->name('reports.transport.mpesa')->middleware('check_user_rights:rpt_transport_mpesa');

    Route::get('/reports/fosa-members', [FosaMembersReportController::class, 'index'])->name('reports.fosa.members')->middleware('check_user_rights:list_fosa_report');


    Route::get(
        '/reports/fosa-members/export/csv',
        [FosaMembersReportController::class, 'exportCsv']
    )->name('reports.fosa.members.export.csv');


    if (config('sacco.transport_sacco') === 'Y') {
        Route::prefix('transport')->group(function () {
            require __DIR__ . '/transport/routes.php';
        });
    }

    Route::prefix('reports/members')
        ->group(function () {


            Route::get(
                '/financial-position',
                [MemberFinancialPositionController::class, 'index']
            )
                ->name('reports.members.financial_position')
                ->middleware('check_user_rights:rpt_reports');

            Route::get(
                '/financial-position/data',
                [MemberFinancialPositionController::class, 'data']
            )
                ->name('reports.members.financial_position.data')
                ->middleware('check_user_rights:rpt_reports');

            Route::get(
                '/financial-position/export',
                [MemberFinancialPositionController::class, 'export']
            )
                ->name('reports.members.financial_position.export')
                ->middleware('check_user_rights:rpt_reports');
        });

    Route::prefix('fosa/transfers')->group(function () {

        Route::get(
            '/',
            [FosaTransferController::class, 'index']
        )->name('fosa.transfers.index')->middleware('check_user_rights:trasfer_fosa');

        Route::get(
            '/search',
            [FosaTransferController::class, 'search']
        )->name('fosa.transfers.search')->middleware('check_user_rights:trasfer_fosa');

        Route::post(
            '/post',
            [FosaTransferController::class, 'postTransfer']
        )->name('fosa.transfers.post')->middleware('check_user_rights:trasfer_fosa');

        Route::get(
            '/ledger-search',
            [FosaTransferController::class, 'ledgerSearch']
        )->name('fosa.transfers.ledger_search')->middleware('check_user_rights:trasfer_fosa');
    });


    // =========================
    // TRIAL BALANCE
    // =========================
    Route::get(
        '/reports/final-accounts/trial-balance',
        [FinalAccountsController::class, 'trialBalance']
    )->name('reports.final_accounts.trial_balance')
        ->middleware('check_user_rights:rpt_trial_balance');

    Route::get(
        '/reports/final-accounts/trial-balance/pdf',
        [FinalAccountsController::class, 'trialBalancePdf']
    )->name('reports.final_accounts.trial_balance.pdf')
        ->middleware('check_user_rights:rpt_trial_balance');

    Route::get(
        '/reports/final-accounts/trial-balance/excel',
        [FinalAccountsController::class, 'trialBalanceExcel']
    )->name('reports.final_accounts.trial_balance.excel')
        ->middleware('check_user_rights:rpt_trial_balance');


    // =========================
    // PROFIT & LOSS
    // =========================
    Route::get(
        '/reports/final-accounts/profit-loss',
        [FinalAccountsController::class, 'profitLoss']
    )->name('reports.final_accounts.profit_loss')
        ->middleware('check_user_rights:rpt_profit_loss');

    Route::get(
        '/reports/final-accounts/profit-loss/pdf',
        [FinalAccountsController::class, 'profitLossPdf']
    )->name('reports.final_accounts.profit_loss.pdf')
        ->middleware('check_user_rights:rpt_profit_loss');

    Route::get(
        '/reports/final-accounts/profit-loss/excel',
        [FinalAccountsController::class, 'profitLossExcel']
    )->name('reports.final_accounts.profit_loss.excel')
        ->middleware('check_user_rights:rpt_profit_loss');


    // =========================
    // BALANCE SHEET
    // =========================
    Route::get(
        '/reports/final-accounts/balance-sheet',
        [FinalAccountsController::class, 'balanceSheet']
    )->name('reports.final_accounts.balance_sheet')
        ->middleware('check_user_rights:rpt_balance_sheet');

    Route::get(
        '/reports/final-accounts/balance-sheet/pdf',
        [FinalAccountsController::class, 'balanceSheetPdf']
    )->name('reports.final_accounts.balance_sheet.pdf')
        ->middleware('check_user_rights:rpt_balance_sheet');

    Route::get(
        '/reports/final-accounts/balance-sheet/excel',
        [FinalAccountsController::class, 'balanceSheetExcel']
    )->name('reports.final_accounts.balance_sheet.excel')
        ->middleware('check_user_rights:rpt_balance_sheet');

    Route::prefix('shares-reductions')->group(function () {

        Route::get('/', [ShareReductionController::class, 'index'])
            ->name('shares_reductions.index')
            ->middleware('check_user_rights:shares_reductions');

        Route::get('/create', [ShareReductionController::class, 'create'])
            ->name('shares_reductions.create')
            ->middleware('check_user_rights:shares_reductions');

        Route::post('/store', [ShareReductionController::class, 'store'])
            ->name('shares_reductions.store')
            ->middleware('check_user_rights:shares_reductions');

        Route::get('/show/{id}', [ShareReductionController::class, 'show'])
            ->name('shares_reductions.show')
            ->middleware('check_user_rights:shares_reductions');

        Route::get('/search-members', [ShareReductionController::class, 'searchMembers'])
            ->name('shares_reductions.search_members')
            ->middleware('check_user_rights:shares_reductions');

        Route::get('/search-accounts', [ShareReductionController::class, 'searchAccounts'])
            ->name('shares_reductions.search_accounts')
            ->middleware('check_user_rights:shares_reductions');
    });


    /*
|--------------------------------------------------------------------------
| Special Savings Module
|--------------------------------------------------------------------------
| Module: Special Savings
| Default Product: FEDHA
| Controller: SpecialSavingController
|--------------------------------------------------------------------------
*/

Route::prefix('special-savings')
    ->name('special_savings.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard / Overview
        |--------------------------------------------------------------------------
        */
        Route::get('/', [SpecialSavingController::class, 'index'])
            ->name('index')
            ->middleware('check_user_rights:special_savings_view');

        Route::get('/dashboard', [SpecialSavingController::class, 'dashboard'])
            ->name('dashboard')
            ->middleware('check_user_rights:special_savings_view');


        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        | Table: sacco_special_saving_categories
        */
        Route::get('/categories', [SpecialSavingController::class, 'categories'])
            ->name('categories.index')
            ->middleware('check_user_rights:special_savings_categories');

        Route::get('/categories/create', [SpecialSavingController::class, 'createCategory'])
            ->name('categories.create')
            ->middleware('check_user_rights:special_savings_categories');

        Route::post('/categories/store', [SpecialSavingController::class, 'storeCategory'])
            ->name('categories.store')
            ->middleware('check_user_rights:special_savings_categories');

        Route::get('/categories/edit/{id}', [SpecialSavingController::class, 'editCategory'])
            ->name('categories.edit')
            ->middleware('check_user_rights:special_savings_categories');

        Route::post('/categories/update/{id}', [SpecialSavingController::class, 'updateCategory'])
            ->name('categories.update')
            ->middleware('check_user_rights:special_savings_categories');

        Route::post('/categories/toggle/{id}', [SpecialSavingController::class, 'toggleCategory'])
            ->name('categories.toggle')
            ->middleware('check_user_rights:special_savings_categories');


        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        | Table: sacco_special_saving_products
        | Example Product: FEDHA
        */
        Route::get('/products', [SpecialSavingController::class, 'products'])
            ->name('products.index')
            ->middleware('check_user_rights:special_savings_products');

        Route::get('/products/create', [SpecialSavingController::class, 'createProduct'])
            ->name('products.create')
            ->middleware('check_user_rights:special_savings_products');

        Route::post('/products/store', [SpecialSavingController::class, 'storeProduct'])
            ->name('products.store')
            ->middleware('check_user_rights:special_savings_products');

        Route::get('/products/edit/{id}', [SpecialSavingController::class, 'editProduct'])
            ->name('products.edit')
            ->middleware('check_user_rights:special_savings_products');

        Route::post('/products/update/{id}', [SpecialSavingController::class, 'updateProduct'])
            ->name('products.update')
            ->middleware('check_user_rights:special_savings_products');

        Route::post('/products/toggle/{id}', [SpecialSavingController::class, 'toggleProduct'])
            ->name('products.toggle')
            ->middleware('check_user_rights:special_savings_products');

        Route::get('/products/show/{id}', [SpecialSavingController::class, 'showProduct'])
            ->name('products.show')
            ->middleware('check_user_rights:special_savings_products');


        /*
        |--------------------------------------------------------------------------
        | Product Rate Tiers
        |--------------------------------------------------------------------------
        | Table: sacco_special_saving_product_rate_tiers
        */
        Route::get('/products/{product_id}/rate-tiers', [SpecialSavingController::class, 'rateTiers'])
            ->name('rate_tiers.index')
            ->middleware('check_user_rights:special_savings_products');

        Route::get('/products/{product_id}/rate-tiers/create', [SpecialSavingController::class, 'createRateTier'])
            ->name('rate_tiers.create')
            ->middleware('check_user_rights:special_savings_products');

        Route::post('/products/{product_id}/rate-tiers/store', [SpecialSavingController::class, 'storeRateTier'])
            ->name('rate_tiers.store')
            ->middleware('check_user_rights:special_savings_products');

        Route::get('/rate-tiers/edit/{id}', [SpecialSavingController::class, 'editRateTier'])
            ->name('rate_tiers.edit')
            ->middleware('check_user_rights:special_savings_products');

        Route::post('/rate-tiers/update/{id}', [SpecialSavingController::class, 'updateRateTier'])
            ->name('rate_tiers.update')
            ->middleware('check_user_rights:special_savings_products');

        Route::post('/rate-tiers/delete/{id}', [SpecialSavingController::class, 'deleteRateTier'])
            ->name('rate_tiers.delete')
            ->middleware('check_user_rights:special_savings_products');


        /*
        |--------------------------------------------------------------------------
        | Member Special Saving Accounts
        |--------------------------------------------------------------------------
        | Table: sacco_special_saving_accounts
        */
        Route::get('/accounts', [SpecialSavingController::class, 'accounts'])
            ->name('accounts.index')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::get('/accounts/create', [SpecialSavingController::class, 'createAccount'])
            ->name('accounts.create')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::post('/accounts/store', [SpecialSavingController::class, 'storeAccount'])
            ->name('accounts.store')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::get('/accounts/show/{id}', [SpecialSavingController::class, 'showAccount'])
            ->name('accounts.show')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::get('/accounts/edit/{id}', [SpecialSavingController::class, 'editAccount'])
            ->name('accounts.edit')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::post('/accounts/update/{id}', [SpecialSavingController::class, 'updateAccount'])
            ->name('accounts.update')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::post('/accounts/freeze/{id}', [SpecialSavingController::class, 'freezeAccount'])
            ->name('accounts.freeze')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::post('/accounts/activate/{id}', [SpecialSavingController::class, 'activateAccount'])
            ->name('accounts.activate')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::post('/accounts/close/{id}', [SpecialSavingController::class, 'closeAccount'])
            ->name('accounts.close')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::get('/accounts/{id}/statement', [SpecialSavingController::class, 'accountStatement'])
            ->name('accounts.statement')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::get('/accounts/{id}/statement/pdf', [SpecialSavingController::class, 'accountStatementPdf'])
            ->name('accounts.statement.pdf')
            ->middleware('check_user_rights:special_savings_accounts');


        /*
        |--------------------------------------------------------------------------
        | Member Search / Account Search APIs
        |--------------------------------------------------------------------------
        | Used by account opening, deposits, withdrawals, and transfers.
        */
        Route::get('/search/members', [SpecialSavingController::class, 'searchMembers'])
            ->name('search.members')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::get('/search/accounts', [SpecialSavingController::class, 'searchAccounts'])
            ->name('search.accounts')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::get('/search/sub-accounts', [SpecialSavingController::class, 'searchSubAccounts'])
            ->name('search.sub_accounts')
            ->middleware('check_user_rights:special_savings_categories');


        /*
        |--------------------------------------------------------------------------
        | Transactions
        |--------------------------------------------------------------------------
        | Table: sacco_special_saving_transactions
        */
        Route::get('/transactions', [SpecialSavingController::class, 'transactions'])
            ->name('transactions.index')
            ->middleware('check_user_rights:special_savings_transactions');

        Route::get('/transactions/create', [SpecialSavingController::class, 'createTransaction'])
            ->name('transactions.create')
            ->middleware('check_user_rights:special_savings_transactions');

        Route::post('/transactions/store', [SpecialSavingController::class, 'storeTransaction'])
            ->name('transactions.store')
            ->middleware('check_user_rights:special_savings_transactions');

        Route::get('/transactions/show/{id}', [SpecialSavingController::class, 'showTransaction'])
            ->name('transactions.show')
            ->middleware('check_user_rights:special_savings_transactions');

        Route::get('/transactions/{id}/receipt', [SpecialSavingController::class, 'transactionReceipt'])
            ->name('transactions.receipt')
            ->middleware('check_user_rights:special_savings_transactions');

        Route::post('/transactions/reverse/{id}', [SpecialSavingController::class, 'reverseTransaction'])
            ->name('transactions.reverse')
            ->middleware('check_user_rights:special_savings_reverse');


        /*
        |--------------------------------------------------------------------------
        | Direct Deposits
        |--------------------------------------------------------------------------
        | DEPOSIT / TRANSFER_IN
        */
        Route::get('/deposits/create', [SpecialSavingController::class, 'createDeposit'])
            ->name('deposits.create')
            ->middleware('check_user_rights:special_savings_deposits');

        Route::post('/deposits/store', [SpecialSavingController::class, 'storeDeposit'])
            ->name('deposits.store')
            ->middleware('check_user_rights:special_savings_deposits');


        /*
        |--------------------------------------------------------------------------
        | Transfers
        |--------------------------------------------------------------------------
        | Transfer from shares/FOSA/capital/other account into Special Savings,
        | or from Special Savings to another member account if allowed.
        */
        Route::get('/transfers/create', [SpecialSavingController::class, 'createTransfer'])
            ->name('transfers.create')
            ->middleware('check_user_rights:special_savings_transfers');

        Route::post('/transfers/store', [SpecialSavingController::class, 'storeTransfer'])
            ->name('transfers.store')
            ->middleware('check_user_rights:special_savings_transfers');


        /*
        |--------------------------------------------------------------------------
        | Withdrawal Requests
        |--------------------------------------------------------------------------
        | Table: sacco_special_saving_withdrawal_requests
        */
        Route::get('/withdrawals', [SpecialSavingController::class, 'withdrawals'])
            ->name('withdrawals.index')
            ->middleware('check_user_rights:special_savings_withdrawals');

        Route::get('/withdrawals/create', [SpecialSavingController::class, 'createWithdrawal'])
            ->name('withdrawals.create')
            ->middleware('check_user_rights:special_savings_withdrawals');

        Route::post('/withdrawals/store', [SpecialSavingController::class, 'storeWithdrawal'])
            ->name('withdrawals.store')
            ->middleware('check_user_rights:special_savings_withdrawals');

        Route::get('/withdrawals/show/{id}', [SpecialSavingController::class, 'showWithdrawal'])
            ->name('withdrawals.show')
            ->middleware('check_user_rights:special_savings_withdrawals');

        Route::post('/withdrawals/approve/{id}', [SpecialSavingController::class, 'approveWithdrawal'])
            ->name('withdrawals.approve')
            ->middleware('check_user_rights:special_savings_withdrawal_approval');

        Route::post('/withdrawals/reject/{id}', [SpecialSavingController::class, 'rejectWithdrawal'])
            ->name('withdrawals.reject')
            ->middleware('check_user_rights:special_savings_withdrawal_approval');

        Route::post('/withdrawals/pay/{id}', [SpecialSavingController::class, 'payWithdrawal'])
            ->name('withdrawals.pay')
            ->middleware('check_user_rights:special_savings_withdrawal_payment');

        Route::post('/withdrawals/cancel/{id}', [SpecialSavingController::class, 'cancelWithdrawal'])
            ->name('withdrawals.cancel')
            ->middleware('check_user_rights:special_savings_withdrawals');

        Route::get('/withdrawals/{id}/voucher', [SpecialSavingController::class, 'withdrawalVoucher'])
            ->name('withdrawals.voucher')
            ->middleware('check_user_rights:special_savings_withdrawals');


        /*
        |--------------------------------------------------------------------------
        | Interest Processing
        |--------------------------------------------------------------------------
        | Tables:
        | sacco_special_saving_interest_runs
        | sacco_special_saving_interest_run_items
        */
        Route::get('/interest', [SpecialSavingController::class, 'interestRuns'])
            ->name('interest.index')
            ->middleware('check_user_rights:special_savings_interest');

        Route::get('/interest/create', [SpecialSavingController::class, 'createInterestRun'])
            ->name('interest.create')
            ->middleware('check_user_rights:special_savings_interest');

        Route::post('/interest/preview', [SpecialSavingController::class, 'previewInterestRun'])
            ->name('interest.preview')
            ->middleware('check_user_rights:special_savings_interest');

        Route::post('/interest/process', [SpecialSavingController::class, 'processInterestRun'])
            ->name('interest.process')
            ->middleware('check_user_rights:special_savings_interest');

        Route::get('/interest/show/{id}', [SpecialSavingController::class, 'showInterestRun'])
            ->name('interest.show')
            ->middleware('check_user_rights:special_savings_interest');

        Route::get('/interest/items/{run_id}', [SpecialSavingController::class, 'interestRunItems'])
            ->name('interest.items')
            ->middleware('check_user_rights:special_savings_interest');

        Route::post('/interest/post/{id}', [SpecialSavingController::class, 'postInterestRun'])
            ->name('interest.post')
            ->middleware('check_user_rights:special_savings_interest');

        Route::post('/interest/reverse/{id}', [SpecialSavingController::class, 'reverseInterestRun'])
            ->name('interest.reverse')
            ->middleware('check_user_rights:special_savings_interest_reverse');

        Route::post('/interest/cancel/{id}', [SpecialSavingController::class, 'cancelInterestRun'])
            ->name('interest.cancel')
            ->middleware('check_user_rights:special_savings_interest');


        /*
        |--------------------------------------------------------------------------
        | Interest Vesting
        |--------------------------------------------------------------------------
        | Converts accrued interest to available interest after 6 months.
        */
        Route::get('/vesting', [SpecialSavingController::class, 'vesting'])
            ->name('vesting.index')
            ->middleware('check_user_rights:special_savings_interest');

        Route::post('/vesting/preview', [SpecialSavingController::class, 'previewVesting'])
            ->name('vesting.preview')
            ->middleware('check_user_rights:special_savings_interest');

        Route::post('/vesting/process', [SpecialSavingController::class, 'processVesting'])
            ->name('vesting.process')
            ->middleware('check_user_rights:special_savings_interest');


        /*
        |--------------------------------------------------------------------------
        | End Month Processing Shortcut
        |--------------------------------------------------------------------------
        | For FEDHA monthly interest processing.
        */
        Route::get('/end-month', [SpecialSavingController::class, 'endMonth'])
            ->name('end_month.index')
            ->middleware('check_user_rights:special_savings_end_month');

        Route::post('/end-month/process', [SpecialSavingController::class, 'processEndMonth'])
            ->name('end_month.process')
            ->middleware('check_user_rights:special_savings_end_month');


        /*
        |--------------------------------------------------------------------------
        | Imports
        |--------------------------------------------------------------------------
        | Optional but useful for payroll/MPA-style special saving contributions.
        */
        Route::get('/import', [SpecialSavingController::class, 'importForm'])
            ->name('import.form')
            ->middleware('check_user_rights:special_savings_import');

        Route::post('/import/preview', [SpecialSavingController::class, 'importPreview'])
            ->name('import.preview')
            ->middleware('check_user_rights:special_savings_import');

        Route::post('/import/process', [SpecialSavingController::class, 'importProcess'])
            ->name('import.process')
            ->middleware('check_user_rights:special_savings_import');

        Route::post('/import/cancel', [SpecialSavingController::class, 'importCancel'])
            ->name('import.cancel')
            ->middleware('check_user_rights:special_savings_import');

        Route::get('/import/sample', [SpecialSavingController::class, 'importSample'])
            ->name('import.sample')
            ->middleware('check_user_rights:special_savings_import');


        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */
        Route::get('/reports/balances', [SpecialSavingController::class, 'reportBalances'])
            ->name('reports.balances')
            ->middleware('check_user_rights:special_savings_reports');

        Route::get('/reports/balances/export', [SpecialSavingController::class, 'exportBalances'])
            ->name('reports.balances.export')
            ->middleware('check_user_rights:special_savings_reports');

        Route::get('/reports/transactions', [SpecialSavingController::class, 'reportTransactions'])
            ->name('reports.transactions')
            ->middleware('check_user_rights:special_savings_reports');

        Route::get('/reports/transactions/export', [SpecialSavingController::class, 'exportTransactions'])
            ->name('reports.transactions.export')
            ->middleware('check_user_rights:special_savings_reports');

        Route::get('/reports/interest', [SpecialSavingController::class, 'reportInterest'])
            ->name('reports.interest')
            ->middleware('check_user_rights:special_savings_reports');

        Route::get('/reports/interest/export', [SpecialSavingController::class, 'exportInterest'])
            ->name('reports.interest.export')
            ->middleware('check_user_rights:special_savings_reports');

        Route::get('/reports/withdrawals', [SpecialSavingController::class, 'reportWithdrawals'])
            ->name('reports.withdrawals')
            ->middleware('check_user_rights:special_savings_reports');

        Route::get('/reports/withdrawals/export', [SpecialSavingController::class, 'exportWithdrawals'])
            ->name('reports.withdrawals.export')
            ->middleware('check_user_rights:special_savings_reports');


        /*
        |--------------------------------------------------------------------------
        | AJAX / Utility Endpoints
        |--------------------------------------------------------------------------
        */
        Route::get('/ajax/account-summary/{account_id}', [SpecialSavingController::class, 'ajaxAccountSummary'])
            ->name('ajax.account_summary')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::get('/ajax/member-accounts/{member_id}', [SpecialSavingController::class, 'ajaxMemberAccounts'])
            ->name('ajax.member_accounts')
            ->middleware('check_user_rights:special_savings_accounts');

        Route::get('/ajax/product-rules/{product_id}', [SpecialSavingController::class, 'ajaxProductRules'])
            ->name('ajax.product_rules')
            ->middleware('check_user_rights:special_savings_products');

        Route::get('/ajax/withdrawal-preview/{account_id}', [SpecialSavingController::class, 'ajaxWithdrawalPreview'])
            ->name('ajax.withdrawal_preview')
            ->middleware('check_user_rights:special_savings_withdrawals');

        Route::get('/ajax/interest-preview/{account_id}', [SpecialSavingController::class, 'ajaxInterestPreview'])
            ->name('ajax.interest_preview')
            ->middleware('check_user_rights:special_savings_interest');
    });
    
 

Route::get('/reports/sasra/{path?}', function ($path = null) {
    $reportName = $path
        ? \Illuminate\Support\Str::of($path)
            ->replace(['-', '_', '/'], ' ')
            ->title()
        : 'SASRA Report';

    return response()->view('reports.sasra.placeholder', [
        'reportName' => $reportName,
    ]);
})
    ->where('path', '.*')
    ->middleware('check_user_rights:rpt_reports')
    ->name('reports.sasra.placeholder');
    // =========================
    // LEDGERS (DRILL-DOWN)
    // =========================
    // Route::get(
    //     '/reports/ledgers/sub-account/{sub_account_id}',
    //     [LedgerController::class, 'subAccountLedger']
    // )->name('reports.ledgers.sub_account')
    //     ->middleware('check_user_rights:rpt_general_ledger');

    // Route::get(
    //     '/reports/ledgers/sub-account/{sub_account_id}/pdf',
    //     [LedgerController::class, 'subAccountLedgerPdf']
    // )->name('reports.ledgers.sub_account.pdf')
    //     ->middleware('check_user_rights:rpt_general_ledger');

    // Route::get(
    //     '/reports/ledgers/sub-account/{sub_account_id}/excel',
    //     [LedgerController::class, 'subAccountLedgerExcel']
    // )->name('reports.ledgers.sub_account.excel')
    //     ->middleware('check_user_rights:rpt_general_ledger');
});
