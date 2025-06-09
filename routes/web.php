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
use App\Http\Controllers\ReportsShareController;



// Route::get('/import-loans-to-ledger', [LoanLedgerController::class, 'importLoansToLedger']);

Route::get('/test-email', function () {
    Mail::raw('This is a test email from Laravel!', function ($message) {
        $message->to('matthewshahi@gmail.com')
            ->subject('Test Email from iSacco 3');
    });

    return 'Test email sent!';
});

Route::get('/preview-loan-email', function () {
    $loan = (object) [
        'member_name' => 'John Doe',
        'loan_type' => 'Personal Loan',
        'loan_amount' => 100000,
        'loan_taken_period' => 12,
        'loan_monthly_repayment_amount' => 8333.33,
    ];
    $saccoMail = 'matthewshahi@gmail.com';

    return new App\Mail\LoanApprovalEmail($loan, $saccoMail);
});

Route::get('/home', [HomeController::class, 'redirectBasedOnAuth'])->name('home');
Route::get('/', [HomeController::class, 'redirectBasedOnAuth'])->name('home1');

Route::get('login', [CustomAuthController::class, 'showLoginForm'])->name('login');
Route::post('login', [CustomAuthController::class, 'login']);
Route::post('logout', [CustomAuthController::class, 'logout'])->name('logout');
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

    Route::get('/loans/apply', [HomeController::class, 'loansApply'])->name('loans.apply');
    Route::post('/loans/apply', [HomeController::class, 'submitLoanApplication'])->name('loans.application.submit');
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




    Route::get('/profile/password', [HomeController::class, 'showChangeSelfPasswordForm'])->name('profile.password');
    Route::post('/profile/password', [HomeController::class, 'updateSelfPassword'])->name('profile.updatePassword');

    Route::get('/downloads', [PublicFileController::class, 'publicDownloads'])->name('public.downloads');
    Route::get('/downloads/{file}/download', [PublicFileController::class, 'downloadFile'])->name('public.download.file');
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

Route::get('/import/members', [MemberImportController::class, 'showForm'])->name('import.members.form'); //->middleware('check_user_rights:new_member_applications_updateXXX');
Route::post('/import/members', [MemberImportController::class, 'import'])->name('import.members.process'); //->middleware('check_user_rights:new_member_applications_updateXXX');
Route::get('/update/ledgers', [MemberImportController::class, 'updatefLedgers'])->name('update.ledgers');


Route::middleware(['auth', 'check_member_position'])->group(function () {


    Route::get('/new_members/list', [PublicRegistrationActionsController::class, 'listMembers'])->name('members.list')->middleware('check_user_rights:new_member_applications_list');
    Route::post('/new_members/update/{id}', [PublicRegistrationActionsController::class, 'updateField'])->middleware('check_user_rights:new_member_applications_update');
    Route::get('/new_members/details/{id}', [PublicRegistrationActionsController::class, 'getMemberDetails'])->name('members.details')->middleware('check_user_rights:new_member_applications_update');
    Route::post('/new_members/export/live', [PublicRegistrationActionsImportController::class, 'exportLiveData'])->name('members.exportLive')->middleware('check_user_rights:new_member_applications_update');



    // Route::get('/import/members', [MemberImportController::class, 'showForm'])->name('import.members.form');//->middleware('check_user_rights:new_member_applications_updateXXX');
    // Route::post('/import/members', [MemberImportController::class, 'import'])->name('import.members.process');//->middleware('check_user_rights:new_member_applications_updateXXX');
    // Route::get('/update/ledgers', [MemberImportController::class, 'updatefLedgers'])->name('update.ledgers');


    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard')->middleware('auth');

    //Route::get('/members/list', [HomeController::class, 'membersList'])->name('members.list')->middleware('check_user_rights:list_sacco_member');
    Route::get('/members/list', [HomeController::class, 'membersList'])->name('members.listing')->middleware('check_user_rights:list_sacco_member');

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



    Route::middleware(['auth', 'check_user_rights:edit_member'])->group(function () {
        Route::get('/kintypelist', [KinTypeController::class, 'index'])->name('kintype.list');
        Route::get('/kintypeadd', [KinTypeController::class, 'add'])->name('kintype.add');
        Route::post('/kintypestore', [KinTypeController::class, 'store'])->name('kintype.store');
        Route::get('/kintypeedit/{id}', [KinTypeController::class, 'edit'])->name('kintype.edit');
        Route::post('/kintypeupdate/{id}', [KinTypeController::class, 'update'])->name('kintype.update');
    });



    Route::delete('/members/{member_id}/guarantors/{guarantor_id}', [HomeController::class, 'deleteGuarantor'])->name('deleteGuarantor')->middleware('check_user_rights:loan_guarantors_change');
    Route::get('/changeGuarantors/{member_id}/{guarantor_id}', [HomeController::class, 'changeGuarantors'])->name('changeGuarantors')->middleware('check_user_rights:loan_guarantors_change');
    Route::post('/updateGuarantors/{member_id}/{guarantor_id}', [HomeController::class, 'updateGuarantors'])->name('updateGuarantors')->middleware('check_user_rights:loan_guarantors_change');

    Route::get('/ajax-get-members', [HomeController::class, 'ajaxGetMembers'])->name('ajaxGetMembers')->middleware('check_user_rights:list_sacco_member');
    Route::get('/members/statement/{id?}', [HomeController::class, 'viewStatement'])->name('members.statement')->middleware('check_user_rights:list_member_statement');
    Route::match(['get', 'post'], '/members/contributions/{id}', [HomeController::class, 'viewContributions'])->name('members.contributions')->middleware('check_user_rights:edit_member_share_contribution');
    Route::get('/members/change-password/{id}', [HomeController::class, 'changePassword'])->name('members.changePassword')->middleware('check_user_rights:edit_password');
    Route::post('/members/change-password/{id}', [HomeController::class, 'updatePassword'])->name('members.updatePassword')->middleware('check_user_rights:edit_password');

    Route::get('/institutions/add', [HomeController::class, 'addInstitution'])->name('institutions.add')->middleware('check_user_rights:add_company');
    Route::post('/institutions/store', [HomeController::class, 'storeInstitution'])->name('institutions.store')->middleware('check_user_rights:add_company');
    Route::get('/institutions/list', [HomeController::class, 'listInstitutions'])->name('institutions.list')->middleware('check_user_rights:edit_company');

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

    Route::get('/reports/sasra/outstandingloans/{status}/{active?}', [HomeController::class, 'reportSasraLoans'])->name('reports.sasra.loans')->middleware('check_user_rights:rpt_loans_issued');
    Route::post('/reports/sasra/outstandingloans/{status}/{active?}', [HomeController::class, 'reportSasraLoans'])->name('reports.sasra.loans.post')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/sasra/loans/data', [HomeController::class, 'fetchSasraLoansData'])->name('reports.sasra.loans.data')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/sasra/share', [HomeController::class, 'reportSasraShareBalances'])->name('reports.sasra.share.balances')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/sasra/share/data', [HomeController::class, 'fetchSasraShareData'])->name('reports.sasra.share.data')->middleware('check_user_rights:rpt_loans_issued');

    Route::get('/admin/access-rights', [HomeController::class, 'adminAccessRights'])->name('admin.access-rights');
    Route::post('/admin/access-rights/save', [HomeController::class, 'user_rights_save'])->name('admin.access-rights.save');
    Route::match(['get', 'post'], '/admin/access-rights/add_modules', [HomeController::class, 'user_rights_add_module'])->name('admin.access-rights.add.module');

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

    Route::post('admin/loans/approve/{id}', [LoanApplicationSelfServiceController::class, 'approveLoan'])
        ->name('loans.approve')->middleware('check_user_rights:end_month_processing_loans');

    Route::post('admin/loans/reject/{id}', [LoanApplicationSelfServiceController::class, 'rejectLoan'])
        ->name('loans.reject')->middleware('check_user_rights:end_month_processing_loans');

    // // Update loan status (Mark as Updated)
    // Route::get('admin/loans/approve/{id}', [LoanApplicationSelfServiceController::class, 'updateLoan'])
    //     ->name('loans.approve')
    //     ->middleware('check_user_rights:end_month_processing_loans');

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
    Route::get('/search/loan_batch/members', [LoanController::class, 'searchMembers'])->name('search.loan_batch.members')->middleware('check_user_rights:add_loan_batch');
    Route::get('/search/loan_batch/member_loans', [LoanController::class, 'searchMemberLoans'])->name('loan_batch.member_loans')->middleware('check_user_rights:add_loan_batch');
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

    Route::get('/loans/categories', [HomeController::class, 'loansCategories'])->name('loans.categories')->middleware('check_user_rights:add_loan_type');
    Route::get('/loans/categories/create', [HomeController::class, 'createLoanCategory'])->name('loans.categories.create')->middleware('check_user_rights:add_loan_type');
    Route::post('/loans/categories/store', [HomeController::class, 'storeLoanCategory'])->name('loans.categories.store')->middleware('check_user_rights:add_loan_type');
    Route::get('/loans/categories/edit/{id}', [HomeController::class, 'editLoanCategory'])->name('loans.categories.edit')->middleware('check_user_rights:add_loan_type');
    Route::post('/loans/categories/update/{id}', [HomeController::class, 'updateLoanCategory'])->name('loans.categories.update')->middleware('check_user_rights:add_loan_type');
    Route::delete('/loans/categories/delete/{id}', [HomeController::class, 'deleteLoanCategory'])->name('loans.categories.delete')->middleware('check_user_rights:add_loan_type');



    Route::get('/modify/member/loans', [LoanPaymentController::class, 'index'])
        ->name('modify.member.loans')
        ->middleware('check_user_rights:modify_member_loan_journal');

    Route::post('/modify/member/loans/update', [LoanPaymentController::class, 'updateLoanPayment'])
        ->name('modify.member.loans.update')
        ->middleware('check_user_rights:modify_member_loan_journal');

    // Additional Routes
    Route::post('/modify/member/loans/pay', [LoanPaymentController::class, 'payLoan'])
        ->name('modify.member.loans.pay')
        ->middleware('check_user_rights:modify_member_loan_journal');

    Route::post('/modify/member/loans/reduce', [LoanPaymentController::class, 'reduceLoan'])
        ->name('modify.member.loans.reduce')
        ->middleware('check_user_rights:modify_member_loan_journal');



    Route::get('/modify/member/loans/asset-accounts', [LoanPaymentController::class, 'getAssetAccounts'])
        ->name('modify.member.loans.asset.accounts')
        ->middleware('check_user_rights:modify_member_loan_journal');



    Route::get('/guarantors/deduction', [HomeController::class, 'guarantorsDeduction'])->name('guarantors.deduction')->name('loans.categories.delete')->middleware('check_user_rights:loan_guarantors_change');
    Route::get('/guarantors/reset', [HomeController::class, 'guarantorsReset'])->name('guarantors.reset')->name('loans.categories.delete')->middleware('check_user_rights:loan_guarantors_change');

    Route::get('/reports/members/status', [HomeController::class, 'reportsMembersStatus'])->name('reports.members.status')->middleware('check_user_rights:list_member_statement');
    Route::get('/reports/loans/issued', [HomeController::class, 'reportsLoansIssued'])->name('reports.loans.issued')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/issued/data', [HomeController::class, 'getLoansIssued'])->name('reports.loans.issued.data')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/issued/download', [HomeController::class, 'downloadLoansIssuedReport'])->name('reports.loans.issued.download')->middleware('check_user_rights:rpt_loans_issued');
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

    Route::get('/reports/accounts/trial-balance', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.trial-balance')->middleware('check_user_rights:rpt_trial_balance');
    Route::get('/reports/accounts/profit-loss', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.profit-loss')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/accounts/profit-loss/budget', [HomeController::class, 'reportsAccountsTrialBalanceBudget'])->name('reports.accounts.profit-loss.budget')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/accounts/balance-sheet', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.balance-sheet')->middleware('check_user_rights:rpt_balance_sheet');
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
    Route::delete('/files/delete/{file}', [FileUploadController::class, 'delete'])->name('file.delete');


    //Route::get('/randomize-members', [RandController::class, 'randomizeMembers'])->name('randomize.members')->middleware('check_user_rights:testing_randomize_members');
    // Route::get('/randomize-loan-payments', [RandController::class, 'randomizeLoanPayments'])->name('randomize.loan.payments')->middleware('check_user_rights:testing_randomize_members');
    // Route::get('/randomize-shares', [RandController::class, 'randomizeShares'])->name('randomize.shares')->middleware('check_user_rights:testing_randomize_members');
    // Route::get('/randomize-accounts-transactions', [RandController::class, 'randomizeAccountsTransactions'])->name('randomize.accounts.transactions')->middleware('check_user_rights:testing_randomize_members');
    // Route::post('/temp/import_capital', [TempCapitalImportController::class, 'importCapital'])->name('temp.import_capital')->middleware('check_user_rights:add_period');
    // Route::get('/test/test', function () {
    //     return view('test.test');
    // })->name('test.view')->middleware('check_user_rights:add_period');
});
