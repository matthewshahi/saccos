<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Auth\CustomAuthController;
 


Route::get('/', [HomeController::class, 'redirectBasedOnAuth'])->name('home');
Route::get('/home', [HomeController::class, 'redirectBasedOnAuth'])->name('home');




Route::get('login', [CustomAuthController::class, 'showLoginForm'])->name('login');
Route::post('login', [CustomAuthController::class, 'login']);
Route::post('logout', [CustomAuthController::class, 'logout'])->name('logout');


Route::middleware(['auth'])->group(function () {
 
    // Member statement route for non-officials
    Route::get('/members/statement/self', [HomeController::class, 'viewStatement'])
        ->name('members.statement');    
    
    Route::get('/members/status/{self}', [HomeController::class, 'memberStatus'])
        ->name('members.status') ; 

    Route::get('/loans/apply', [HomeController::class, 'loansApply'])->name('loans.apply');

    Route::get('/loans/guarantee/requests', [HomeController::class, 'listGuaranteeRequests'])->name('loans.guarantee.requests');
    Route::get('/loans/pending/approval', [HomeController::class, 'listLoansPendingApproval'])->name('loans.pending.approval');

    Route::get('/loans/types/list', [HomeController::class, 'loansTypesList'])->name('loans.types.list');



    Route::get('/profile/password', [HomeController::class, 'showChangeSelfPasswordForm'])->name('profile.password');
    Route::post('/profile/password', [HomeController::class, 'updateSelfPassword'])->name('profile.updatePassword');

        
});



// Middleware group for authentication
Route::middleware(['auth', 'check_member_position'])->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard')->middleware('auth');
    // Members
     

    Route::get('/members/list', [HomeController::class, 'membersList'])->name('members.list')->middleware('check_user_rights:list_sacco_member');
    Route::get('/members/add', [HomeController::class, 'addNewMember'])->name('members.add')->middleware('check_user_rights:add_new_sacco_member');
 
 
    Route::post('/members/store', [HomeController::class, 'storeNewMember'])->name('members.store')->middleware('check_user_rights:add_new_sacco_member');
    Route::get('/members/active/{status}', [HomeController::class, 'membersActive'])->name('members.active')->middleware('check_user_rights:list_sacco_member');

    Route::get('/members/edit/{id}', [HomeController::class, 'editMember'])->name('members.edit')->middleware('check_user_rights:edit_member'); 
    Route::post('/members/update/{id}', [HomeController::class, 'updateMember'])->name('members.update')->middleware('check_user_rights:edit_member'); 
    Route::get('/members/next-of-kin/{id}', [HomeController::class, 'editNextOfKin'])->name('members.nextOfKin')->middleware('check_user_rights:add_next_of_kin'); 
    Route::post('/members/next-of-kin/update/{id}', [HomeController::class, 'updateNextOfKin'])->name('members.updateNextOfKin')->middleware('check_user_rights:add_next_of_kin'); 
    Route::get('/members/delete-next-of-kin/{member_id}/{kin_id}', [HomeController::class, 'deleteNextOfKin'])->name('members.deleteNextOfKin')->middleware('check_user_rights:add_next_of_kin'); 
    
    Route::get('/members/status/{id}', [HomeController::class, 'memberStatus'])->name('members.status')->middleware('check_user_rights:rpt_loans_issued'); 

    Route::delete('/members/{member_id}/guarantors/{guarantor_id}', [HomeController::class, 'deleteGuarantor'])->name('deleteGuarantor')->middleware('check_user_rights:loan_guarantors_change');
    
    Route::delete('/guarantor/{member_id}/{guarantor_id}', [HomeController::class, 'deleteGuarantor'])->name('deleteGuarantor')->middleware('check_user_rights:loan_guarantors_change');
    Route::get('/changeGuarantors/{member_id}/{guarantor_id}', [HomeController::class, 'changeGuarantors'])->name('changeGuarantors')->middleware('check_user_rights:loan_guarantors_change');

    Route::post('/updateGuarantors/{member_id}/{guarantor_id}', [HomeController::class, 'updateGuarantors'])->name('updateGuarantors')->middleware('check_user_rights:loan_guarantors_change');

    Route::get('/ajax-get-members', [HomeController::class, 'ajaxGetMembers'])->name('ajaxGetMembers')->middleware('check_user_rights:list_sacco_member');

    Route::get('/members/statement/{id?}', [HomeController::class, 'viewStatement'])
    ->name('members.statement')
    ->middleware('check_user_rights:rpt_loans_issued');
    Route::match(['get', 'post'], '/members/contributions/{id}', [HomeController::class, 'viewContributions'])->name('members.contributions')->middleware('check_user_rights:edit_member_share_contribution');
   
    Route::get('/members/change-password/{id}', [HomeController::class, 'changePassword'])->name('members.changePassword')->middleware('check_user_rights:edit_password');
    Route::post('/members/change-password/{id}', [HomeController::class, 'updatePassword'])->name('members.updatePassword')->middleware('check_user_rights:edit_password');

    // Institutions
    Route::get('/institutions/add', [HomeController::class, 'addInstitution'])->name('institutions.add')->middleware('check_user_rights:add_company');
    Route::post('/institutions/store', [HomeController::class, 'storeInstitution'])->name('institutions.store')->middleware('check_user_rights:add_company');
    Route::get('/institutions/list', [HomeController::class, 'listInstitutions'])->name('institutions.list')->middleware('check_user_rights:edit_company');

   // Member share deposits
   
   Route::get('/modify/member/shares', [HomeController::class, 'modifyShares'])->name('modify.member.shares')->middleware('check_user_rights:modify_member_shares_journal');
   Route::post('/modify/member/shares', [HomeController::class, 'modifyShares'])->middleware('check_user_rights:modify_member_shares_journal');
   Route::get('/search/members', [HomeController::class, 'searchMembers'])->name('search.members')->middleware('check_user_rights:modify_member_shares_journal');
   Route::get('/search/accounts', [HomeController::class, 'searchAccounts'])->name('search.accounts')->middleware('check_user_rights:modify_member_shares_journal');

   Route::match(['get', 'post'], '/transfer/member/shares', [HomeController::class, 'transferShares'])->name('transfer.member.shares')->middleware('check_user_rights:transfer_member_shares');
   
   Route::match(['get', 'post'], '/proc/end/month/shares', [HomeController::class, 'processEndMonthShares'])->name('proc.end.month.shares')->middleware('check_user_rights:end_month_processing_shares');

   Route::match(['get', 'post'], '/modify/member/share/capital', [HomeController::class, 'modifyShareCapital'])->name('modify.member.share.capital')->middleware('check_user_rights:transfer_member_shares');
   Route::match(['get', 'post'], '/transfer/member/capital/shares', [HomeController::class, 'transferCapitalShares'])->name('transfer.member.capital.shares')->middleware('check_user_rights:transfer_member_shares');
   Route::match(['get', 'post'], '/transfer/share/to/capital/shares', [HomeController::class, 'transferShareToCapitalShares'])->name('transfer.share.to.capital.shares')->middleware('check_user_rights:transfer_member_shares');
 
    // Member share CAPITAL

    
    Route::match(['get', 'post'], '/modify/member/share/capital', [HomeController::class, 'modifyShareCapital'])->name('modify.member.share.capital')->middleware('check_user_rights:modify_member_shares_capital');

    Route::get('/transfer/member/capital/shares', [HomeController::class, 'transferCapitalShares'])->name('transfer.member.capital.shares')->middleware('check_user_rights:modify_member_shares_capital');

    // FOSA
     
    Route::match(['get', 'post'], '/modify/member/fosas', [HomeController::class, 'modifyFosas'])->name('modify.member.fosas')->middleware('check_user_rights:modify_member_shares_journal');
    Route::match(['get', 'post'], '/transfer/member/fosa', [HomeController::class, 'transferFosa'])->name('transfer.member.fosa')->middleware('check_user_rights:modify_member_shares_journal');

    Route::get('/proc/end/month/fosa', [HomeController::class, 'endMonthFosa'])->name('proc.end.month.fosa')->middleware('check_user_rights:modify_member_shares_journal');

    // SASRA Reports
    Route::get('/reports/sasra/outstandingloans/{status}/{active?}', [HomeController::class, 'reportSasraLoans'])
    ->name('reports.sasra.loans')
    ->middleware('check_user_rights:rpt_loans_issued');

    Route::post('/reports/sasra/outstandingloans/{status}/{active?}', [HomeController::class, 'reportSasraLoans'])
        ->name('reports.sasra.loans.post')
        ->middleware('check_user_rights:rpt_loans_issued');

    Route::get('/reports/sasra/loans/data', [HomeController::class, 'fetchSasraLoansData'])
        ->name('reports.sasra.loans.data')
        ->middleware('check_user_rights:rpt_loans_issued');

        Route::get('/reports/sasra/share', [HomeController::class, 'reportSasraShareBalances'])
            ->name('reports.sasra.share.balances')
            ->middleware('check_user_rights:rpt_loans_issued');

        Route::get('/reports/sasra/share/data', [HomeController::class, 'fetchSasraShareData'])
            ->name('reports.sasra.share.data')
            ->middleware('check_user_rights:rpt_loans_issued');

            
        Route::get('/admin/access-rights', [HomeController::class, 'adminAccessRights'])->name('admin.access-rights')->middleware('check_user_rights:modify_useraccessrights'); 
        Route::post('/admin/access-rights/save', [HomeController::class, 'user_rights_save'])->name('admin.access-rights.save')->middleware('check_user_rights:modify_useraccessrights');
        Route::match(['get', 'post'], '/admin/access-rights/add_modules', [HomeController::class, 'user_rights_add_module'])->name('admin.access-rights.add.module')->middleware('check_user_rights:modify_useraccessrights');



        Route::match(['get', 'post'], '/reports/profit_and_loss', [HomeController::class, 'reportSasraProfitAndLoss'])
            ->name('reports.sasra.profitandloss')
            ->middleware('check_user_rights:rpt_loans_issued');
        
        Route::get('/reports/profit_and_loss/data', [HomeController::class, 'fetchSasraProfitAndLossData'])
            ->name('reports.sasra.profitandloss.data')
            ->middleware('check_user_rights:rpt_loans_issued');
        
        

        Route::get('/reports/sasra/finance_position_report', [HomeController::class, 'reportSasraFinancePositionReport'])
        ->name('reports.sasra.financeposition')
        ->middleware('check_user_rights:rpt_loans_issued');

        Route::get('/reports/sasra/income_report', [HomeController::class, 'reportSasraIncomeReport'])
        ->name('reports.sasra.income')
        ->middleware('check_user_rights:rpt_loans_issued');
 

        Route::get('/reports/sasra/loan_performance/data', [HomeController::class, 'fetchLoanPerformanceData'])
        ->name('reports.sasra.loanperformance.data')
        ->middleware('check_user_rights:rpt_loans_issued');
        
        Route::get('/reports/sasra/loan_performance/{version?}', [HomeController::class, 'reportSasraLoanPerformance'])
        ->name('reports.sasra.loanperformance')
        ->middleware('check_user_rights:rpt_loans_issued');




        Route::get('/reports/sasra/insider_lending', [HomeController::class, 'reportSasraInsiderLending'])
        ->name('reports.sasra.insiderlending')
        ->middleware('check_user_rights:rpt_loans_issued');

        Route::get('/reports/sasra/outstandingloans/y', [HomeController::class, 'reportSasraLoansAllOutstanding'])
        ->name('reports.sasra.loans.alloutstanding')
        ->middleware('check_user_rights:rpt_loans_issued');

        Route::get('/reports/sasra/deposits', [HomeController::class, 'reportSasraCapitalBalances'])
        ->name('reports.sasra.capitalbalances')
        ->middleware('check_user_rights:rpt_loans_issued');

        Route::get('/reports/sasra/return_on_investment_report', [HomeController::class, 'reportSasraReturnOnInvestmentReport'])
        ->name('reports.sasra.returnoninvestment')
        ->middleware('check_user_rights:rpt_loans_issued');

    // End month processing
    Route::get('/list/contribution', [HomeController::class, 'listContribution'])->name('list.contribution')->middleware('check_user_rights:list_sacco_member_contributions');
    Route::get('/proc/end/month/loans', [HomeController::class, 'endMonthLoans'])->name('proc.end.month.loans');


    
    Route::post('/loans/apply', [HomeController::class, 'submitLoanApplication'])->name('loans.application.submit');
    Route::get('/loans/approval', [HomeController::class, 'listLoansForApproval'])->name('loans.approval');
    Route::get('/loans/approve/{loanId}', [HomeController::class, 'approveLoan'])->name('loans.approve');
    Route::get('/loans/delete/{loanId}', [HomeController::class, 'deleteLoan'])->name('loans.delete');

    
    Route::get('/admin/loans/pending/approval', [HomeController::class, 'adminListLoansPendingApproval'])->name('admin.loans.pending.approval')->middleware('check_user_rights:update_loan_batch');
    Route::get('/admin/end-of-year-processing', [HomeController::class, 'showEndOfYearProcessingForm'])->name('admin.show-end-of-year-processing-form')->middleware('check_user_rights:end_of_year_processing');
    Route::post('/admin/end-of-year-processing', [HomeController::class, 'endOfYearProcessing'])->name('admin.end-of-year-processing')->middleware('check_user_rights:end_of_year_processing');


    // Loans
    Route::get('/loans/issued', [HomeController::class, 'loansIssued'])->name('loans.issued');
    Route::get('/loans/batch', [HomeController::class, 'loansBatch'])->name('loans.batch');
  
    Route::get('/loans/end-month', [HomeController::class, 'loansEndMonth'])->name('loans.end-month');
    Route::get('/loans/un-finished', [HomeController::class, 'loansUnFinished'])->name('loans.un-finished');
    Route::get('/loans/shares-to-loans', [HomeController::class, 'loansSharesToLoans'])->name('loans.shares-to-loans');
    
    Route::get('/loans/types', [HomeController::class, 'loansTypes'])->name('loans.types')->middleware('check_user_rights:add_loan_type');
    Route::get('/loans/types/edit/{id}', [HomeController::class, 'editLoanType'])->name('loans.types.edit')->middleware('check_user_rights:add_loan_type');
    Route::put('/loans/types/update/{id}', [HomeController::class, 'updateLoanType'])->name('loans.types.update')->middleware('check_user_rights:add_loan_type');
    Route::get('/loans/types/add', [HomeController::class, 'createLoanType'])->name('loans.types.add')->middleware('check_user_rights:add_loan_type');
    Route::post('/loans/types/store', [HomeController::class, 'storeLoanType'])->name('loans.types.store')->middleware('check_user_rights:add_loan_type');
    Route::delete('/loans/types/delete/{id}', [HomeController::class, 'deleteLoanType'])->name('loans.types.delete')->middleware('check_user_rights:add_loan_type');
 

    // Route::get('/loans/types/add', [HomeController::class, 'addLoanType'])->name('addLoanType')->middleware('check_user_rights:add_loan_type');
    // Route::get('/loans/types/edit/{id}', [HomeController::class, 'editLoanType'])->name('editLoanType')->middleware('check_user_rights:add_loan_type');
    // Route::delete('/loans/types/delete/{id}', [HomeController::class, 'deleteLoanType'])->name('deleteLoanType')->middleware('check_user_rights:add_loan_type');



    Route::get('/loans/categories', [HomeController::class, 'loansCategories'])->name('loans.categories')->middleware('check_user_rights:add_loan_type');
    Route::get('/loans/categories/create', [HomeController::class, 'createLoanCategory'])->name('loans.categories.create')->middleware('check_user_rights:add_loan_type');
    Route::post('/loans/categories/store', [HomeController::class, 'storeLoanCategory'])->name('loans.categories.store')->middleware('check_user_rights:add_loan_type');
    Route::get('/loans/categories/edit/{id}', [HomeController::class, 'editLoanCategory'])->name('loans.categories.edit')->middleware('check_user_rights:add_loan_type');
    Route::post('/loans/categories/update/{id}', [HomeController::class, 'updateLoanCategory'])->name('loans.categories.update')->middleware('check_user_rights:add_loan_type');
    Route::delete('/loans/categories/delete/{id}', [HomeController::class, 'deleteLoanCategory'])->name('loans.categories.delete')->middleware('check_user_rights:add_loan_type');


    Route::get('/loans/calculator', [HomeController::class, 'loansCalculator'])->name('loans.calculator');

    // Guarantors
    Route::get('/guarantors/deduction', [HomeController::class, 'guarantorsDeduction'])->name('guarantors.deduction');
    Route::get('/guarantors/reset', [HomeController::class, 'guarantorsReset'])->name('guarantors.reset');

    // Reports
       
    Route::get('/reports/members/status', [HomeController::class, 'reportsMembersStatus'])->name('reports.members.status');
    Route::get('/reports/loans/issued', [HomeController::class, 'reportsLoansIssued'])->name('reports.loans.issued')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/issued/data', [HomeController::class, 'getLoansIssued'])->name('reports.loans.issued.data')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/issued/download', [HomeController::class, 'downloadLoansIssuedReport'])->name('reports.loans.issued.download')->middleware('check_user_rights:rpt_loans_issued');

    // Route::get('/reports/loans/repayments', [HomeController::class, 'reportsLoansRepayments'])->name('reports.loans.repayments')->middleware('check_user_rights:rpt_loans_repayments')->middleware('check_user_rights:rpt_loans_issued');
    // Route::get('/reports/loans/repayments/data', [HomeController::class, 'getLoansRepayments'])->name('reports.loans.repayments.data')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/repayments', [HomeController::class, 'reportsLoansRepayments'])->name('reports.loans.repayments')->middleware('check_user_rights:rpt_loans_repayments');
    Route::get('/reports/loans/repayments/data', [HomeController::class, 'getLoansRepayments'])->name('reports.loans.repayments.data')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/repayments/download', [HomeController::class, 'downloadLoansRepaymentsReport'])->name('reports.loans.repayments.download')->middleware('check_user_rights:rpt_loans_issued');
    



    Route::get('/reports/members/status/data', [HomeController::class, 'reportsMembersStatusData'])->name('reports.members.status.data');
    Route::get('/reports/members/status/download', [HomeController::class, 'downloadMembersStatus'])->name('reports.members.status.download');


    Route::get('/reports/shares/contribution', [HomeController::class, 'reportsSharesContribution'])->name('reports.shares.contribution');
    Route::get('/reports/shares/capital', [HomeController::class, 'reportsSharesCapital'])->name('reports.shares.capital');
    Route::get('/reports/fosa/contribution', [HomeController::class, 'reportsFosaContribution'])->name('reports.fosa.contribution');
    Route::get('/reports/loans/balances', [HomeController::class, 'reportsLoansBalances'])->name('reports.loans.balances');
    Route::get('/reports/shares/period', [HomeController::class, 'reportsSharesPeriod'])->name('reports.shares.period');

    Route::get('/reports/loans/issued', [HomeController::class, 'reportsLoansIssued'])->name('reports.loans.issued')->middleware('check_user_rights:rpt_loans_issued');
    Route::get('/reports/loans/issued/data', [HomeController::class, 'getLoansIssued'])->name('reports.loans.issued.data')->middleware('check_user_rights:rpt_loans_issued');


    Route::get('/reports/loans/member', [HomeController::class, 'reportsLoansMember'])->name('reports.loans.member');
    Route::get('/reports/guarantors', [HomeController::class, 'reportsGuarantors'])->name('reports.guarantors');
    Route::get('/reports/contributions', [HomeController::class, 'reportsContributions'])->name('reports.contributions');
    Route::get('/reports/contributions/principal', [HomeController::class, 'reportsContributionsPrincipal'])->name('reports.contributions.principal');
    Route::get('/reports/loans/repayments', [HomeController::class, 'reportsLoansRepayments'])->name('reports.loans.repayments');
    Route::get('/reports/loans/balances/period', [HomeController::class, 'reportsLoansBalancesPeriod'])->name('reports.loans.balances.period');

    Route::get('/reports/accounts/ledger', [HomeController::class, 'reportsAccountsLedger'])->name('reports.accounts.ledger')->middleware('check_user_rights:rpt_acc_trans');
    Route::get('/reports/accounts/trial-balance', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.trial-balance')->middleware('check_user_rights:rpt_trial_balance'); 
    Route::get('/reports/accounts/profit-loss', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.profit-loss')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/accounts/profit-loss/budget', [HomeController::class, 'reportsAccountsTrialBalanceBudget'])->name('reports.accounts.profit-loss.budget')->middleware('check_user_rights:rpt_profit_loss');
    Route::get('/reports/accounts/profit-loss/budget', [HomeController::class, 'reportsAccountsTrialBalanceBudget'])->name('reports.accounts.profit-loss-budget')->middleware('check_user_rights:rpt_profit_loss');

    
    Route::get('/reports/accounts/accounts/alltimetrialbalance', [HomeController::class, 'reportsAccountsAllTime'])->name('reports.accounts.AllTimeAccountsFullTrialBalance')->middleware('check_user_rights:rpt_trial_balance');

    Route::get('/reports/accounts/accounts/alltimetprofitandloss', [HomeController::class, 'reportsAccountsAllTime'])->name('reports.accounts.AllTimeAccountsFullProftAndLoss')->middleware('check_user_rights:rpt_trial_balance');
    
    Route::get('/reports/accounts/accounts/alltimetbalancesheet', [HomeController::class, 'reportsAccountsAllTime'])->name('reports.accounts.AllTimeAccountsFullBalanceSheet')->middleware('check_user_rights:rpt_trial_balance');
    


                                          

    


    Route::get('/reports/accounts/balance-sheet', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.balance-sheet')->middleware('check_user_rights:rpt_balance_sheet');
    
    Route::get('/reports/accounts/budget-vs-actuals', [HomeController::class, 'reportsAccountsBudgetVsActuals'])->name('reports.accounts.budget-vs-actuals')->middleware('check_user_rights:rpt_balance_sheet');



    Route::get('/reports/accounts/trial-balance-horizontal', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.trial-balance-horizontal')->middleware('check_user_rights:rpt_balance_sheet');
    Route::get('/reports/accounts/profit-loss-horizontal', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.profit-loss-horizontal')->middleware('check_user_rights:rpt_balance_sheet');
    Route::get('/reports/accounts/balance-sheet-horizontal', [HomeController::class, 'reportsAccountsTrialBalance'])->name('reports.accounts.balance-sheet-horizontal')->middleware('check_user_rights:rpt_balance_sheet');



    // Interest & Dividends
    Route::get('/interest/shares', [HomeController::class, 'interestShares'])->name('interest.shares');
    Route::get('/dividends/shares', [HomeController::class, 'dividendsShares'])->name('dividends.shares');
    Route::get('/interest/fosa', [HomeController::class, 'interestFosa'])->name('interest.fosa');

    // Journal Accounts
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



    // Downloads
    Route::get('/downloads/view', [HomeController::class, 'downloadsView'])->name('downloads.view');
    Route::get('/downloads/add-type', [HomeController::class, 'downloadsAddType'])->name('downloads.add-type');

    // Sacco Admin
    Route::get('/admin/users', [HomeController::class, 'adminUsers'])->name('admin.users');
    Route::get('/admin/user-types', [HomeController::class, 'adminUserTypes'])->name('admin.user-types');
   
    // Route::get('/admin/modules', [HomeController::class, 'adminModules'])->name('admin.modules');
    
    Route::get('/admin/defaults', [HomeController::class, 'adminDefaults'])->name('admin.defaults')->middleware('check_user_rights:add_default'); 
    Route::post('/admin/defaults/update', [HomeController::class, 'updateDefaults'])->name('admin.defaults.update')->middleware('check_user_rights:add_default');
    Route::post('/admin/defaults/store', [HomeController::class, 'storeDefault'])->name('admin.defaults.store')->middleware('check_user_rights:add_default');

     Route::get('/admin/budget', [HomeController::class, 'adminBudget'])->name('admin.budget')->middleware('check_user_rights:add_sub_account');
    Route::post('/admin/budget/store', [HomeController::class, 'adminBudget_store'])->name('admin.budget.store')->middleware('check_user_rights:add_sub_account');

    Route::get('/admin/year-end', [HomeController::class, 'adminYearEnd'])->name('admin.year-end');

    Route::get('/admin/periods', [HomeController::class, 'adminPeriods'])->name('admin.periods')->middleware('check_user_rights:add_period');
    Route::get('/admin/periods/create', [HomeController::class, 'adminPeriodsCreate'])->name('admin.periods.create')->middleware('check_user_rights:add_period');
    Route::post('/admin/periods/store', [HomeController::class, 'adminPeriodsStore'])->name('admin.periods.store')->middleware('check_user_rights:add_period');
    Route::get('/admin/periods/activate/{id}', [HomeController::class, 'adminPeriodsActivate'])->name('admin.periods.activate')->middleware('check_user_rights:add_period');


     
    
});



