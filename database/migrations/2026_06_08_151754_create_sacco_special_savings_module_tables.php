<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Special Saving Categories
        |--------------------------------------------------------------------------
        */
        Schema::create('sacco_special_saving_categories', function (Blueprint $table) {
            $table->increments('special_saving_category_id');

            $table->string('special_saving_category_name', 150);
            $table->string('special_saving_category_code', 50);
            $table->text('special_saving_category_description')->nullable();

            /*
             * Mandatory accounting sub accounts.
             * These should point to sacco_sub_account.sub_account_id.
             * No hard FK is added.
             */
            $table->unsignedInteger('special_saving_liability_sub_account_id');
            $table->unsignedInteger('special_saving_interest_expense_sub_account_id');
            $table->unsignedInteger('special_saving_interest_payable_sub_account_id');
            $table->unsignedInteger('special_saving_penalty_income_sub_account_id');
            $table->unsignedInteger('special_saving_charge_income_sub_account_id');
            $table->unsignedInteger('special_saving_default_cash_sub_account_id');

            $table->string('special_saving_category_status', 20)->default('Active');

            $table->unsignedInteger('special_saving_category_by')->nullable();
            $table->string('special_saving_category_ip', 100)->nullable();
            $table->timestamp('special_saving_category_transdate')->useCurrent();

            $table->char('special_saving_category_deleted', 1)->default('N');
            $table->unsignedInteger('special_saving_category_deleted_by')->nullable();
            $table->dateTime('special_saving_category_deleted_on')->nullable();
            $table->string('special_saving_category_deleted_ip', 100)->nullable();

            $table->unique('special_saving_category_code', 'ss_cat_code_unique');

            $table->index('special_saving_liability_sub_account_id', 'ss_cat_liability_acc_idx');
            $table->index('special_saving_interest_expense_sub_account_id', 'ss_cat_interest_exp_acc_idx');
            $table->index('special_saving_interest_payable_sub_account_id', 'ss_cat_interest_pay_acc_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | 2. Special Saving Products
        |--------------------------------------------------------------------------
        */
        Schema::create('sacco_special_saving_products', function (Blueprint $table) {
            $table->increments('special_saving_product_id');

            $table->unsignedInteger('special_saving_product_category_id');

            $table->string('special_saving_product_name', 150);
            $table->string('special_saving_product_code', 50);
            $table->text('special_saving_product_description')->nullable();

            /*
             * FLAT = one fixed rate.
             * TIERED = rate comes from sacco_special_saving_product_rate_tiers.
             */
            $table->string('special_saving_product_rate_mode', 20)->default('FLAT');

            $table->decimal('special_saving_product_annual_interest_rate', 10, 6)->default(0);
            $table->decimal('special_saving_product_monthly_interest_rate', 10, 6)->default(0);

            /*
             * CLOSING_BALANCE
             * MINIMUM_MONTHLY_BALANCE
             * DAILY_BALANCE
             */
            $table->string('special_saving_product_interest_method', 40)->default('MINIMUM_MONTHLY_BALANCE');

            $table->string('special_saving_product_interest_posting_frequency', 20)->default('MONTHLY');

            $table->char('special_saving_product_require_full_month', 1)->default('Y');
            $table->unsignedInteger('special_saving_product_member_minimum_days')->default(30);
            $table->unsignedInteger('special_saving_product_deposit_minimum_days')->default(10);

            $table->unsignedInteger('special_saving_product_withdrawal_cycle_months')->default(6);
            $table->char('special_saving_product_early_withdrawal_allowed', 1)->default('Y');

            $table->string('special_saving_product_early_withdrawal_interest_policy', 60)
                ->default('FORFEIT_UNVESTED_INTEREST');

            $table->string('special_saving_product_interest_vesting_policy', 60)
                ->default('AFTER_WITHDRAWAL_CYCLE');

            $table->string('special_saving_product_interest_credit_policy', 40)
                ->default('KEEP_SEPARATE');

            $table->decimal('special_saving_product_minimum_opening_amount', 15, 2)->default(0);
            $table->decimal('special_saving_product_minimum_monthly_contribution', 15, 2)->default(0);
            $table->decimal('special_saving_product_minimum_balance', 15, 2)->default(0);

            $table->char('special_saving_product_can_secure_loan', 1)->default('N');
            $table->char('special_saving_product_allow_member_transfer', 1)->default('Y');
            $table->char('special_saving_product_allow_mpesa_collection', 1)->default('Y');

            $table->string('special_saving_product_status', 20)->default('Active');

            $table->unsignedInteger('special_saving_product_by')->nullable();
            $table->string('special_saving_product_ip', 100)->nullable();
            $table->timestamp('special_saving_product_transdate')->useCurrent();

            $table->char('special_saving_product_deleted', 1)->default('N');
            $table->unsignedInteger('special_saving_product_deleted_by')->nullable();
            $table->dateTime('special_saving_product_deleted_on')->nullable();
            $table->string('special_saving_product_deleted_ip', 100)->nullable();

            $table->unique('special_saving_product_code', 'ss_product_code_unique');

            $table->index('special_saving_product_category_id', 'ss_product_category_idx');
            $table->index('special_saving_product_interest_method', 'ss_product_interest_method_idx');
            $table->index('special_saving_product_status', 'ss_product_status_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | 3. Product Rate Tiers
        |--------------------------------------------------------------------------
        */
        Schema::create('sacco_special_saving_product_rate_tiers', function (Blueprint $table) {
            $table->increments('special_saving_rate_tier_id');

            $table->unsignedInteger('special_saving_rate_tier_product_id');

            $table->decimal('special_saving_rate_tier_min_amount', 15, 2)->default(0);
            $table->decimal('special_saving_rate_tier_max_amount', 15, 2)->nullable();

            $table->decimal('special_saving_rate_tier_annual_rate', 10, 6)->default(0);
            $table->decimal('special_saving_rate_tier_monthly_rate', 10, 6)->default(0);

            $table->string('special_saving_rate_tier_status', 20)->default('Active');

            $table->unsignedInteger('special_saving_rate_tier_by')->nullable();
            $table->string('special_saving_rate_tier_ip', 100)->nullable();
            $table->timestamp('special_saving_rate_tier_transdate')->useCurrent();

            $table->char('special_saving_rate_tier_deleted', 1)->default('N');
            $table->unsignedInteger('special_saving_rate_tier_deleted_by')->nullable();
            $table->dateTime('special_saving_rate_tier_deleted_on')->nullable();
            $table->string('special_saving_rate_tier_deleted_ip', 100)->nullable();

            $table->index('special_saving_rate_tier_product_id', 'ss_rate_product_idx');
            $table->index([
                'special_saving_rate_tier_product_id',
                'special_saving_rate_tier_min_amount',
                'special_saving_rate_tier_max_amount'
            ], 'ss_rate_amount_range_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | 4. Member Special Saving Accounts
        |--------------------------------------------------------------------------
        */
        Schema::create('sacco_special_saving_accounts', function (Blueprint $table) {
            $table->increments('special_saving_account_id');

            $table->unsignedInteger('special_saving_account_member_id');
            $table->unsignedInteger('special_saving_account_product_id');

            $table->string('special_saving_account_number', 100)->nullable();

            $table->date('special_saving_account_opening_date');
            $table->date('special_saving_account_last_interest_date')->nullable();

            $table->date('special_saving_account_last_withdrawal_date')->nullable();
            $table->date('special_saving_account_next_free_withdrawal_date')->nullable();

            $table->decimal('special_saving_account_principal_balance', 15, 2)->default(0);
            $table->decimal('special_saving_account_accrued_interest_balance', 15, 2)->default(0);
            $table->decimal('special_saving_account_available_interest_balance', 15, 2)->default(0);
            $table->decimal('special_saving_account_forfeited_interest_balance', 15, 2)->default(0);

            $table->decimal('special_saving_account_total_balance', 15, 2)->default(0);

            $table->string('special_saving_account_status', 30)->default('Active');

            $table->text('special_saving_account_notes')->nullable();

            $table->unsignedInteger('special_saving_account_by')->nullable();
            $table->string('special_saving_account_ip', 100)->nullable();
            $table->timestamp('special_saving_account_transdate')->useCurrent();

            $table->char('special_saving_account_deleted', 1)->default('N');
            $table->unsignedInteger('special_saving_account_deleted_by')->nullable();
            $table->dateTime('special_saving_account_deleted_on')->nullable();
            $table->string('special_saving_account_deleted_ip', 100)->nullable();

            $table->unique('special_saving_account_number', 'ss_account_number_unique');

            $table->index('special_saving_account_member_id', 'ss_account_member_idx');
            $table->index('special_saving_account_product_id', 'ss_account_product_idx');
            $table->index('special_saving_account_status', 'ss_account_status_idx');
            $table->index([
                'special_saving_account_member_id',
                'special_saving_account_product_id'
            ], 'ss_account_member_product_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | 5. Special Saving Transactions
        |--------------------------------------------------------------------------
        */
        Schema::create('sacco_special_saving_transactions', function (Blueprint $table) {
            $table->increments('special_saving_transaction_id');

            $table->unsignedInteger('special_saving_transaction_account_id');
            $table->unsignedInteger('special_saving_transaction_member_id');
            $table->unsignedInteger('special_saving_transaction_product_id');

            $table->string('special_saving_transaction_type', 40);

            $table->string('special_saving_transaction_direction', 10)->default('CREDIT');

            $table->decimal('special_saving_transaction_amount', 15, 2)->default(0);

            $table->decimal('special_saving_transaction_principal_amount', 15, 2)->default(0);
            $table->decimal('special_saving_transaction_interest_amount', 15, 2)->default(0);
            $table->decimal('special_saving_transaction_penalty_amount', 15, 2)->default(0);
            $table->decimal('special_saving_transaction_charge_amount', 15, 2)->default(0);

            $table->date('special_saving_transaction_date');
            $table->string('special_saving_transaction_period', 6)->nullable();

            $table->string('special_saving_transaction_doc_no', 100)->nullable();
            $table->string('special_saving_transaction_reference', 150)->nullable();
            $table->string('special_saving_transaction_source', 50)->nullable();

            $table->unsignedInteger('special_saving_transaction_sub_account_id')->nullable();
            $table->char('special_saving_transaction_ledger_posted', 1)->default('N');
            $table->string('special_saving_transaction_ledger_ref', 150)->nullable();

            $table->decimal('special_saving_transaction_principal_balance_after', 15, 2)->default(0);
            $table->decimal('special_saving_transaction_accrued_interest_after', 15, 2)->default(0);
            $table->decimal('special_saving_transaction_available_interest_after', 15, 2)->default(0);
            $table->decimal('special_saving_transaction_total_balance_after', 15, 2)->default(0);

            $table->char('special_saving_transaction_reversed', 1)->default('N');
            $table->unsignedInteger('special_saving_transaction_reversed_by')->nullable();
            $table->dateTime('special_saving_transaction_reversed_on')->nullable();
            $table->string('special_saving_transaction_reversal_reason', 255)->nullable();
            $table->unsignedInteger('special_saving_transaction_original_id')->nullable();

            $table->text('special_saving_transaction_description')->nullable();

            $table->unsignedInteger('special_saving_transaction_by')->nullable();
            $table->string('special_saving_transaction_ip', 100)->nullable();
            $table->timestamp('special_saving_transaction_transdate')->useCurrent();

            $table->char('special_saving_transaction_deleted', 1)->default('N');
            $table->unsignedInteger('special_saving_transaction_deleted_by')->nullable();
            $table->dateTime('special_saving_transaction_deleted_on')->nullable();
            $table->string('special_saving_transaction_deleted_ip', 100)->nullable();

            $table->index('special_saving_transaction_account_id', 'ss_txn_account_idx');
            $table->index('special_saving_transaction_member_id', 'ss_txn_member_idx');
            $table->index('special_saving_transaction_product_id', 'ss_txn_product_idx');
            $table->index('special_saving_transaction_type', 'ss_txn_type_idx');
            $table->index('special_saving_transaction_period', 'ss_txn_period_idx');
            $table->index('special_saving_transaction_doc_no', 'ss_txn_doc_idx');
            $table->index('special_saving_transaction_reference', 'ss_txn_ref_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | 6. Monthly Interest Runs
        |--------------------------------------------------------------------------
        */
        Schema::create('sacco_special_saving_interest_runs', function (Blueprint $table) {
            $table->increments('special_saving_interest_run_id');

            $table->unsignedInteger('special_saving_interest_run_product_id');

            $table->string('special_saving_interest_run_period', 6);
            $table->date('special_saving_interest_run_start_date');
            $table->date('special_saving_interest_run_end_date');

            $table->string('special_saving_interest_run_method', 40)->default('MINIMUM_MONTHLY_BALANCE');
            $table->decimal('special_saving_interest_run_annual_rate', 10, 6)->default(0);
            $table->decimal('special_saving_interest_run_monthly_rate', 10, 6)->default(0);

            $table->unsignedInteger('special_saving_interest_run_total_accounts')->default(0);
            $table->unsignedInteger('special_saving_interest_run_qualified_accounts')->default(0);
            $table->unsignedInteger('special_saving_interest_run_skipped_accounts')->default(0);

            $table->decimal('special_saving_interest_run_total_qualifying_balance', 15, 2)->default(0);
            $table->decimal('special_saving_interest_run_total_interest', 15, 2)->default(0);

            $table->string('special_saving_interest_run_status', 30)->default('Draft');

            $table->dateTime('special_saving_interest_run_posted_on')->nullable();
            $table->unsignedInteger('special_saving_interest_run_posted_by')->nullable();

            $table->text('special_saving_interest_run_notes')->nullable();

            $table->unsignedInteger('special_saving_interest_run_by')->nullable();
            $table->string('special_saving_interest_run_ip', 100)->nullable();
            $table->timestamp('special_saving_interest_run_transdate')->useCurrent();

            $table->char('special_saving_interest_run_deleted', 1)->default('N');
            $table->unsignedInteger('special_saving_interest_run_deleted_by')->nullable();
            $table->dateTime('special_saving_interest_run_deleted_on')->nullable();
            $table->string('special_saving_interest_run_deleted_ip', 100)->nullable();

            $table->unique([
                'special_saving_interest_run_product_id',
                'special_saving_interest_run_period'
            ], 'ss_interest_run_product_period_unique');

            $table->index('special_saving_interest_run_status', 'ss_interest_run_status_idx');
            $table->index('special_saving_interest_run_period', 'ss_interest_run_period_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | 7. Monthly Interest Run Items
        |--------------------------------------------------------------------------
        */
        Schema::create('sacco_special_saving_interest_run_items', function (Blueprint $table) {
            $table->increments('special_saving_interest_item_id');

            $table->unsignedInteger('special_saving_interest_item_run_id');
            $table->unsignedInteger('special_saving_interest_item_account_id');
            $table->unsignedInteger('special_saving_interest_item_member_id');
            $table->unsignedInteger('special_saving_interest_item_product_id');

            $table->string('special_saving_interest_item_period', 6);

            $table->decimal('special_saving_interest_item_opening_balance', 15, 2)->default(0);
            $table->decimal('special_saving_interest_item_closing_balance', 15, 2)->default(0);
            $table->decimal('special_saving_interest_item_minimum_balance', 15, 2)->default(0);
            $table->decimal('special_saving_interest_item_qualifying_balance', 15, 2)->default(0);

            $table->decimal('special_saving_interest_item_annual_rate', 10, 6)->default(0);
            $table->decimal('special_saving_interest_item_monthly_rate', 10, 6)->default(0);
            $table->decimal('special_saving_interest_item_interest_amount', 15, 2)->default(0);

            $table->unsignedInteger('special_saving_interest_item_days_as_member')->default(0);
            $table->unsignedInteger('special_saving_interest_item_days_in_product')->default(0);
            $table->unsignedInteger('special_saving_interest_item_minimum_deposit_days')->default(0);

            $table->char('special_saving_interest_item_qualified', 1)->default('N');
            $table->string('special_saving_interest_item_skip_reason', 255)->nullable();

            $table->unsignedInteger('special_saving_interest_item_transaction_id')->nullable();

            $table->string('special_saving_interest_item_status', 30)->default('Pending');

            $table->unsignedInteger('special_saving_interest_item_by')->nullable();
            $table->string('special_saving_interest_item_ip', 100)->nullable();
            $table->timestamp('special_saving_interest_item_transdate')->useCurrent();

            $table->index('special_saving_interest_item_run_id', 'ss_interest_item_run_idx');
            $table->index('special_saving_interest_item_account_id', 'ss_interest_item_account_idx');
            $table->index('special_saving_interest_item_member_id', 'ss_interest_item_member_idx');
            $table->index('special_saving_interest_item_period', 'ss_interest_item_period_idx');
            $table->index('special_saving_interest_item_qualified', 'ss_interest_item_qualified_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | 8. Withdrawal Requests
        |--------------------------------------------------------------------------
        */
        Schema::create('sacco_special_saving_withdrawal_requests', function (Blueprint $table) {
            $table->increments('special_saving_withdrawal_id');

            $table->unsignedInteger('special_saving_withdrawal_account_id');
            $table->unsignedInteger('special_saving_withdrawal_member_id');
            $table->unsignedInteger('special_saving_withdrawal_product_id');

            $table->date('special_saving_withdrawal_request_date');
            $table->decimal('special_saving_withdrawal_principal_amount', 15, 2)->default(0);
            $table->decimal('special_saving_withdrawal_interest_amount', 15, 2)->default(0);
            $table->decimal('special_saving_withdrawal_total_amount', 15, 2)->default(0);

            $table->char('special_saving_withdrawal_is_early', 1)->default('N');
            $table->decimal('special_saving_withdrawal_forfeited_interest', 15, 2)->default(0);

            $table->date('special_saving_withdrawal_last_withdrawal_date')->nullable();
            $table->date('special_saving_withdrawal_next_free_withdrawal_date')->nullable();

            $table->string('special_saving_withdrawal_payment_mode', 50)->nullable();
            $table->string('special_saving_withdrawal_payment_reference', 150)->nullable();
            $table->unsignedInteger('special_saving_withdrawal_payment_sub_account_id')->nullable();

            $table->string('special_saving_withdrawal_status', 30)->default('Pending');

            $table->unsignedInteger('special_saving_withdrawal_requested_by')->nullable();
            $table->unsignedInteger('special_saving_withdrawal_approved_by')->nullable();
            $table->dateTime('special_saving_withdrawal_approved_on')->nullable();

            $table->unsignedInteger('special_saving_withdrawal_paid_by')->nullable();
            $table->dateTime('special_saving_withdrawal_paid_on')->nullable();

            $table->unsignedInteger('special_saving_withdrawal_transaction_id')->nullable();
            $table->unsignedInteger('special_saving_withdrawal_forfeiture_transaction_id')->nullable();

            $table->text('special_saving_withdrawal_notes')->nullable();

            $table->unsignedInteger('special_saving_withdrawal_by')->nullable();
            $table->string('special_saving_withdrawal_ip', 100)->nullable();
            $table->timestamp('special_saving_withdrawal_transdate')->useCurrent();

            $table->char('special_saving_withdrawal_deleted', 1)->default('N');
            $table->unsignedInteger('special_saving_withdrawal_deleted_by')->nullable();
            $table->dateTime('special_saving_withdrawal_deleted_on')->nullable();
            $table->string('special_saving_withdrawal_deleted_ip', 100)->nullable();

            $table->index('special_saving_withdrawal_account_id', 'ss_withdrawal_account_idx');
            $table->index('special_saving_withdrawal_member_id', 'ss_withdrawal_member_idx');
            $table->index('special_saving_withdrawal_product_id', 'ss_withdrawal_product_idx');
            $table->index('special_saving_withdrawal_status', 'ss_withdrawal_status_idx');
            $table->index('special_saving_withdrawal_is_early', 'ss_withdrawal_early_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | 9. Processing Locks
        |--------------------------------------------------------------------------
        */
        Schema::create('sacco_special_saving_processing_locks', function (Blueprint $table) {
            $table->increments('special_saving_lock_id');

            $table->unsignedInteger('special_saving_lock_product_id');
            $table->string('special_saving_lock_period', 6);
            $table->string('special_saving_lock_process', 50);

            $table->string('special_saving_lock_status', 30)->default('Locked');

            $table->dateTime('special_saving_lock_started_on')->nullable();
            $table->dateTime('special_saving_lock_finished_on')->nullable();

            $table->unsignedInteger('special_saving_lock_by')->nullable();
            $table->string('special_saving_lock_ip', 100)->nullable();
            $table->timestamp('special_saving_lock_transdate')->useCurrent();

            $table->unique([
                'special_saving_lock_product_id',
                'special_saving_lock_period',
                'special_saving_lock_process'
            ], 'ss_processing_lock_unique');

            $table->index('special_saving_lock_status', 'ss_processing_lock_status_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | Default Seed: Category + FEDHA Product + Default Flat Tier
        |--------------------------------------------------------------------------
        */
        DB::table('sacco_special_saving_categories')->insert([
            'special_saving_category_id' => 1,
            'special_saving_category_name' => 'Monthly Interest Savings',
            'special_saving_category_code' => 'MONTHLY_INTEREST',
            'special_saving_category_description' => 'Special savings category for products that earn interest monthly but restrict withdrawals by cycle.',

            'special_saving_liability_sub_account_id' => 1,
            'special_saving_interest_expense_sub_account_id' => 1,
            'special_saving_interest_payable_sub_account_id' => 1,
            'special_saving_penalty_income_sub_account_id' => 1,
            'special_saving_charge_income_sub_account_id' => 1,
            'special_saving_default_cash_sub_account_id' => 1,

            'special_saving_category_status' => 'Active',
            'special_saving_category_by' => 1,
            'special_saving_category_ip' => 'MIGRATION',
            'special_saving_category_transdate' => now(),
            'special_saving_category_deleted' => 'N',
        ]);

        DB::table('sacco_special_saving_products')->insert([
            'special_saving_product_id' => 1,
            'special_saving_product_category_id' => 1,

            'special_saving_product_name' => 'FEDHA',
            'special_saving_product_code' => 'FEDHA',
            'special_saving_product_description' => 'FEDHA monthly interest savings product. Earns 12.5% per year, calculated monthly. Interest accrues monthly but becomes withdrawable after 6 months. Early withdrawal forfeits unvested interest.',

            'special_saving_product_rate_mode' => 'FLAT',
            'special_saving_product_annual_interest_rate' => 12.500000,
            'special_saving_product_monthly_interest_rate' => 1.041667,

            'special_saving_product_interest_method' => 'MINIMUM_MONTHLY_BALANCE',
            'special_saving_product_interest_posting_frequency' => 'MONTHLY',

            'special_saving_product_require_full_month' => 'Y',
            'special_saving_product_member_minimum_days' => 30,
            'special_saving_product_deposit_minimum_days' => 10,

            'special_saving_product_withdrawal_cycle_months' => 6,
            'special_saving_product_early_withdrawal_allowed' => 'Y',
            'special_saving_product_early_withdrawal_interest_policy' => 'FORFEIT_UNVESTED_INTEREST',
            'special_saving_product_interest_vesting_policy' => 'AFTER_WITHDRAWAL_CYCLE',
            'special_saving_product_interest_credit_policy' => 'KEEP_SEPARATE',

            'special_saving_product_minimum_opening_amount' => 0,
            'special_saving_product_minimum_monthly_contribution' => 0,
            'special_saving_product_minimum_balance' => 0,

            'special_saving_product_can_secure_loan' => 'N',
            'special_saving_product_allow_member_transfer' => 'Y',
            'special_saving_product_allow_mpesa_collection' => 'Y',

            'special_saving_product_status' => 'Active',
            'special_saving_product_by' => 1,
            'special_saving_product_ip' => 'MIGRATION',
            'special_saving_product_transdate' => now(),
            'special_saving_product_deleted' => 'N',
        ]);

        DB::table('sacco_special_saving_product_rate_tiers')->insert([
            'special_saving_rate_tier_id' => 1,
            'special_saving_rate_tier_product_id' => 1,
            'special_saving_rate_tier_min_amount' => 0,
            'special_saving_rate_tier_max_amount' => null,
            'special_saving_rate_tier_annual_rate' => 12.500000,
            'special_saving_rate_tier_monthly_rate' => 1.041667,
            'special_saving_rate_tier_status' => 'Active',
            'special_saving_rate_tier_by' => 1,
            'special_saving_rate_tier_ip' => 'MIGRATION',
            'special_saving_rate_tier_transdate' => now(),
            'special_saving_rate_tier_deleted' => 'N',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_special_saving_processing_locks');
        Schema::dropIfExists('sacco_special_saving_withdrawal_requests');
        Schema::dropIfExists('sacco_special_saving_interest_run_items');
        Schema::dropIfExists('sacco_special_saving_interest_runs');
        Schema::dropIfExists('sacco_special_saving_transactions');
        Schema::dropIfExists('sacco_special_saving_accounts');
        Schema::dropIfExists('sacco_special_saving_product_rate_tiers');
        Schema::dropIfExists('sacco_special_saving_products');
        Schema::dropIfExists('sacco_special_saving_categories');
    }
};