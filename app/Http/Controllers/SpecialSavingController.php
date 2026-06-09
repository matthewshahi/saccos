<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpecialSavingController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Dashboard / Overview
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        return $this->dashboard($request);
    }

    public function dashboard(Request $request)
    {
        $summary = [
            'products' => DB::table('sacco_special_saving_products')
                ->where('special_saving_product_deleted', 'N')
                ->count(),

            'active_accounts' => DB::table('sacco_special_saving_accounts')
                ->where('special_saving_account_deleted', 'N')
                ->where('special_saving_account_status', 'Active')
                ->count(),

            'principal_balance' => DB::table('sacco_special_saving_accounts')
                ->where('special_saving_account_deleted', 'N')
                ->sum('special_saving_account_principal_balance'),

            'accrued_interest' => DB::table('sacco_special_saving_accounts')
                ->where('special_saving_account_deleted', 'N')
                ->sum('special_saving_account_accrued_interest_balance'),

            'available_interest' => DB::table('sacco_special_saving_accounts')
                ->where('special_saving_account_deleted', 'N')
                ->sum('special_saving_account_available_interest_balance'),

            'total_balance' => DB::table('sacco_special_saving_accounts')
                ->where('special_saving_account_deleted', 'N')
                ->sum('special_saving_account_total_balance'),
        ];

        $latest_transactions = DB::table('sacco_special_saving_transactions as t')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 't.special_saving_transaction_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 't.special_saving_transaction_product_id')
            ->where('t.special_saving_transaction_deleted', 'N')
            ->orderByDesc('t.special_saving_transaction_id')
            ->select('t.*', 'm.member_name', 'm.member_sacco_id', 'p.special_saving_product_name')
            ->limit(10)
            ->get();

        return view('special_savings.dashboard', compact('summary', 'latest_transactions'));
    }

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    public function categories(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $records = DB::table('sacco_special_saving_categories')
            ->where('special_saving_category_deleted', 'N')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('special_saving_category_name', 'like', "%{$q}%")
                        ->orWhere('special_saving_category_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('special_saving_category_name')
            ->paginate(25);

        return view('special_savings.categories.index', compact('records', 'q'));
    }

    public function createCategory()
    {
        $sub_accounts = $this->subAccountsList();

        return view('special_savings.categories.create', compact('sub_accounts'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'special_saving_category_name' => 'required|string|max:150',
            'special_saving_category_code' => 'required|string|max:50',
            'special_saving_category_description' => 'nullable|string',

            'special_saving_liability_sub_account_id' => 'required|integer',
            'special_saving_interest_expense_sub_account_id' => 'required|integer',
            'special_saving_interest_payable_sub_account_id' => 'required|integer',
            'special_saving_penalty_income_sub_account_id' => 'required|integer',
            'special_saving_charge_income_sub_account_id' => 'required|integer',
            'special_saving_default_cash_sub_account_id' => 'required|integer',

            'special_saving_category_status' => 'nullable|string|max:20',
        ]);

        $exists = DB::table('sacco_special_saving_categories')
            ->where('special_saving_category_code', $data['special_saving_category_code'])
            ->where('special_saving_category_deleted', 'N')
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'Category code already exists.');
        }

        $data['special_saving_category_status'] = $data['special_saving_category_status'] ?? 'Active';
        $data['special_saving_category_by'] = $this->userId();
        $data['special_saving_category_ip'] = $request->ip();
        $data['special_saving_category_transdate'] = now();
        $data['special_saving_category_deleted'] = 'N';

        DB::table('sacco_special_saving_categories')->insert($data);

        return redirect()->route('special_savings.categories.index')
            ->with('success', 'Special saving category created successfully.');
    }

    public function editCategory($id)
    {
        $record = DB::table('sacco_special_saving_categories')
            ->where('special_saving_category_id', $id)
            ->where('special_saving_category_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        $sub_accounts = $this->subAccountsList();

        return view('special_savings.categories.edit', compact('record', 'sub_accounts'));
    }

    public function updateCategory(Request $request, $id)
    {
        $record = DB::table('sacco_special_saving_categories')
            ->where('special_saving_category_id', $id)
            ->where('special_saving_category_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        $data = $request->validate([
            'special_saving_category_name' => 'required|string|max:150',
            'special_saving_category_code' => 'required|string|max:50',
            'special_saving_category_description' => 'nullable|string',

            'special_saving_liability_sub_account_id' => 'required|integer',
            'special_saving_interest_expense_sub_account_id' => 'required|integer',
            'special_saving_interest_payable_sub_account_id' => 'required|integer',
            'special_saving_penalty_income_sub_account_id' => 'required|integer',
            'special_saving_charge_income_sub_account_id' => 'required|integer',
            'special_saving_default_cash_sub_account_id' => 'required|integer',

            'special_saving_category_status' => 'nullable|string|max:20',
        ]);

        $exists = DB::table('sacco_special_saving_categories')
            ->where('special_saving_category_code', $data['special_saving_category_code'])
            ->where('special_saving_category_id', '!=', $id)
            ->where('special_saving_category_deleted', 'N')
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'Category code already exists.');
        }

        $data['special_saving_category_status'] = $data['special_saving_category_status'] ?? 'Active';
        $data['special_saving_category_by'] = $this->userId();
        $data['special_saving_category_ip'] = $request->ip();
        $data['special_saving_category_transdate'] = now();

        DB::table('sacco_special_saving_categories')
            ->where('special_saving_category_id', $id)
            ->update($data);

        return redirect()->route('special_savings.categories.index')
            ->with('success', 'Special saving category updated successfully.');
    }

    public function toggleCategory(Request $request, $id)
    {
        $record = DB::table('sacco_special_saving_categories')
            ->where('special_saving_category_id', $id)
            ->where('special_saving_category_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        $newStatus = $record->special_saving_category_status === 'Active' ? 'Inactive' : 'Active';

        DB::table('sacco_special_saving_categories')
            ->where('special_saving_category_id', $id)
            ->update([
                'special_saving_category_status' => $newStatus,
                'special_saving_category_by' => $this->userId(),
                'special_saving_category_ip' => $request->ip(),
                'special_saving_category_transdate' => now(),
            ]);

        return back()->with('success', "Category status changed to {$newStatus}.");
    }

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    public function products(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $records = DB::table('sacco_special_saving_products as p')
            ->leftJoin('sacco_special_saving_categories as c', 'c.special_saving_category_id', '=', 'p.special_saving_product_category_id')
            ->where('p.special_saving_product_deleted', 'N')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('p.special_saving_product_name', 'like', "%{$q}%")
                        ->orWhere('p.special_saving_product_code', 'like', "%{$q}%")
                        ->orWhere('c.special_saving_category_name', 'like', "%{$q}%");
                });
            })
            ->select('p.*', 'c.special_saving_category_name')
            ->orderBy('p.special_saving_product_name')
            ->paginate(25);

        return view('special_savings.products.index', compact('records', 'q'));
    }

    public function createProduct()
    {
        $categories = DB::table('sacco_special_saving_categories')
            ->where('special_saving_category_deleted', 'N')
            ->where('special_saving_category_status', 'Active')
            ->orderBy('special_saving_category_name')
            ->get();

        return view('special_savings.products.create', compact('categories'));
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'special_saving_product_category_id' => 'required|integer',
            'special_saving_product_name' => 'required|string|max:150',
            'special_saving_product_code' => 'required|string|max:50',
            'special_saving_product_description' => 'nullable|string',

            'special_saving_product_rate_mode' => 'required|string|max:20',
            'special_saving_product_annual_interest_rate' => 'required|numeric|min:0',
            'special_saving_product_interest_method' => 'required|string|max:40',
            'special_saving_product_interest_posting_frequency' => 'required|string|max:20',

            'special_saving_product_require_full_month' => 'nullable|string|max:1',
            'special_saving_product_member_minimum_days' => 'nullable|integer|min:0',
            'special_saving_product_deposit_minimum_days' => 'nullable|integer|min:0',

            'special_saving_product_withdrawal_cycle_months' => 'nullable|integer|min:0',
            'special_saving_product_early_withdrawal_allowed' => 'nullable|string|max:1',
            'special_saving_product_early_withdrawal_interest_policy' => 'nullable|string|max:60',
            'special_saving_product_interest_vesting_policy' => 'nullable|string|max:60',
            'special_saving_product_interest_credit_policy' => 'nullable|string|max:40',

            'special_saving_product_minimum_opening_amount' => 'nullable|numeric|min:0',
            'special_saving_product_minimum_monthly_contribution' => 'nullable|numeric|min:0',
            'special_saving_product_minimum_balance' => 'nullable|numeric|min:0',

            'special_saving_product_can_secure_loan' => 'nullable|string|max:1',
            'special_saving_product_allow_member_transfer' => 'nullable|string|max:1',
            'special_saving_product_allow_mpesa_collection' => 'nullable|string|max:1',
            'special_saving_product_status' => 'nullable|string|max:20',
        ]);

        $exists = DB::table('sacco_special_saving_products')
            ->where('special_saving_product_code', $data['special_saving_product_code'])
            ->where('special_saving_product_deleted', 'N')
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'Product code already exists.');
        }

        $annual = $this->money($data['special_saving_product_annual_interest_rate']);

        $data['special_saving_product_monthly_interest_rate'] = round($annual / 12, 6);
        $data['special_saving_product_require_full_month'] = $data['special_saving_product_require_full_month'] ?? 'Y';
        $data['special_saving_product_member_minimum_days'] = $data['special_saving_product_member_minimum_days'] ?? 30;
        $data['special_saving_product_deposit_minimum_days'] = $data['special_saving_product_deposit_minimum_days'] ?? 10;
        $data['special_saving_product_withdrawal_cycle_months'] = $data['special_saving_product_withdrawal_cycle_months'] ?? 6;
        $data['special_saving_product_early_withdrawal_allowed'] = $data['special_saving_product_early_withdrawal_allowed'] ?? 'Y';
        $data['special_saving_product_early_withdrawal_interest_policy'] = $data['special_saving_product_early_withdrawal_interest_policy'] ?? 'FORFEIT_UNVESTED_INTEREST';
        $data['special_saving_product_interest_vesting_policy'] = $data['special_saving_product_interest_vesting_policy'] ?? 'AFTER_WITHDRAWAL_CYCLE';
        $data['special_saving_product_interest_credit_policy'] = $data['special_saving_product_interest_credit_policy'] ?? 'KEEP_SEPARATE';
        $data['special_saving_product_minimum_opening_amount'] = $this->money($data['special_saving_product_minimum_opening_amount'] ?? 0);
        $data['special_saving_product_minimum_monthly_contribution'] = $this->money($data['special_saving_product_minimum_monthly_contribution'] ?? 0);
        $data['special_saving_product_minimum_balance'] = $this->money($data['special_saving_product_minimum_balance'] ?? 0);
        $data['special_saving_product_can_secure_loan'] = $data['special_saving_product_can_secure_loan'] ?? 'N';
        $data['special_saving_product_allow_member_transfer'] = $data['special_saving_product_allow_member_transfer'] ?? 'Y';
        $data['special_saving_product_allow_mpesa_collection'] = $data['special_saving_product_allow_mpesa_collection'] ?? 'Y';
        $data['special_saving_product_status'] = $data['special_saving_product_status'] ?? 'Active';

        $data['special_saving_product_by'] = $this->userId();
        $data['special_saving_product_ip'] = $request->ip();
        $data['special_saving_product_transdate'] = now();
        $data['special_saving_product_deleted'] = 'N';

        DB::table('sacco_special_saving_products')->insert($data);

        return redirect()->route('special_savings.products.index')
            ->with('success', 'Special saving product created successfully.');
    }

    public function editProduct($id)
    {
        $record = DB::table('sacco_special_saving_products')
            ->where('special_saving_product_id', $id)
            ->where('special_saving_product_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        $categories = DB::table('sacco_special_saving_categories')
            ->where('special_saving_category_deleted', 'N')
            ->orderBy('special_saving_category_name')
            ->get();

        return view('special_savings.products.edit', compact('record', 'categories'));
    }

    public function updateProduct(Request $request, $id)
    {
        $record = DB::table('sacco_special_saving_products')
            ->where('special_saving_product_id', $id)
            ->where('special_saving_product_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        $data = $request->validate([
            'special_saving_product_category_id' => 'required|integer',
            'special_saving_product_name' => 'required|string|max:150',
            'special_saving_product_code' => 'required|string|max:50',
            'special_saving_product_description' => 'nullable|string',

            'special_saving_product_rate_mode' => 'required|string|max:20',
            'special_saving_product_annual_interest_rate' => 'required|numeric|min:0',
            'special_saving_product_interest_method' => 'required|string|max:40',
            'special_saving_product_interest_posting_frequency' => 'required|string|max:20',

            'special_saving_product_require_full_month' => 'nullable|string|max:1',
            'special_saving_product_member_minimum_days' => 'nullable|integer|min:0',
            'special_saving_product_deposit_minimum_days' => 'nullable|integer|min:0',

            'special_saving_product_withdrawal_cycle_months' => 'nullable|integer|min:0',
            'special_saving_product_early_withdrawal_allowed' => 'nullable|string|max:1',
            'special_saving_product_early_withdrawal_interest_policy' => 'nullable|string|max:60',
            'special_saving_product_interest_vesting_policy' => 'nullable|string|max:60',
            'special_saving_product_interest_credit_policy' => 'nullable|string|max:40',

            'special_saving_product_minimum_opening_amount' => 'nullable|numeric|min:0',
            'special_saving_product_minimum_monthly_contribution' => 'nullable|numeric|min:0',
            'special_saving_product_minimum_balance' => 'nullable|numeric|min:0',

            'special_saving_product_can_secure_loan' => 'nullable|string|max:1',
            'special_saving_product_allow_member_transfer' => 'nullable|string|max:1',
            'special_saving_product_allow_mpesa_collection' => 'nullable|string|max:1',
            'special_saving_product_status' => 'nullable|string|max:20',
        ]);

        $exists = DB::table('sacco_special_saving_products')
            ->where('special_saving_product_code', $data['special_saving_product_code'])
            ->where('special_saving_product_id', '!=', $id)
            ->where('special_saving_product_deleted', 'N')
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'Product code already exists.');
        }

        $annual = $this->money($data['special_saving_product_annual_interest_rate']);

        $data['special_saving_product_monthly_interest_rate'] = round($annual / 12, 6);
        $data['special_saving_product_require_full_month'] = $data['special_saving_product_require_full_month'] ?? 'Y';
        $data['special_saving_product_member_minimum_days'] = $data['special_saving_product_member_minimum_days'] ?? 30;
        $data['special_saving_product_deposit_minimum_days'] = $data['special_saving_product_deposit_minimum_days'] ?? 10;
        $data['special_saving_product_withdrawal_cycle_months'] = $data['special_saving_product_withdrawal_cycle_months'] ?? 6;
        $data['special_saving_product_early_withdrawal_allowed'] = $data['special_saving_product_early_withdrawal_allowed'] ?? 'Y';
        $data['special_saving_product_early_withdrawal_interest_policy'] = $data['special_saving_product_early_withdrawal_interest_policy'] ?? 'FORFEIT_UNVESTED_INTEREST';
        $data['special_saving_product_interest_vesting_policy'] = $data['special_saving_product_interest_vesting_policy'] ?? 'AFTER_WITHDRAWAL_CYCLE';
        $data['special_saving_product_interest_credit_policy'] = $data['special_saving_product_interest_credit_policy'] ?? 'KEEP_SEPARATE';
        $data['special_saving_product_minimum_opening_amount'] = $this->money($data['special_saving_product_minimum_opening_amount'] ?? 0);
        $data['special_saving_product_minimum_monthly_contribution'] = $this->money($data['special_saving_product_minimum_monthly_contribution'] ?? 0);
        $data['special_saving_product_minimum_balance'] = $this->money($data['special_saving_product_minimum_balance'] ?? 0);
        $data['special_saving_product_can_secure_loan'] = $data['special_saving_product_can_secure_loan'] ?? 'N';
        $data['special_saving_product_allow_member_transfer'] = $data['special_saving_product_allow_member_transfer'] ?? 'Y';
        $data['special_saving_product_allow_mpesa_collection'] = $data['special_saving_product_allow_mpesa_collection'] ?? 'Y';
        $data['special_saving_product_status'] = $data['special_saving_product_status'] ?? 'Active';

        $data['special_saving_product_by'] = $this->userId();
        $data['special_saving_product_ip'] = $request->ip();
        $data['special_saving_product_transdate'] = now();

        DB::table('sacco_special_saving_products')
            ->where('special_saving_product_id', $id)
            ->update($data);

        return redirect()->route('special_savings.products.index')
            ->with('success', 'Special saving product updated successfully.');
    }

    public function toggleProduct(Request $request, $id)
    {
        $record = $this->getProduct($id);

        $newStatus = $record->special_saving_product_status === 'Active' ? 'Inactive' : 'Active';

        DB::table('sacco_special_saving_products')
            ->where('special_saving_product_id', $id)
            ->update([
                'special_saving_product_status' => $newStatus,
                'special_saving_product_by' => $this->userId(),
                'special_saving_product_ip' => $request->ip(),
                'special_saving_product_transdate' => now(),
            ]);

        return back()->with('success', "Product status changed to {$newStatus}.");
    }

    public function showProduct($id)
    {
        $record = DB::table('sacco_special_saving_products as p')
            ->leftJoin('sacco_special_saving_categories as c', 'c.special_saving_category_id', '=', 'p.special_saving_product_category_id')
            ->where('p.special_saving_product_id', $id)
            ->where('p.special_saving_product_deleted', 'N')
            ->select('p.*', 'c.special_saving_category_name')
            ->first();

        abort_if(!$record, 404);

        $rate_tiers = DB::table('sacco_special_saving_product_rate_tiers')
            ->where('special_saving_rate_tier_product_id', $id)
            ->where('special_saving_rate_tier_deleted', 'N')
            ->orderBy('special_saving_rate_tier_min_amount')
            ->get();

        return view('special_savings.products.show', compact('record', 'rate_tiers'));
    }

    /*
    |--------------------------------------------------------------------------
    | Product Rate Tiers
    |--------------------------------------------------------------------------
    */

    public function rateTiers($product_id)
    {
        $product = $this->getProduct($product_id);

        $records = DB::table('sacco_special_saving_product_rate_tiers')
            ->where('special_saving_rate_tier_product_id', $product_id)
            ->where('special_saving_rate_tier_deleted', 'N')
            ->orderBy('special_saving_rate_tier_min_amount')
            ->paginate(25);

        return view('special_savings.rate_tiers.index', compact('product', 'records'));
    }

    public function createRateTier($product_id)
    {
        $product = $this->getProduct($product_id);

        return view('special_savings.rate_tiers.create', compact('product'));
    }

    public function storeRateTier(Request $request, $product_id)
    {
        $product = $this->getProduct($product_id);

        $data = $request->validate([
            'special_saving_rate_tier_min_amount' => 'required|numeric|min:0',
            'special_saving_rate_tier_max_amount' => 'nullable|numeric|min:0',
            'special_saving_rate_tier_annual_rate' => 'required|numeric|min:0',
            'special_saving_rate_tier_status' => 'nullable|string|max:20',
        ]);

        $annual = $this->money($data['special_saving_rate_tier_annual_rate']);

        $data['special_saving_rate_tier_product_id'] = $product->special_saving_product_id;
        $data['special_saving_rate_tier_min_amount'] = $this->money($data['special_saving_rate_tier_min_amount']);
        $data['special_saving_rate_tier_max_amount'] = isset($data['special_saving_rate_tier_max_amount'])
            ? $this->money($data['special_saving_rate_tier_max_amount'])
            : null;
        $data['special_saving_rate_tier_annual_rate'] = $annual;
        $data['special_saving_rate_tier_monthly_rate'] = round($annual / 12, 6);
        $data['special_saving_rate_tier_status'] = $data['special_saving_rate_tier_status'] ?? 'Active';
        $data['special_saving_rate_tier_by'] = $this->userId();
        $data['special_saving_rate_tier_ip'] = $request->ip();
        $data['special_saving_rate_tier_transdate'] = now();
        $data['special_saving_rate_tier_deleted'] = 'N';

        DB::table('sacco_special_saving_product_rate_tiers')->insert($data);

        return redirect()->route('special_savings.rate_tiers.index', $product_id)
            ->with('success', 'Rate tier created successfully.');
    }

    public function editRateTier($id)
    {
        $record = DB::table('sacco_special_saving_product_rate_tiers')
            ->where('special_saving_rate_tier_id', $id)
            ->where('special_saving_rate_tier_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        $product = $this->getProduct($record->special_saving_rate_tier_product_id);

        return view('special_savings.rate_tiers.edit', compact('record', 'product'));
    }

    public function updateRateTier(Request $request, $id)
    {
        $record = DB::table('sacco_special_saving_product_rate_tiers')
            ->where('special_saving_rate_tier_id', $id)
            ->where('special_saving_rate_tier_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        $data = $request->validate([
            'special_saving_rate_tier_min_amount' => 'required|numeric|min:0',
            'special_saving_rate_tier_max_amount' => 'nullable|numeric|min:0',
            'special_saving_rate_tier_annual_rate' => 'required|numeric|min:0',
            'special_saving_rate_tier_status' => 'nullable|string|max:20',
        ]);

        $annual = $this->money($data['special_saving_rate_tier_annual_rate']);

        $data['special_saving_rate_tier_min_amount'] = $this->money($data['special_saving_rate_tier_min_amount']);
        $data['special_saving_rate_tier_max_amount'] = isset($data['special_saving_rate_tier_max_amount'])
            ? $this->money($data['special_saving_rate_tier_max_amount'])
            : null;
        $data['special_saving_rate_tier_annual_rate'] = $annual;
        $data['special_saving_rate_tier_monthly_rate'] = round($annual / 12, 6);
        $data['special_saving_rate_tier_status'] = $data['special_saving_rate_tier_status'] ?? 'Active';
        $data['special_saving_rate_tier_by'] = $this->userId();
        $data['special_saving_rate_tier_ip'] = $request->ip();
        $data['special_saving_rate_tier_transdate'] = now();

        DB::table('sacco_special_saving_product_rate_tiers')
            ->where('special_saving_rate_tier_id', $id)
            ->update($data);

        return redirect()->route('special_savings.rate_tiers.index', $record->special_saving_rate_tier_product_id)
            ->with('success', 'Rate tier updated successfully.');
    }

    public function deleteRateTier(Request $request, $id)
    {
        $record = DB::table('sacco_special_saving_product_rate_tiers')
            ->where('special_saving_rate_tier_id', $id)
            ->where('special_saving_rate_tier_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        DB::table('sacco_special_saving_product_rate_tiers')
            ->where('special_saving_rate_tier_id', $id)
            ->update([
                'special_saving_rate_tier_deleted' => 'Y',
                'special_saving_rate_tier_deleted_by' => $this->userId(),
                'special_saving_rate_tier_deleted_on' => now(),
                'special_saving_rate_tier_deleted_ip' => $request->ip(),
            ]);

        return redirect()->route('special_savings.rate_tiers.index', $record->special_saving_rate_tier_product_id)
            ->with('success', 'Rate tier deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Accounts
    |--------------------------------------------------------------------------
    */

    public function accounts(Request $request)
{
    $q = trim((string) $request->get('q'));
    $product_id = $request->get('product_id');
    $status = $request->get('status');

    $products = $this->activeProducts();

    $qDigits = preg_replace('/\D+/', '', $q);

    $phoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(m.member_phone_no, ''), ' ', ''), '-', ''), '+', ''), '(', ''), ')', '')";

    $records = DB::table('sacco_special_saving_accounts as a')
        ->leftJoin('sacco_members as m', 'm.member_id', '=', 'a.special_saving_account_member_id')
        ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'a.special_saving_account_product_id')
        ->where('a.special_saving_account_deleted', 'N')
        ->when($q !== '', function ($query) use ($q, $qDigits, $phoneSql) {
            $query->where(function ($qq) use ($q, $qDigits, $phoneSql) {
                $qq->where('a.special_saving_account_number', 'like', "%{$q}%")
                    ->orWhere('m.member_name', 'like', "%{$q}%")
                    ->orWhere('m.member_sacco_id', 'like', "%{$q}%")
                    ->orWhere('m.member_national_id', 'like', "%{$q}%")
                    ->orWhere('m.member_phone_no', 'like', "%{$q}%");

                if ($qDigits !== '') {
                    $qq->orWhereRaw("{$phoneSql} LIKE ?", ["%{$qDigits}%"]);
                }
            });
        })
        ->when($product_id, function ($query) use ($product_id) {
            $query->where('a.special_saving_account_product_id', $product_id);
        })
        ->when($status, function ($query) use ($status) {
            $query->where('a.special_saving_account_status', $status);
        })
        ->select(
            'a.*',
            'm.member_name',
            'm.member_sacco_id',
            'm.member_national_id',
            'm.member_phone_no',
            'p.special_saving_product_name'
        )
        ->orderByDesc('a.special_saving_account_id')
        ->paginate(25);

    return view('special_savings.accounts.index', compact(
        'records',
        'q',
        'products',
        'product_id',
        'status'
    ));
}

public function createDeposit()
{
    $products = $this->activeProducts();
    $sub_accounts = $this->subAccountsList();

    return view('special_savings.deposits.create', compact('products', 'sub_accounts'));
}

    public function createAccount(Request $request)
{
    $products = $this->activeProducts();

    $selected_member = null;

    if ($request->filled('member_id')) {
        $selected_member = DB::table('sacco_members')
            ->where('member_id', $request->get('member_id'))
            ->where('member_active', 'Y')
            ->where('member_deleted', 'N')
            ->first();

        if (!$selected_member) {
            return redirect()
                ->route('special_savings.accounts.index')
                ->with('error', 'Selected member was not found or is inactive.');
        }
    }

    return view('special_savings.accounts.create', compact('products', 'selected_member'));
}

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'special_saving_account_member_id' => 'required|integer',
            'special_saving_account_product_id' => 'required|integer',
            'special_saving_account_opening_date' => 'required|date',
            'special_saving_account_notes' => 'nullable|string',
        ]);

        $member = DB::table('sacco_members')
    ->where('member_id', $data['special_saving_account_member_id'])
    ->where('member_active', 'Y')
    ->where('member_deleted', 'N')
    ->first();

if (!$member) {
    return back()
        ->withInput()
        ->with('error', 'Selected member was not found or is inactive.');
}


        $product = $this->getProduct($data['special_saving_account_product_id']);

        $existing = DB::table('sacco_special_saving_accounts')
            ->where('special_saving_account_member_id', $data['special_saving_account_member_id'])
            ->where('special_saving_account_product_id', $data['special_saving_account_product_id'])
            ->where('special_saving_account_deleted', 'N')
            ->whereIn('special_saving_account_status', ['Active', 'Frozen', 'Dormant'])
            ->first();

        if ($existing) {
            return back()->withInput()->with('error', 'This member already has an active account for this product.');
        }

        $openingDate = Carbon::parse($data['special_saving_account_opening_date']);
        $nextFreeWithdrawalDate = $openingDate->copy()->addMonths((int) $product->special_saving_product_withdrawal_cycle_months)->toDateString();

        $accountId = DB::table('sacco_special_saving_accounts')->insertGetId([
            'special_saving_account_member_id' => $data['special_saving_account_member_id'],
            'special_saving_account_product_id' => $data['special_saving_account_product_id'],
            'special_saving_account_number' => null,
            'special_saving_account_opening_date' => $openingDate->toDateString(),
            'special_saving_account_last_interest_date' => null,
            'special_saving_account_last_withdrawal_date' => null,
            'special_saving_account_next_free_withdrawal_date' => $nextFreeWithdrawalDate,

            'special_saving_account_principal_balance' => 0,
            'special_saving_account_accrued_interest_balance' => 0,
            'special_saving_account_available_interest_balance' => 0,
            'special_saving_account_forfeited_interest_balance' => 0,
            'special_saving_account_total_balance' => 0,

            'special_saving_account_status' => 'Active',
            'special_saving_account_notes' => $data['special_saving_account_notes'] ?? null,

            'special_saving_account_by' => $this->userId(),
            'special_saving_account_ip' => $request->ip(),
            'special_saving_account_transdate' => now(),
            'special_saving_account_deleted' => 'N',
        ]);

        DB::table('sacco_special_saving_accounts')
            ->where('special_saving_account_id', $accountId)
            ->update([
                'special_saving_account_number' => $this->generateAccountNumber($accountId, $product->special_saving_product_code),
            ]);

        return redirect()->route('special_savings.accounts.show', $accountId)
            ->with('success', 'Special saving account opened successfully.');
    }

    public function showAccount($id)
    {
        $record = $this->accountWithDetails($id);

        $transactions = DB::table('sacco_special_saving_transactions')
            ->where('special_saving_transaction_account_id', $id)
            ->where('special_saving_transaction_deleted', 'N')
            ->orderByDesc('special_saving_transaction_date')
            ->orderByDesc('special_saving_transaction_id')
            ->limit(50)
            ->get();

        return view('special_savings.accounts.show', compact('record', 'transactions'));
    }

    public function editAccount($id)
    {
        $record = $this->accountWithDetails($id);
        $products = $this->activeProducts();

        return view('special_savings.accounts.edit', compact('record', 'products'));
    }

    public function updateAccount(Request $request, $id)
    {
        $record = $this->getAccount($id);

        $data = $request->validate([
            'special_saving_account_status' => 'required|string|max:30',
            'special_saving_account_notes' => 'nullable|string',
        ]);

        DB::table('sacco_special_saving_accounts')
            ->where('special_saving_account_id', $record->special_saving_account_id)
            ->update([
                'special_saving_account_status' => $data['special_saving_account_status'],
                'special_saving_account_notes' => $data['special_saving_account_notes'] ?? null,
                'special_saving_account_by' => $this->userId(),
                'special_saving_account_ip' => $request->ip(),
                'special_saving_account_transdate' => now(),
            ]);

        return redirect()->route('special_savings.accounts.show', $id)
            ->with('success', 'Account updated successfully.');
    }

    public function freezeAccount(Request $request, $id)
    {
        return $this->changeAccountStatus($request, $id, 'Frozen');
    }

    public function activateAccount(Request $request, $id)
    {
        return $this->changeAccountStatus($request, $id, 'Active');
    }

    public function closeAccount(Request $request, $id)
    {
        $account = $this->getAccount($id);

        if ((float) $account->special_saving_account_total_balance > 0) {
            return back()->with('error', 'This account still has a balance and cannot be closed.');
        }

        return $this->changeAccountStatus($request, $id, 'Closed');
    }

    public function accountStatement(Request $request, $id)
    {
        $record = $this->accountWithDetails($id);

        $from = $request->get('from');
        $to = $request->get('to');

        $transactions = DB::table('sacco_special_saving_transactions')
            ->where('special_saving_transaction_account_id', $id)
            ->where('special_saving_transaction_deleted', 'N')
            ->when($from, fn($q) => $q->whereDate('special_saving_transaction_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('special_saving_transaction_date', '<=', $to))
            ->orderBy('special_saving_transaction_date')
            ->orderBy('special_saving_transaction_id')
            ->get();

        return view('special_savings.accounts.statement', compact('record', 'transactions', 'from', 'to'));
    }

    public function accountStatementPdf(Request $request, $id)
    {
        $record = $this->accountWithDetails($id);

        $from = $request->get('from');
        $to = $request->get('to');

        $transactions = DB::table('sacco_special_saving_transactions')
            ->where('special_saving_transaction_account_id', $id)
            ->where('special_saving_transaction_deleted', 'N')
            ->when($from, fn($q) => $q->whereDate('special_saving_transaction_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('special_saving_transaction_date', '<=', $to))
            ->orderBy('special_saving_transaction_date')
            ->orderBy('special_saving_transaction_id')
            ->get();

        return view('special_savings.accounts.statement_pdf', compact('record', 'transactions', 'from', 'to'));
    }

    /*
    |--------------------------------------------------------------------------
    | Search APIs
    |--------------------------------------------------------------------------
    */

    public function searchMembers(Request $request)
{
    $q = trim((string) $request->get('q'));

    if (strlen($q) < 2) {
        return response()->json([
            'status' => 'empty',
            'ambiguous' => false,
            'message' => 'Enter at least 2 characters.',
            'records' => [],
        ]);
    }

    $qDigits = preg_replace('/\D+/', '', $q);

    $phoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(member_phone_no, ''), ' ', ''), '-', ''), '+', ''), '(', ''), ')', '')";

    $orderSql = "
        CASE
            WHEN member_sacco_id = ? THEN 1
            WHEN member_national_id = ? THEN 2
    ";

    $bindings = [
        $q,
        $q,
    ];

    if ($qDigits !== '') {
        $orderSql .= " WHEN {$phoneSql} = ? THEN 3 ";
        $bindings[] = $qDigits;
    }

    $orderSql .= "
            WHEN member_name = ? THEN 4
            WHEN member_sacco_id LIKE ? THEN 5
            WHEN member_national_id LIKE ? THEN 6
    ";

    $bindings[] = $q;
    $bindings[] = "{$q}%";
    $bindings[] = "{$q}%";

    if ($qDigits !== '') {
        $orderSql .= " WHEN {$phoneSql} LIKE ? THEN 7 ";
        $bindings[] = "{$qDigits}%";
    }

    $orderSql .= "
            ELSE 8
        END
    ";

    $records = DB::table('sacco_members')
        ->where('member_deleted', 'N')
        ->where('member_active', 'Y')
        ->where(function ($query) use ($q, $qDigits, $phoneSql) {
            $query->where('member_name', 'like', "%{$q}%")
                ->orWhere('member_sacco_id', 'like', "%{$q}%")
                ->orWhere('member_national_id', 'like', "%{$q}%")
                ->orWhere('member_phone_no', 'like', "%{$q}%");

            if ($qDigits !== '') {
                $query->orWhereRaw("{$phoneSql} LIKE ?", ["%{$qDigits}%"]);
            }
        })
        ->orderByRaw($orderSql, $bindings)
        ->orderBy('member_name')
        ->limit(20)
        ->get([
            'member_id',
            'member_name',
            'member_sacco_id',
            'member_national_id',
            'member_phone_no',
        ]);

    $count = $records->count();

    if ($count === 0) {
        return response()->json([
            'status' => 'empty',
            'ambiguous' => false,
            'message' => 'No active member found.',
            'records' => [],
        ]);
    }

    if ($count > 1) {
        return response()->json([
            'status' => 'ambiguous',
            'ambiguous' => true,
            'message' => 'More than one matching member was found. Search more specifically using SACCO number, phone number, or national ID.',
            'records' => $records,
        ]);
    }

    return response()->json([
        'status' => 'ok',
        'ambiguous' => false,
        'message' => 'One member found.',
        'records' => $records,
    ]);
}

    

    public function searchSubAccounts(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $records = DB::table('sacco_sub_account')
            ->where('sub_account_deleted', 'N')
            ->when(strlen($q) >= 2, function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('sub_account_name', 'like', "%{$q}%")
                        ->orWhere('sub_account_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('sub_account_name')
            ->limit(30)
            ->get(['sub_account_id', 'sub_account_name', 'sub_account_code', 'sub_account_main_account']);

        return response()->json($records);
    }

    /*
    |--------------------------------------------------------------------------
    | Transactions / Deposits / Transfers
    |--------------------------------------------------------------------------
    */

    public function transactions(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $type = $request->get('type');
        $from = $request->get('from');
        $to = $request->get('to');

        $records = DB::table('sacco_special_saving_transactions as t')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 't.special_saving_transaction_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 't.special_saving_transaction_product_id')
            ->leftJoin('sacco_special_saving_accounts as a', 'a.special_saving_account_id', '=', 't.special_saving_transaction_account_id')
            ->where('t.special_saving_transaction_deleted', 'N')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('t.special_saving_transaction_doc_no', 'like', "%{$q}%")
                        ->orWhere('t.special_saving_transaction_reference', 'like', "%{$q}%")
                        ->orWhere('a.special_saving_account_number', 'like', "%{$q}%")
                        ->orWhere('m.member_name', 'like', "%{$q}%")
                        ->orWhere('m.member_sacco_id', 'like', "%{$q}%");
                });
            })
            ->when($type, fn($query) => $query->where('t.special_saving_transaction_type', $type))
            ->when($from, fn($query) => $query->whereDate('t.special_saving_transaction_date', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('t.special_saving_transaction_date', '<=', $to))
            ->select('t.*', 'm.member_name', 'm.member_sacco_id', 'p.special_saving_product_name', 'a.special_saving_account_number')
            ->orderByDesc('t.special_saving_transaction_date')
            ->orderByDesc('t.special_saving_transaction_id')
            ->paginate(25);

        return view('special_savings.transactions.index', compact('records', 'q', 'type', 'from', 'to'));
    }

    public function createTransaction()
    {
        $products = $this->activeProducts();

        return view('special_savings.transactions.create', compact('products'));
    }

    public function storeTransaction(Request $request)
    {
        $data = $request->validate([
            'special_saving_transaction_account_id' => 'required|integer',
            'special_saving_transaction_type' => 'required|string|max:40',
            'special_saving_transaction_amount' => 'required|numeric|min:0.01',
            'special_saving_transaction_date' => 'required|date',
            'special_saving_transaction_reference' => 'nullable|string|max:150',
            'special_saving_transaction_description' => 'nullable|string',
            'special_saving_transaction_sub_account_id' => 'nullable|integer',
        ]);

        $type = strtoupper($data['special_saving_transaction_type']);

        if (!in_array($type, ['DEPOSIT', 'ADJUSTMENT', 'CHARGE', 'PENALTY'])) {
            return back()->withInput()->with('error', 'This transaction type should be handled through its dedicated workflow.');
        }

        if ($type === 'DEPOSIT') {
            return $this->storeDeposit($request);
        }

        return back()->withInput()->with('error', 'Only DEPOSIT is currently supported from the generic transaction screen.');
    }

    public function showTransaction($id)
    {
        $record = DB::table('sacco_special_saving_transactions as t')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 't.special_saving_transaction_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 't.special_saving_transaction_product_id')
            ->leftJoin('sacco_special_saving_accounts as a', 'a.special_saving_account_id', '=', 't.special_saving_transaction_account_id')
            ->where('t.special_saving_transaction_id', $id)
            ->where('t.special_saving_transaction_deleted', 'N')
            ->select('t.*', 'm.member_name', 'm.member_sacco_id', 'p.special_saving_product_name', 'a.special_saving_account_number')
            ->first();

        abort_if(!$record, 404);

        return view('special_savings.transactions.show', compact('record'));
    }

    public function transactionReceipt($id)
    {
        $record = DB::table('sacco_special_saving_transactions as t')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 't.special_saving_transaction_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 't.special_saving_transaction_product_id')
            ->leftJoin('sacco_special_saving_accounts as a', 'a.special_saving_account_id', '=', 't.special_saving_transaction_account_id')
            ->where('t.special_saving_transaction_id', $id)
            ->where('t.special_saving_transaction_deleted', 'N')
            ->select('t.*', 'm.member_name', 'm.member_sacco_id', 'p.special_saving_product_name', 'a.special_saving_account_number')
            ->first();

        abort_if(!$record, 404);

        return view('special_savings.transactions.receipt', compact('record'));
    }

    public function reverseTransaction(Request $request, $id)
    {
        $transaction = DB::table('sacco_special_saving_transactions')
            ->where('special_saving_transaction_id', $id)
            ->where('special_saving_transaction_deleted', 'N')
            ->first();

        abort_if(!$transaction, 404);

        if ($transaction->special_saving_transaction_reversed === 'Y') {
            return back()->with('error', 'Transaction has already been reversed.');
        }

        DB::transaction(function () use ($request, $transaction) {
            $this->reverseTransactionEffect($transaction, $request);

            DB::table('sacco_special_saving_transactions')
                ->where('special_saving_transaction_id', $transaction->special_saving_transaction_id)
                ->update([
                    'special_saving_transaction_reversed' => 'Y',
                    'special_saving_transaction_reversed_by' => $this->userId(),
                    'special_saving_transaction_reversed_on' => now(),
                    'special_saving_transaction_reversal_reason' => $request->get('reason', 'Manual reversal'),
                ]);
        });

        return back()->with('success', 'Transaction reversed successfully.');
    }

  public function searchAccounts(Request $request)
{
    $q = trim((string) $request->get('q'));

    if (strlen($q) < 2) {
        return response()->json([
            'status' => 'empty',
            'ambiguous' => false,
            'search_stage' => null,
            'message' => 'Enter at least 2 characters.',
            'records' => [],
            'members' => [],
        ]);
    }

    $qDigits = preg_replace('/\D+/', '', $q);

    $accountPhoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(m.member_phone_no, ''), ' ', ''), '-', ''), '+', ''), '(', ''), ')', '')";
    $memberPhoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(member_phone_no, ''), ' ', ''), '-', ''), '+', ''), '(', ''), ')', '')";

    /*
    |--------------------------------------------------------------------------
    | Account search closure
    |--------------------------------------------------------------------------
    */
    $searchAccountsByStage = function (string $stage) use ($q, $qDigits, $accountPhoneSql) {
        $query = DB::table('sacco_special_saving_accounts as a')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'a.special_saving_account_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'a.special_saving_account_product_id')
            ->where('a.special_saving_account_deleted', 'N')
            ->where('a.special_saving_account_status', 'Active')
            ->where('m.member_deleted', 'N')
            ->where('m.member_active', 'Y');

        if ($stage === 'account_number_exact') {
            $query->where('a.special_saving_account_number', $q);
        }

        if ($stage === 'sacco_number_exact') {
            $query->where('m.member_sacco_id', $q);
        }

        if ($stage === 'national_id_exact') {
            $query->where('m.member_national_id', $q);
        }

        if ($stage === 'phone_exact') {
            if ($qDigits === '') {
                return collect();
            }

            $query->whereRaw("{$accountPhoneSql} = ?", [$qDigits]);
        }

        if ($stage === 'name_exact') {
            $query->where('m.member_name', $q);
        }

        if ($stage === 'name_starts') {
            $query->where('m.member_name', 'like', "{$q}%");
        }

        if ($stage === 'name_contains') {
            $query->where('m.member_name', 'like', "%{$q}%");
        }

        return $query
            ->select([
                'a.special_saving_account_id',
                'a.special_saving_account_number',
                'a.special_saving_account_principal_balance',
                'a.special_saving_account_accrued_interest_balance',
                'a.special_saving_account_available_interest_balance',
                'a.special_saving_account_total_balance',
                'a.special_saving_account_status',

                'm.member_id',
                'm.member_name',
                'm.member_sacco_id',
                'm.member_national_id',
                'm.member_phone_no',

                'p.special_saving_product_id',
                'p.special_saving_product_name',
            ])
            ->orderBy('m.member_name')
            ->limit(20)
            ->get();
    };

    /*
    |--------------------------------------------------------------------------
    | Member fallback closure
    |--------------------------------------------------------------------------
    | Used when member exists but no active special savings account exists.
    */
    $searchMembersByStage = function (string $stage) use ($q, $qDigits, $memberPhoneSql) {
        $query = DB::table('sacco_members')
            ->where('member_deleted', 'N')
            ->where('member_active', 'Y');

        if ($stage === 'account_number_exact') {
            return collect();
        }

        if ($stage === 'sacco_number_exact') {
            $query->where('member_sacco_id', $q);
        }

        if ($stage === 'national_id_exact') {
            $query->where('member_national_id', $q);
        }

        if ($stage === 'phone_exact') {
            if ($qDigits === '') {
                return collect();
            }

            $query->whereRaw("{$memberPhoneSql} = ?", [$qDigits]);
        }

        if ($stage === 'name_exact') {
            $query->where('member_name', $q);
        }

        if ($stage === 'name_starts') {
            $query->where('member_name', 'like', "{$q}%");
        }

        if ($stage === 'name_contains') {
            $query->where('member_name', 'like', "%{$q}%");
        }

        return $query
            ->select([
                'member_id',
                'member_name',
                'member_sacco_id',
                'member_national_id',
                'member_phone_no',
                'member_active',
                'member_deleted',
            ])
            ->orderBy('member_name')
            ->limit(20)
            ->get();
    };

    /*
    |--------------------------------------------------------------------------
    | Search stages
    |--------------------------------------------------------------------------
    */
    $stages = [
        'account_number_exact' => 'special savings account number',
        'sacco_number_exact' => 'SACCO number',
        'national_id_exact' => 'national ID',
        'phone_exact' => 'phone number',
        'name_exact' => 'full member name',
        'name_starts' => 'member name starting with search text',
        'name_contains' => 'member name containing search text',
    ];

    foreach ($stages as $stage => $stageLabel) {
        $records = $searchAccountsByStage($stage);

        if ($records->count() > 0) {
            if ($records->count() > 1) {
                return response()->json([
                    'status' => 'ambiguous',
                    'ambiguous' => true,
                    'search_stage' => $stage,
                    'message' => 'More than one active special saving account was found by ' . $stageLabel . '. Refine the search before posting.',
                    'records' => $records,
                    'members' => [],
                ]);
            }

            return response()->json([
                'status' => 'ok',
                'ambiguous' => false,
                'search_stage' => $stage,
                'message' => 'One active special saving account found by ' . $stageLabel . '.',
                'records' => $records,
                'members' => [],
            ]);
        }

        $members = $searchMembersByStage($stage);

        if ($members->count() > 0) {
            return response()->json([
                'status' => 'member_found_no_account',
                'ambiguous' => $members->count() > 1,
                'search_stage' => $stage,
                'message' => $members->count() > 1
                    ? 'Member records were found by ' . $stageLabel . ', but no active special saving account exists. Refine the search or open a special saving account first.'
                    : 'Member found by ' . $stageLabel . ', but this member has no active special saving account. Open a special saving account first.',
                'records' => [],
                'members' => $members,
            ]);
        }
    }

    return response()->json([
        'status' => 'empty',
        'ambiguous' => false,
        'search_stage' => null,
        'message' => 'No active member or special saving account found.',
        'records' => [],
        'members' => [],
    ]);
}

    public function storeDeposit(Request $request)
{
    $data = $request->validate([
        'special_saving_transaction_account_id' => 'required|integer',
        'special_saving_transaction_amount' => 'required|numeric|min:0.01',
        'special_saving_transaction_date' => 'required|date',
        'special_saving_transaction_reference' => 'nullable|string|max:150',
        'special_saving_transaction_description' => 'nullable|string',
        'special_saving_transaction_sub_account_id' => 'nullable|integer',
    ]);

    $account = $this->getAccount($data['special_saving_transaction_account_id']);

    $memberOk = DB::table('sacco_members')
        ->where('member_id', $account->special_saving_account_member_id)
        ->where('member_active', 'Y')
        ->where('member_deleted', 'N')
        ->exists();

    if (!$memberOk) {
        return back()
            ->withInput()
            ->with('error', 'The selected member is inactive or deleted.');
    }

    if ($account->special_saving_account_status !== 'Active') {
        return back()
            ->withInput()
            ->with('error', 'Only active accounts can receive deposits.');
    }

    $amount = $this->money($data['special_saving_transaction_amount']);
    $date = Carbon::parse($data['special_saving_transaction_date']);
    $period = $date->format('Ym');

    $transactionId = DB::transaction(function () use ($request, $account, $amount, $date, $period, $data) {
        $balances = $this->applyAccountBalanceDelta($account->special_saving_account_id, [
            'principal' => $amount,
            'accrued_interest' => 0,
            'available_interest' => 0,
            'forfeited_interest' => 0,
        ], $request);

        return $this->createSavingTransaction([
            'account_id' => $account->special_saving_account_id,
            'member_id' => $account->special_saving_account_member_id,
            'product_id' => $account->special_saving_account_product_id,
            'type' => 'DEPOSIT',
            'direction' => 'CREDIT',
            'amount' => $amount,
            'principal_amount' => $amount,
            'interest_amount' => 0,
            'penalty_amount' => 0,
            'charge_amount' => 0,
            'date' => $date->toDateString(),
            'period' => $period,
            'doc_no' => $this->generateDocNo('SSD'),
            'reference' => $data['special_saving_transaction_reference'] ?? null,
            'source' => 'MANUAL',
            'sub_account_id' => $data['special_saving_transaction_sub_account_id'] ?? null,
            'description' => $data['special_saving_transaction_description'] ?? 'Special saving deposit',
            'balances' => $balances,
            'request' => $request,
        ]);
    });

    return redirect()
        ->route('special_savings.transactions.receipt', $transactionId)
        ->with('success', 'Deposit posted successfully.');
}

    public function createTransfer()
    {
        $products = $this->activeProducts();
        $sub_accounts = $this->subAccountsList();

        return view('special_savings.transfers.create', compact('products', 'sub_accounts'));
    }

    public function storeTransfer(Request $request)
    {
        $request->merge([
            'special_saving_transaction_description' => $request->get('special_saving_transaction_description', 'Transfer into special savings'),
        ]);

        return $this->storeDeposit($request);
    }

    /*
    |--------------------------------------------------------------------------
    | Withdrawals
    |--------------------------------------------------------------------------
    */

    public function withdrawals(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $status = $request->get('status');

        $records = DB::table('sacco_special_saving_withdrawal_requests as w')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'w.special_saving_withdrawal_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'w.special_saving_withdrawal_product_id')
            ->leftJoin('sacco_special_saving_accounts as a', 'a.special_saving_account_id', '=', 'w.special_saving_withdrawal_account_id')
            ->where('w.special_saving_withdrawal_deleted', 'N')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('m.member_name', 'like', "%{$q}%")
                        ->orWhere('m.member_sacco_id', 'like', "%{$q}%")
                        ->orWhere('a.special_saving_account_number', 'like', "%{$q}%")
                        ->orWhere('w.special_saving_withdrawal_payment_reference', 'like', "%{$q}%");
                });
            })
            ->when($status, fn($query) => $query->where('w.special_saving_withdrawal_status', $status))
            ->select('w.*', 'm.member_name', 'm.member_sacco_id', 'p.special_saving_product_name', 'a.special_saving_account_number')
            ->orderByDesc('w.special_saving_withdrawal_id')
            ->paginate(25);

        return view('special_savings.withdrawals.index', compact('records', 'q', 'status'));
    }

    public function createWithdrawal()
    {
        $sub_accounts = $this->subAccountsList();

        return view('special_savings.withdrawals.create', compact('sub_accounts'));
    }

    public function storeWithdrawal(Request $request)
    {
        $data = $request->validate([
            'special_saving_withdrawal_account_id' => 'required|integer',
            'special_saving_withdrawal_request_date' => 'required|date',
            'special_saving_withdrawal_principal_amount' => 'nullable|numeric|min:0',
            'special_saving_withdrawal_interest_amount' => 'nullable|numeric|min:0',
            'special_saving_withdrawal_payment_mode' => 'nullable|string|max:50',
            'special_saving_withdrawal_payment_reference' => 'nullable|string|max:150',
            'special_saving_withdrawal_payment_sub_account_id' => 'nullable|integer',
            'special_saving_withdrawal_notes' => 'nullable|string',
        ]);

        $account = $this->getAccount($data['special_saving_withdrawal_account_id']);
        $product = $this->getProduct($account->special_saving_account_product_id);

        $principalAmount = $this->money($data['special_saving_withdrawal_principal_amount'] ?? 0);
        $interestAmount = $this->money($data['special_saving_withdrawal_interest_amount'] ?? 0);
        $requestDate = Carbon::parse($data['special_saving_withdrawal_request_date']);

        if ($principalAmount <= 0 && $interestAmount <= 0) {
            return back()->withInput()->with('error', 'Enter either principal amount or interest amount to withdraw.');
        }

        if ($principalAmount > (float) $account->special_saving_account_principal_balance) {
            return back()->withInput()->with('error', 'Principal withdrawal amount exceeds available principal balance.');
        }

        $nextFreeDate = $account->special_saving_account_next_free_withdrawal_date
            ? Carbon::parse($account->special_saving_account_next_free_withdrawal_date)
            : Carbon::parse($account->special_saving_account_opening_date)->addMonths((int) $product->special_saving_product_withdrawal_cycle_months);

        $isEarly = $requestDate->lt($nextFreeDate) ? 'Y' : 'N';

        if ($isEarly === 'Y') {
            $interestAmount = 0;
        } else {
            if ($interestAmount > (float) $account->special_saving_account_available_interest_balance) {
                return back()->withInput()->with('error', 'Interest withdrawal amount exceeds available vested interest.');
            }
        }

        $forfeitedInterest = $isEarly === 'Y'
            ? (float) $account->special_saving_account_accrued_interest_balance
            : 0;

        $withdrawalId = DB::table('sacco_special_saving_withdrawal_requests')->insertGetId([
            'special_saving_withdrawal_account_id' => $account->special_saving_account_id,
            'special_saving_withdrawal_member_id' => $account->special_saving_account_member_id,
            'special_saving_withdrawal_product_id' => $account->special_saving_account_product_id,

            'special_saving_withdrawal_request_date' => $requestDate->toDateString(),
            'special_saving_withdrawal_principal_amount' => $principalAmount,
            'special_saving_withdrawal_interest_amount' => $interestAmount,
            'special_saving_withdrawal_total_amount' => $principalAmount + $interestAmount,

            'special_saving_withdrawal_is_early' => $isEarly,
            'special_saving_withdrawal_forfeited_interest' => $forfeitedInterest,

            'special_saving_withdrawal_last_withdrawal_date' => $account->special_saving_account_last_withdrawal_date,
            'special_saving_withdrawal_next_free_withdrawal_date' => $nextFreeDate->toDateString(),

            'special_saving_withdrawal_payment_mode' => $data['special_saving_withdrawal_payment_mode'] ?? null,
            'special_saving_withdrawal_payment_reference' => $data['special_saving_withdrawal_payment_reference'] ?? null,
            'special_saving_withdrawal_payment_sub_account_id' => $data['special_saving_withdrawal_payment_sub_account_id'] ?? null,

            'special_saving_withdrawal_status' => 'Pending',
            'special_saving_withdrawal_requested_by' => $this->userId(),
            'special_saving_withdrawal_notes' => $data['special_saving_withdrawal_notes'] ?? null,

            'special_saving_withdrawal_by' => $this->userId(),
            'special_saving_withdrawal_ip' => $request->ip(),
            'special_saving_withdrawal_transdate' => now(),
            'special_saving_withdrawal_deleted' => 'N',
        ]);

        return redirect()->route('special_savings.withdrawals.show', $withdrawalId)
            ->with('success', 'Withdrawal request created successfully.');
    }

    public function showWithdrawal($id)
    {
        $record = $this->withdrawalWithDetails($id);

        return view('special_savings.withdrawals.show', compact('record'));
    }

    public function approveWithdrawal(Request $request, $id)
    {
        $record = DB::table('sacco_special_saving_withdrawal_requests')
            ->where('special_saving_withdrawal_id', $id)
            ->where('special_saving_withdrawal_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        if ($record->special_saving_withdrawal_status !== 'Pending') {
            return back()->with('error', 'Only pending withdrawal requests can be approved.');
        }

        DB::table('sacco_special_saving_withdrawal_requests')
            ->where('special_saving_withdrawal_id', $id)
            ->update([
                'special_saving_withdrawal_status' => 'Approved',
                'special_saving_withdrawal_approved_by' => $this->userId(),
                'special_saving_withdrawal_approved_on' => now(),
                'special_saving_withdrawal_by' => $this->userId(),
                'special_saving_withdrawal_ip' => $request->ip(),
                'special_saving_withdrawal_transdate' => now(),
            ]);

        return back()->with('success', 'Withdrawal approved successfully.');
    }

    public function rejectWithdrawal(Request $request, $id)
    {
        $record = DB::table('sacco_special_saving_withdrawal_requests')
            ->where('special_saving_withdrawal_id', $id)
            ->where('special_saving_withdrawal_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        if (!in_array($record->special_saving_withdrawal_status, ['Pending', 'Approved'])) {
            return back()->with('error', 'This withdrawal cannot be rejected.');
        }

        DB::table('sacco_special_saving_withdrawal_requests')
            ->where('special_saving_withdrawal_id', $id)
            ->update([
                'special_saving_withdrawal_status' => 'Rejected',
                'special_saving_withdrawal_notes' => trim(($record->special_saving_withdrawal_notes ?? '') . "\nRejected: " . $request->get('reason', 'No reason provided')),
                'special_saving_withdrawal_by' => $this->userId(),
                'special_saving_withdrawal_ip' => $request->ip(),
                'special_saving_withdrawal_transdate' => now(),
            ]);

        return back()->with('success', 'Withdrawal rejected successfully.');
    }

    public function payWithdrawal(Request $request, $id)
    {
        $record = DB::table('sacco_special_saving_withdrawal_requests')
            ->where('special_saving_withdrawal_id', $id)
            ->where('special_saving_withdrawal_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        if ($record->special_saving_withdrawal_status !== 'Approved') {
            return back()->with('error', 'Only approved withdrawals can be paid.');
        }

        $account = $this->getAccount($record->special_saving_withdrawal_account_id);
        $product = $this->getProduct($record->special_saving_withdrawal_product_id);

        $transactionIds = DB::transaction(function () use ($request, $record, $account, $product) {
            $withdrawalDate = Carbon::parse($record->special_saving_withdrawal_request_date);
            $period = $withdrawalDate->format('Ym');

            $principalAmount = (float) $record->special_saving_withdrawal_principal_amount;
            $interestAmount = (float) $record->special_saving_withdrawal_interest_amount;
            $forfeitedInterest = (float) $record->special_saving_withdrawal_forfeited_interest;

            if ($principalAmount > (float) $account->special_saving_account_principal_balance) {
                throw new \Exception('Principal withdrawal exceeds available principal balance.');
            }

            if ($record->special_saving_withdrawal_is_early !== 'Y') {
                if ($interestAmount > (float) $account->special_saving_account_available_interest_balance) {
                    throw new \Exception('Interest withdrawal exceeds available interest balance.');
                }
            }

            $balances = $this->applyAccountBalanceDelta($account->special_saving_account_id, [
                'principal' => -$principalAmount,
                'accrued_interest' => -$forfeitedInterest,
                'available_interest' => -$interestAmount,
                'forfeited_interest' => $forfeitedInterest,
            ], $request);

            $withdrawalTxnId = $this->createSavingTransaction([
                'account_id' => $account->special_saving_account_id,
                'member_id' => $account->special_saving_account_member_id,
                'product_id' => $account->special_saving_account_product_id,
                'type' => 'WITHDRAWAL',
                'direction' => 'DEBIT',
                'amount' => $principalAmount + $interestAmount,
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestAmount,
                'penalty_amount' => 0,
                'charge_amount' => 0,
                'date' => $withdrawalDate->toDateString(),
                'period' => $period,
                'doc_no' => $this->generateDocNo('SSW'),
                'reference' => $record->special_saving_withdrawal_payment_reference,
                'source' => 'MANUAL',
                'sub_account_id' => $record->special_saving_withdrawal_payment_sub_account_id,
                'description' => 'Special saving withdrawal',
                'balances' => $balances,
                'request' => $request,
            ]);

            $forfeitureTxnId = null;

            if ($forfeitedInterest > 0) {
                $forfeitureTxnId = $this->createSavingTransaction([
                    'account_id' => $account->special_saving_account_id,
                    'member_id' => $account->special_saving_account_member_id,
                    'product_id' => $account->special_saving_account_product_id,
                    'type' => 'INTEREST_FORFEITURE',
                    'direction' => 'DEBIT',
                    'amount' => $forfeitedInterest,
                    'principal_amount' => 0,
                    'interest_amount' => $forfeitedInterest,
                    'penalty_amount' => 0,
                    'charge_amount' => 0,
                    'date' => $withdrawalDate->toDateString(),
                    'period' => $period,
                    'doc_no' => $this->generateDocNo('SSF'),
                    'reference' => $record->special_saving_withdrawal_payment_reference,
                    'source' => 'SYSTEM',
                    'sub_account_id' => null,
                    'description' => 'Forfeited unvested interest due to early withdrawal',
                    'balances' => $balances,
                    'request' => $request,
                ]);
            }

            $nextFreeDate = $withdrawalDate->copy()->addMonths((int) $product->special_saving_product_withdrawal_cycle_months)->toDateString();

            DB::table('sacco_special_saving_accounts')
                ->where('special_saving_account_id', $account->special_saving_account_id)
                ->update([
                    'special_saving_account_last_withdrawal_date' => $withdrawalDate->toDateString(),
                    'special_saving_account_next_free_withdrawal_date' => $nextFreeDate,
                    'special_saving_account_by' => $this->userId(),
                    'special_saving_account_ip' => $request->ip(),
                    'special_saving_account_transdate' => now(),
                ]);

            DB::table('sacco_special_saving_withdrawal_requests')
                ->where('special_saving_withdrawal_id', $record->special_saving_withdrawal_id)
                ->update([
                    'special_saving_withdrawal_status' => 'Paid',
                    'special_saving_withdrawal_paid_by' => $this->userId(),
                    'special_saving_withdrawal_paid_on' => now(),
                    'special_saving_withdrawal_transaction_id' => $withdrawalTxnId,
                    'special_saving_withdrawal_forfeiture_transaction_id' => $forfeitureTxnId,
                    'special_saving_withdrawal_by' => $this->userId(),
                    'special_saving_withdrawal_ip' => $request->ip(),
                    'special_saving_withdrawal_transdate' => now(),
                ]);

            return [$withdrawalTxnId, $forfeitureTxnId];
        });

        return redirect()->route('special_savings.withdrawals.show', $id)
            ->with('success', 'Withdrawal paid successfully.');
    }

    public function cancelWithdrawal(Request $request, $id)
    {
        $record = DB::table('sacco_special_saving_withdrawal_requests')
            ->where('special_saving_withdrawal_id', $id)
            ->where('special_saving_withdrawal_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        if (!in_array($record->special_saving_withdrawal_status, ['Pending', 'Approved'])) {
            return back()->with('error', 'This withdrawal cannot be cancelled.');
        }

        DB::table('sacco_special_saving_withdrawal_requests')
            ->where('special_saving_withdrawal_id', $id)
            ->update([
                'special_saving_withdrawal_status' => 'Cancelled',
                'special_saving_withdrawal_by' => $this->userId(),
                'special_saving_withdrawal_ip' => $request->ip(),
                'special_saving_withdrawal_transdate' => now(),
            ]);

        return back()->with('success', 'Withdrawal cancelled successfully.');
    }

    public function withdrawalVoucher($id)
    {
        $record = $this->withdrawalWithDetails($id);

        return view('special_savings.withdrawals.voucher', compact('record'));
    }

    /*
    |--------------------------------------------------------------------------
    | Interest Processing
    |--------------------------------------------------------------------------
    */

    public function interestRuns(Request $request)
    {
        $period = $request->get('period');
        $product_id = $request->get('product_id');

        $products = $this->activeProducts();

        $records = DB::table('sacco_special_saving_interest_runs as r')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'r.special_saving_interest_run_product_id')
            ->where('r.special_saving_interest_run_deleted', 'N')
            ->when($period, fn($q) => $q->where('r.special_saving_interest_run_period', $period))
            ->when($product_id, fn($q) => $q->where('r.special_saving_interest_run_product_id', $product_id))
            ->select('r.*', 'p.special_saving_product_name')
            ->orderByDesc('r.special_saving_interest_run_id')
            ->paginate(25);

        return view('special_savings.interest.index', compact('records', 'products', 'period', 'product_id'));
    }

    public function createInterestRun()
    {
        $products = $this->activeProducts();
        $period = now()->format('Ym');

        return view('special_savings.interest.create', compact('products', 'period'));
    }

    public function previewInterestRun(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'period' => 'required|string|size:6',
        ]);

        $product = $this->getProduct($data['product_id']);
        $items = $this->buildInterestItems($product, $data['period']);

        return view('special_savings.interest.preview', compact('product', 'items'));
    }

    public function processInterestRun(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'period' => 'required|string|size:6',
        ]);

        $product = $this->getProduct($data['product_id']);

        $runId = $this->createInterestRunInternal($product, $data['period'], $request, false);

        return redirect()->route('special_savings.interest.show', $runId)
            ->with('success', 'Interest run created successfully. Review and post it.');
    }

    public function showInterestRun($id)
    {
        $record = DB::table('sacco_special_saving_interest_runs as r')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'r.special_saving_interest_run_product_id')
            ->where('r.special_saving_interest_run_id', $id)
            ->where('r.special_saving_interest_run_deleted', 'N')
            ->select('r.*', 'p.special_saving_product_name')
            ->first();

        abort_if(!$record, 404);

        $items = DB::table('sacco_special_saving_interest_run_items as i')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'i.special_saving_interest_item_member_id')
            ->leftJoin('sacco_special_saving_accounts as a', 'a.special_saving_account_id', '=', 'i.special_saving_interest_item_account_id')
            ->where('i.special_saving_interest_item_run_id', $id)
            ->select('i.*', 'm.member_name', 'm.member_sacco_id', 'a.special_saving_account_number')
            ->orderBy('m.member_name')
            ->paginate(50);

        return view('special_savings.interest.show', compact('record', 'items'));
    }

    public function interestRunItems($run_id)
    {
        $record = DB::table('sacco_special_saving_interest_runs')
            ->where('special_saving_interest_run_id', $run_id)
            ->where('special_saving_interest_run_deleted', 'N')
            ->first();

        abort_if(!$record, 404);

        $items = DB::table('sacco_special_saving_interest_run_items as i')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'i.special_saving_interest_item_member_id')
            ->leftJoin('sacco_special_saving_accounts as a', 'a.special_saving_account_id', '=', 'i.special_saving_interest_item_account_id')
            ->where('i.special_saving_interest_item_run_id', $run_id)
            ->select('i.*', 'm.member_name', 'm.member_sacco_id', 'a.special_saving_account_number')
            ->orderBy('m.member_name')
            ->paginate(100);

        return view('special_savings.interest.items', compact('record', 'items'));
    }

    public function postInterestRun(Request $request, $id)
    {
        $run = DB::table('sacco_special_saving_interest_runs')
            ->where('special_saving_interest_run_id', $id)
            ->where('special_saving_interest_run_deleted', 'N')
            ->first();

        abort_if(!$run, 404);

        if ($run->special_saving_interest_run_status === 'Posted') {
            return back()->with('error', 'This interest run has already been posted.');
        }

        if (!in_array($run->special_saving_interest_run_status, ['Draft', 'Processing'])) {
            return back()->with('error', 'This interest run cannot be posted.');
        }

        DB::transaction(function () use ($request, $run) {
            $items = DB::table('sacco_special_saving_interest_run_items')
                ->where('special_saving_interest_item_run_id', $run->special_saving_interest_run_id)
                ->where('special_saving_interest_item_qualified', 'Y')
                ->where('special_saving_interest_item_status', 'Pending')
                ->get();

            foreach ($items as $item) {
                $account = $this->getAccount($item->special_saving_interest_item_account_id);
                $interestAmount = (float) $item->special_saving_interest_item_interest_amount;

                if ($interestAmount <= 0) {
                    continue;
                }

                $balances = $this->applyAccountBalanceDelta($account->special_saving_account_id, [
                    'principal' => 0,
                    'accrued_interest' => $interestAmount,
                    'available_interest' => 0,
                    'forfeited_interest' => 0,
                ], $request);

                $txnId = $this->createSavingTransaction([
                    'account_id' => $account->special_saving_account_id,
                    'member_id' => $account->special_saving_account_member_id,
                    'product_id' => $account->special_saving_account_product_id,
                    'type' => 'INTEREST_ACCRUAL',
                    'direction' => 'CREDIT',
                    'amount' => $interestAmount,
                    'principal_amount' => 0,
                    'interest_amount' => $interestAmount,
                    'penalty_amount' => 0,
                    'charge_amount' => 0,
                    'date' => $run->special_saving_interest_run_end_date,
                    'period' => $run->special_saving_interest_run_period,
                    'doc_no' => $this->generateDocNo('SSI'),
                    'reference' => 'RUN-' . $run->special_saving_interest_run_id,
                    'source' => 'INTEREST_RUN',
                    'sub_account_id' => null,
                    'description' => 'Monthly special saving interest accrual',
                    'balances' => $balances,
                    'request' => $request,
                ]);

                DB::table('sacco_special_saving_interest_run_items')
                    ->where('special_saving_interest_item_id', $item->special_saving_interest_item_id)
                    ->update([
                        'special_saving_interest_item_transaction_id' => $txnId,
                        'special_saving_interest_item_status' => 'Posted',
                    ]);

                DB::table('sacco_special_saving_accounts')
                    ->where('special_saving_account_id', $account->special_saving_account_id)
                    ->update([
                        'special_saving_account_last_interest_date' => $run->special_saving_interest_run_end_date,
                    ]);
            }

            DB::table('sacco_special_saving_interest_run_items')
                ->where('special_saving_interest_item_run_id', $run->special_saving_interest_run_id)
                ->where('special_saving_interest_item_qualified', 'N')
                ->where('special_saving_interest_item_status', 'Pending')
                ->update([
                    'special_saving_interest_item_status' => 'Skipped',
                ]);

            DB::table('sacco_special_saving_interest_runs')
                ->where('special_saving_interest_run_id', $run->special_saving_interest_run_id)
                ->update([
                    'special_saving_interest_run_status' => 'Posted',
                    'special_saving_interest_run_posted_on' => now(),
                    'special_saving_interest_run_posted_by' => $this->userId(),
                    'special_saving_interest_run_by' => $this->userId(),
                    'special_saving_interest_run_ip' => $request->ip(),
                    'special_saving_interest_run_transdate' => now(),
                ]);
        });

        return back()->with('success', 'Interest run posted successfully.');
    }

    public function reverseInterestRun(Request $request, $id)
    {
        $run = DB::table('sacco_special_saving_interest_runs')
            ->where('special_saving_interest_run_id', $id)
            ->where('special_saving_interest_run_deleted', 'N')
            ->first();

        abort_if(!$run, 404);

        if ($run->special_saving_interest_run_status !== 'Posted') {
            return back()->with('error', 'Only posted interest runs can be reversed.');
        }

        DB::transaction(function () use ($request, $run) {
            $items = DB::table('sacco_special_saving_interest_run_items')
                ->where('special_saving_interest_item_run_id', $run->special_saving_interest_run_id)
                ->where('special_saving_interest_item_status', 'Posted')
                ->get();

            foreach ($items as $item) {
                if (!$item->special_saving_interest_item_transaction_id) {
                    continue;
                }

                $transaction = DB::table('sacco_special_saving_transactions')
                    ->where('special_saving_transaction_id', $item->special_saving_interest_item_transaction_id)
                    ->where('special_saving_transaction_reversed', 'N')
                    ->first();

                if (!$transaction) {
                    continue;
                }

                $this->reverseTransactionEffect($transaction, $request);

                DB::table('sacco_special_saving_transactions')
                    ->where('special_saving_transaction_id', $transaction->special_saving_transaction_id)
                    ->update([
                        'special_saving_transaction_reversed' => 'Y',
                        'special_saving_transaction_reversed_by' => $this->userId(),
                        'special_saving_transaction_reversed_on' => now(),
                        'special_saving_transaction_reversal_reason' => 'Interest run reversal',
                    ]);

                DB::table('sacco_special_saving_interest_run_items')
                    ->where('special_saving_interest_item_id', $item->special_saving_interest_item_id)
                    ->update([
                        'special_saving_interest_item_status' => 'Reversed',
                    ]);
            }

            DB::table('sacco_special_saving_interest_runs')
                ->where('special_saving_interest_run_id', $run->special_saving_interest_run_id)
                ->update([
                    'special_saving_interest_run_status' => 'Reversed',
                    'special_saving_interest_run_by' => $this->userId(),
                    'special_saving_interest_run_ip' => $request->ip(),
                    'special_saving_interest_run_transdate' => now(),
                ]);
        });

        return back()->with('success', 'Interest run reversed successfully.');
    }

    public function cancelInterestRun(Request $request, $id)
    {
        $run = DB::table('sacco_special_saving_interest_runs')
            ->where('special_saving_interest_run_id', $id)
            ->where('special_saving_interest_run_deleted', 'N')
            ->first();

        abort_if(!$run, 404);

        if ($run->special_saving_interest_run_status === 'Posted') {
            return back()->with('error', 'Posted interest runs cannot be cancelled. Reverse instead.');
        }

        DB::table('sacco_special_saving_interest_runs')
            ->where('special_saving_interest_run_id', $id)
            ->update([
                'special_saving_interest_run_status' => 'Cancelled',
                'special_saving_interest_run_by' => $this->userId(),
                'special_saving_interest_run_ip' => $request->ip(),
                'special_saving_interest_run_transdate' => now(),
            ]);

        DB::table('sacco_special_saving_interest_run_items')
            ->where('special_saving_interest_item_run_id', $id)
            ->where('special_saving_interest_item_status', 'Pending')
            ->update([
                'special_saving_interest_item_status' => 'Skipped',
            ]);

        return back()->with('success', 'Interest run cancelled successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Vesting
    |--------------------------------------------------------------------------
    */

    public function vesting(Request $request)
    {
        $products = $this->activeProducts();
        $process_date = $request->get('process_date', now()->toDateString());

        return view('special_savings.vesting.index', compact('products', 'process_date'));
    }

    public function previewVesting(Request $request)
    {
        $data = $request->validate([
            'process_date' => 'required|date',
            'product_id' => 'nullable|integer',
        ]);

        $items = $this->buildVestingItems($data['process_date'], $data['product_id'] ?? null);

        return view('special_savings.vesting.preview', compact('items', 'data'));
    }

    public function processVesting(Request $request)
    {
        $data = $request->validate([
            'process_date' => 'required|date',
            'product_id' => 'nullable|integer',
        ]);

        $items = $this->buildVestingItems($data['process_date'], $data['product_id'] ?? null);

        $count = 0;
        $total = 0;

        DB::transaction(function () use ($request, $items, &$count, &$total) {
            foreach ($items as $item) {
                if ((float) $item->special_saving_account_accrued_interest_balance <= 0) {
                    continue;
                }

                $amount = (float) $item->special_saving_account_accrued_interest_balance;

                $balances = $this->applyAccountBalanceDelta($item->special_saving_account_id, [
                    'principal' => 0,
                    'accrued_interest' => -$amount,
                    'available_interest' => $amount,
                    'forfeited_interest' => 0,
                ], $request);

                $this->createSavingTransaction([
                    'account_id' => $item->special_saving_account_id,
                    'member_id' => $item->special_saving_account_member_id,
                    'product_id' => $item->special_saving_account_product_id,
                    'type' => 'INTEREST_VESTING',
                    'direction' => 'CREDIT',
                    'amount' => $amount,
                    'principal_amount' => 0,
                    'interest_amount' => $amount,
                    'penalty_amount' => 0,
                    'charge_amount' => 0,
                    'date' => $request->get('process_date'),
                    'period' => Carbon::parse($request->get('process_date'))->format('Ym'),
                    'doc_no' => $this->generateDocNo('SSV'),
                    'reference' => null,
                    'source' => 'SYSTEM',
                    'sub_account_id' => null,
                    'description' => 'Accrued interest vested and made available for withdrawal',
                    'balances' => $balances,
                    'request' => $request,
                ]);

                $count++;
                $total += $amount;
            }
        });

        return redirect()->route('special_savings.vesting.index')
            ->with('success', "{$count} accounts vested. Total vested interest: " . number_format($total, 2));
    }

    /*
    |--------------------------------------------------------------------------
    | End Month
    |--------------------------------------------------------------------------
    */

    public function endMonth(Request $request)
    {
        $products = $this->activeProducts();
        $period = $request->get('period', now()->format('Ym'));

        return view('special_savings.end_month.index', compact('products', 'period'));
    }

    public function processEndMonth(Request $request)
    {
        $data = $request->validate([
            'period' => 'required|string|size:6',
            'product_id' => 'nullable|integer',
            'post_immediately' => 'nullable|string|max:1',
            'force' => 'nullable|string|max:1',
        ]);

        $period = $data['period'];
        $endDate = $this->periodEndDate($period);

        if (($data['force'] ?? 'N') !== 'Y' && !Carbon::parse($endDate)->isLastOfMonth()) {
            return back()->with('error', 'The selected period does not resolve to a valid month-end date.');
        }

        $productsQuery = DB::table('sacco_special_saving_products')
            ->where('special_saving_product_deleted', 'N')
            ->where('special_saving_product_status', 'Active')
            ->where('special_saving_product_interest_posting_frequency', 'MONTHLY');

        if (!empty($data['product_id'])) {
            $productsQuery->where('special_saving_product_id', $data['product_id']);
        }

        $products = $productsQuery->get();

        $processed = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $existing = DB::table('sacco_special_saving_interest_runs')
                ->where('special_saving_interest_run_product_id', $product->special_saving_product_id)
                ->where('special_saving_interest_run_period', $period)
                ->whereNotIn('special_saving_interest_run_status', ['Cancelled', 'Reversed'])
                ->first();

            if ($existing) {
                $skipped++;
                continue;
            }

            $runId = $this->createInterestRunInternal($product, $period, $request, false);

            if (($data['post_immediately'] ?? 'Y') === 'Y') {
                $this->postInterestRun($request, $runId);
            }

            $processed++;
        }

        $vestingItems = $this->buildVestingItems($endDate, $data['product_id'] ?? null);

        if (count($vestingItems) > 0) {
            $request->merge([
                'process_date' => $endDate,
                'product_id' => $data['product_id'] ?? null,
            ]);

            $this->processVesting($request);
        }

        return redirect()->route('special_savings.end_month.index', ['period' => $period])
            ->with('success', "End-month processing completed. Processed: {$processed}. Skipped existing: {$skipped}.");
    }

    /*
    |--------------------------------------------------------------------------
    | Imports
    |--------------------------------------------------------------------------
    */

    public function importForm()
    {
        $products = $this->activeProducts();

        return view('special_savings.import.index', compact('products'));
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
            'product_id' => 'required|integer',
            'transaction_date' => 'required|date',
        ]);

        $file = $request->file('csv_file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));

        $header = array_map('trim', array_shift($rows));
        $preview = [];

        foreach ($rows as $row) {
            $item = array_combine($header, $row);

            if (!$item) {
                continue;
            }

            $preview[] = $item;

            if (count($preview) >= 100) {
                break;
            }
        }

        session([
            'special_savings_import' => [
                'product_id' => $request->product_id,
                'transaction_date' => $request->transaction_date,
                'rows' => $rows,
                'header' => $header,
            ]
        ]);

        return view('special_savings.import.preview', compact('preview', 'header'));
    }

    public function importProcess(Request $request)
    {
        $payload = session('special_savings_import');

        if (!$payload) {
            return redirect()->route('special_savings.import.form')
                ->with('error', 'No import preview found. Please upload the file again.');
        }

        $product = $this->getProduct($payload['product_id']);
        $date = Carbon::parse($payload['transaction_date']);
        $posted = 0;
        $skipped = 0;

        foreach ($payload['rows'] as $row) {
            $item = array_combine($payload['header'], $row);

            if (!$item) {
                $skipped++;
                continue;
            }

            $memberSaccoId = trim($item['member_sacco_id'] ?? $item['sacco_no'] ?? '');
            $amount = $this->money($item['amount'] ?? 0);

            if ($memberSaccoId === '' || $amount <= 0) {
                $skipped++;
                continue;
            }

            $member = DB::table('sacco_members')
                ->where('member_sacco_id', $memberSaccoId)
                ->first();

            if (!$member) {
                $skipped++;
                continue;
            }

            $account = DB::table('sacco_special_saving_accounts')
                ->where('special_saving_account_member_id', $member->member_id)
                ->where('special_saving_account_product_id', $product->special_saving_product_id)
                ->where('special_saving_account_deleted', 'N')
                ->where('special_saving_account_status', 'Active')
                ->first();

            if (!$account) {
                $openingDate = $date->toDateString();

                $accountId = DB::table('sacco_special_saving_accounts')->insertGetId([
                    'special_saving_account_member_id' => $member->member_id,
                    'special_saving_account_product_id' => $product->special_saving_product_id,
                    'special_saving_account_number' => null,
                    'special_saving_account_opening_date' => $openingDate,
                    'special_saving_account_next_free_withdrawal_date' => Carbon::parse($openingDate)->addMonths((int) $product->special_saving_product_withdrawal_cycle_months)->toDateString(),
                    'special_saving_account_status' => 'Active',
                    'special_saving_account_by' => $this->userId(),
                    'special_saving_account_ip' => $request->ip(),
                    'special_saving_account_transdate' => now(),
                    'special_saving_account_deleted' => 'N',
                ]);

                DB::table('sacco_special_saving_accounts')
                    ->where('special_saving_account_id', $accountId)
                    ->update([
                        'special_saving_account_number' => $this->generateAccountNumber($accountId, $product->special_saving_product_code),
                    ]);

                $account = $this->getAccount($accountId);
            }

            DB::transaction(function () use ($request, $account, $amount, $date, $item) {
                $balances = $this->applyAccountBalanceDelta($account->special_saving_account_id, [
                    'principal' => $amount,
                    'accrued_interest' => 0,
                    'available_interest' => 0,
                    'forfeited_interest' => 0,
                ], $request);

                $this->createSavingTransaction([
                    'account_id' => $account->special_saving_account_id,
                    'member_id' => $account->special_saving_account_member_id,
                    'product_id' => $account->special_saving_account_product_id,
                    'type' => 'DEPOSIT',
                    'direction' => 'CREDIT',
                    'amount' => $amount,
                    'principal_amount' => $amount,
                    'interest_amount' => 0,
                    'penalty_amount' => 0,
                    'charge_amount' => 0,
                    'date' => $date->toDateString(),
                    'period' => $date->format('Ym'),
                    'doc_no' => $this->generateDocNo('SSI'),
                    'reference' => $item['reference'] ?? null,
                    'source' => 'IMPORT',
                    'sub_account_id' => null,
                    'description' => $item['description'] ?? 'Imported special saving contribution',
                    'balances' => $balances,
                    'request' => $request,
                ]);
            });

            $posted++;
        }

        session()->forget('special_savings_import');

        return redirect()->route('special_savings.import.form')
            ->with('success', "Import completed. Posted: {$posted}. Skipped: {$skipped}.");
    }

    public function importCancel()
    {
        session()->forget('special_savings_import');

        return redirect()->route('special_savings.import.form')
            ->with('success', 'Import cancelled.');
    }

    public function importSample()
    {
        $headers = [
            'member_sacco_id',
            'amount',
            'reference',
            'description',
        ];

        return $this->csvResponse('special_savings_import_sample.csv', $headers, [
            ['1001', '5000', 'PAYROLL-JUN-2026', 'FEDHA contribution'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    public function reportBalances(Request $request)
    {
        return $this->accounts($request);
    }

    public function exportBalances(Request $request)
    {
        $records = DB::table('sacco_special_saving_accounts as a')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'a.special_saving_account_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'a.special_saving_account_product_id')
            ->where('a.special_saving_account_deleted', 'N')
            ->select('a.*', 'm.member_name', 'm.member_sacco_id', 'p.special_saving_product_name')
            ->orderBy('m.member_name')
            ->get();

        $rows = [];

        foreach ($records as $r) {
            $rows[] = [
                $r->member_sacco_id,
                $r->member_name,
                $r->special_saving_product_name,
                $r->special_saving_account_number,
                $r->special_saving_account_principal_balance,
                $r->special_saving_account_accrued_interest_balance,
                $r->special_saving_account_available_interest_balance,
                $r->special_saving_account_forfeited_interest_balance,
                $r->special_saving_account_total_balance,
                $r->special_saving_account_status,
            ];
        }

        return $this->csvResponse('special_savings_balances.csv', [
            'Sacco No',
            'Member',
            'Product',
            'Account No',
            'Principal',
            'Accrued Interest',
            'Available Interest',
            'Forfeited Interest',
            'Total Balance',
            'Status',
        ], $rows);
    }

    public function reportTransactions(Request $request)
    {
        return $this->transactions($request);
    }

    public function exportTransactions(Request $request)
    {
        $records = DB::table('sacco_special_saving_transactions as t')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 't.special_saving_transaction_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 't.special_saving_transaction_product_id')
            ->leftJoin('sacco_special_saving_accounts as a', 'a.special_saving_account_id', '=', 't.special_saving_transaction_account_id')
            ->where('t.special_saving_transaction_deleted', 'N')
            ->select('t.*', 'm.member_name', 'm.member_sacco_id', 'p.special_saving_product_name', 'a.special_saving_account_number')
            ->orderBy('t.special_saving_transaction_date')
            ->get();

        $rows = [];

        foreach ($records as $r) {
            $rows[] = [
                $r->special_saving_transaction_date,
                $r->special_saving_transaction_period,
                $r->member_sacco_id,
                $r->member_name,
                $r->special_saving_product_name,
                $r->special_saving_account_number,
                $r->special_saving_transaction_type,
                $r->special_saving_transaction_doc_no,
                $r->special_saving_transaction_amount,
                $r->special_saving_transaction_principal_amount,
                $r->special_saving_transaction_interest_amount,
                $r->special_saving_transaction_total_balance_after,
            ];
        }

        return $this->csvResponse('special_savings_transactions.csv', [
            'Date',
            'Period',
            'Sacco No',
            'Member',
            'Product',
            'Account No',
            'Type',
            'Doc No',
            'Amount',
            'Principal',
            'Interest',
            'Balance After',
        ], $rows);
    }

    public function reportInterest(Request $request)
    {
        return $this->interestRuns($request);
    }

    public function exportInterest(Request $request)
    {
        $records = DB::table('sacco_special_saving_interest_run_items as i')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'i.special_saving_interest_item_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'i.special_saving_interest_item_product_id')
            ->leftJoin('sacco_special_saving_accounts as a', 'a.special_saving_account_id', '=', 'i.special_saving_interest_item_account_id')
            ->select('i.*', 'm.member_name', 'm.member_sacco_id', 'p.special_saving_product_name', 'a.special_saving_account_number')
            ->orderByDesc('i.special_saving_interest_item_id')
            ->get();

        $rows = [];

        foreach ($records as $r) {
            $rows[] = [
                $r->special_saving_interest_item_period,
                $r->member_sacco_id,
                $r->member_name,
                $r->special_saving_product_name,
                $r->special_saving_account_number,
                $r->special_saving_interest_item_qualifying_balance,
                $r->special_saving_interest_item_monthly_rate,
                $r->special_saving_interest_item_interest_amount,
                $r->special_saving_interest_item_qualified,
                $r->special_saving_interest_item_skip_reason,
                $r->special_saving_interest_item_status,
            ];
        }

        return $this->csvResponse('special_savings_interest.csv', [
            'Period',
            'Sacco No',
            'Member',
            'Product',
            'Account No',
            'Qualifying Balance',
            'Monthly Rate',
            'Interest',
            'Qualified',
            'Skip Reason',
            'Status',
        ], $rows);
    }

    public function reportWithdrawals(Request $request)
    {
        return $this->withdrawals($request);
    }

    public function exportWithdrawals(Request $request)
    {
        $records = DB::table('sacco_special_saving_withdrawal_requests as w')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'w.special_saving_withdrawal_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'w.special_saving_withdrawal_product_id')
            ->leftJoin('sacco_special_saving_accounts as a', 'a.special_saving_account_id', '=', 'w.special_saving_withdrawal_account_id')
            ->where('w.special_saving_withdrawal_deleted', 'N')
            ->select('w.*', 'm.member_name', 'm.member_sacco_id', 'p.special_saving_product_name', 'a.special_saving_account_number')
            ->orderByDesc('w.special_saving_withdrawal_id')
            ->get();

        $rows = [];

        foreach ($records as $r) {
            $rows[] = [
                $r->special_saving_withdrawal_request_date,
                $r->member_sacco_id,
                $r->member_name,
                $r->special_saving_product_name,
                $r->special_saving_account_number,
                $r->special_saving_withdrawal_principal_amount,
                $r->special_saving_withdrawal_interest_amount,
                $r->special_saving_withdrawal_total_amount,
                $r->special_saving_withdrawal_is_early,
                $r->special_saving_withdrawal_forfeited_interest,
                $r->special_saving_withdrawal_status,
            ];
        }

        return $this->csvResponse('special_savings_withdrawals.csv', [
            'Date',
            'Sacco No',
            'Member',
            'Product',
            'Account No',
            'Principal',
            'Interest',
            'Total',
            'Early',
            'Forfeited Interest',
            'Status',
        ], $rows);
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX
    |--------------------------------------------------------------------------
    */

    public function ajaxAccountSummary($account_id)
    {
        return response()->json($this->accountWithDetails($account_id));
    }

    public function ajaxMemberAccounts($member_id)
    {
        $records = DB::table('sacco_special_saving_accounts as a')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'a.special_saving_account_product_id')
            ->where('a.special_saving_account_member_id', $member_id)
            ->where('a.special_saving_account_deleted', 'N')
            ->select('a.*', 'p.special_saving_product_name')
            ->orderBy('p.special_saving_product_name')
            ->get();

        return response()->json($records);
    }

    public function ajaxProductRules($product_id)
    {
        return response()->json($this->getProduct($product_id));
    }

    public function ajaxWithdrawalPreview($account_id)
    {
        $account = $this->getAccount($account_id);
        $product = $this->getProduct($account->special_saving_account_product_id);

        $today = now();
        $nextFree = $account->special_saving_account_next_free_withdrawal_date
            ? Carbon::parse($account->special_saving_account_next_free_withdrawal_date)
            : Carbon::parse($account->special_saving_account_opening_date)->addMonths((int) $product->special_saving_product_withdrawal_cycle_months);

        $isEarly = $today->lt($nextFree);

        return response()->json([
            'account_id' => $account->special_saving_account_id,
            'principal_balance' => (float) $account->special_saving_account_principal_balance,
            'accrued_interest_balance' => (float) $account->special_saving_account_accrued_interest_balance,
            'available_interest_balance' => (float) $account->special_saving_account_available_interest_balance,
            'total_balance' => (float) $account->special_saving_account_total_balance,
            'is_early' => $isEarly ? 'Y' : 'N',
            'next_free_withdrawal_date' => $nextFree->toDateString(),
            'interest_payable_now' => $isEarly ? 0 : (float) $account->special_saving_account_available_interest_balance,
            'interest_to_forfeit_if_early' => $isEarly ? (float) $account->special_saving_account_accrued_interest_balance : 0,
        ]);
    }

    public function ajaxInterestPreview($account_id)
    {
        $account = $this->getAccount($account_id);
        $product = $this->getProduct($account->special_saving_account_product_id);
        $period = now()->format('Ym');

        $balances = $this->calculateQualifyingBalance($account, $product, $period);
        $monthlyRate = $this->resolveMonthlyRate($product, $balances['qualifying_balance']);
        $interest = round($balances['qualifying_balance'] * ($monthlyRate / 100), 2);

        return response()->json([
            'period' => $period,
            'method' => $product->special_saving_product_interest_method,
            'qualifying_balance' => $balances['qualifying_balance'],
            'monthly_rate' => $monthlyRate,
            'estimated_interest' => $interest,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internal Helpers
    |--------------------------------------------------------------------------
    */

    private function createInterestRunInternal($product, string $period, Request $request, bool $postImmediately = false): int
    {
        $existing = DB::table('sacco_special_saving_interest_runs')
            ->where('special_saving_interest_run_product_id', $product->special_saving_product_id)
            ->where('special_saving_interest_run_period', $period)
            ->whereNotIn('special_saving_interest_run_status', ['Cancelled', 'Reversed'])
            ->first();

        if ($existing) {
            return $existing->special_saving_interest_run_id;
        }

        $items = $this->buildInterestItems($product, $period);
        $startDate = $this->periodStartDate($period);
        $endDate = $this->periodEndDate($period);

        $qualified = collect($items)->where('special_saving_interest_item_qualified', 'Y');
        $skipped = collect($items)->where('special_saving_interest_item_qualified', 'N');

        $runId = DB::transaction(function () use ($request, $product, $period, $startDate, $endDate, $items, $qualified, $skipped) {
            $runId = DB::table('sacco_special_saving_interest_runs')->insertGetId([
                'special_saving_interest_run_product_id' => $product->special_saving_product_id,
                'special_saving_interest_run_period' => $period,
                'special_saving_interest_run_start_date' => $startDate,
                'special_saving_interest_run_end_date' => $endDate,
                'special_saving_interest_run_method' => $product->special_saving_product_interest_method,
                'special_saving_interest_run_annual_rate' => $product->special_saving_product_annual_interest_rate,
                'special_saving_interest_run_monthly_rate' => $product->special_saving_product_monthly_interest_rate,
                'special_saving_interest_run_total_accounts' => count($items),
                'special_saving_interest_run_qualified_accounts' => $qualified->count(),
                'special_saving_interest_run_skipped_accounts' => $skipped->count(),
                'special_saving_interest_run_total_qualifying_balance' => $qualified->sum('special_saving_interest_item_qualifying_balance'),
                'special_saving_interest_run_total_interest' => $qualified->sum('special_saving_interest_item_interest_amount'),
                'special_saving_interest_run_status' => 'Draft',
                'special_saving_interest_run_by' => $this->userId(),
                'special_saving_interest_run_ip' => $request->ip(),
                'special_saving_interest_run_transdate' => now(),
                'special_saving_interest_run_deleted' => 'N',
            ]);

            foreach ($items as $item) {
                $item['special_saving_interest_item_run_id'] = $runId;
                $item['special_saving_interest_item_by'] = $this->userId();
                $item['special_saving_interest_item_ip'] = $request->ip();
                $item['special_saving_interest_item_transdate'] = now();

                DB::table('sacco_special_saving_interest_run_items')->insert($item);
            }

            return $runId;
        });

        if ($postImmediately) {
            $this->postInterestRun($request, $runId);
        }

        return $runId;
    }

    private function buildInterestItems($product, string $period): array
    {
        $accounts = DB::table('sacco_special_saving_accounts')
            ->where('special_saving_account_product_id', $product->special_saving_product_id)
            ->where('special_saving_account_deleted', 'N')
            ->where('special_saving_account_status', 'Active')
            ->get();

        $items = [];
        $startDate = Carbon::parse($this->periodStartDate($period));
        $endDate = Carbon::parse($this->periodEndDate($period));

        foreach ($accounts as $account) {
            $openingDate = Carbon::parse($account->special_saving_account_opening_date);
            $daysInProduct = $openingDate->diffInDays($endDate) + 1;

            $qualified = 'Y';
            $skipReason = null;

            if ($product->special_saving_product_require_full_month === 'Y' && $openingDate->gt($startDate)) {
                $qualified = 'N';
                $skipReason = 'Account was not active for the full month.';
            }

            if ($qualified === 'Y' && $daysInProduct < (int) $product->special_saving_product_member_minimum_days) {
                $qualified = 'N';
                $skipReason = 'Account has not met the minimum required days.';
            }

            $balanceData = $this->calculateQualifyingBalance($account, $product, $period);
            $monthlyRate = $this->resolveMonthlyRate($product, $balanceData['qualifying_balance']);
            $interest = round($balanceData['qualifying_balance'] * ($monthlyRate / 100), 2);

            if ($qualified === 'Y' && $balanceData['qualifying_balance'] <= 0) {
                $qualified = 'N';
                $skipReason = 'No qualifying balance.';
            }

            if ($qualified === 'Y' && $interest <= 0) {
                $qualified = 'N';
                $skipReason = 'Calculated interest is zero.';
            }

            $items[] = [
                'special_saving_interest_item_account_id' => $account->special_saving_account_id,
                'special_saving_interest_item_member_id' => $account->special_saving_account_member_id,
                'special_saving_interest_item_product_id' => $account->special_saving_account_product_id,
                'special_saving_interest_item_period' => $period,

                'special_saving_interest_item_opening_balance' => $balanceData['opening_balance'],
                'special_saving_interest_item_closing_balance' => $balanceData['closing_balance'],
                'special_saving_interest_item_minimum_balance' => $balanceData['minimum_balance'],
                'special_saving_interest_item_qualifying_balance' => $balanceData['qualifying_balance'],

                'special_saving_interest_item_annual_rate' => $monthlyRate * 12,
                'special_saving_interest_item_monthly_rate' => $monthlyRate,
                'special_saving_interest_item_interest_amount' => $qualified === 'Y' ? $interest : 0,

                'special_saving_interest_item_days_as_member' => $daysInProduct,
                'special_saving_interest_item_days_in_product' => $daysInProduct,
                'special_saving_interest_item_minimum_deposit_days' => (int) $product->special_saving_product_deposit_minimum_days,

                'special_saving_interest_item_qualified' => $qualified,
                'special_saving_interest_item_skip_reason' => $skipReason,
                'special_saving_interest_item_transaction_id' => null,
                'special_saving_interest_item_status' => 'Pending',
            ];
        }

        return $items;
    }

    private function calculateQualifyingBalance($account, $product, string $period): array
    {
        $startDate = $this->periodStartDate($period);
        $endDate = $this->periodEndDate($period);

        $lastBefore = DB::table('sacco_special_saving_transactions')
            ->where('special_saving_transaction_account_id', $account->special_saving_account_id)
            ->where('special_saving_transaction_deleted', 'N')
            ->where('special_saving_transaction_reversed', 'N')
            ->whereDate('special_saving_transaction_date', '<', $startDate)
            ->orderByDesc('special_saving_transaction_date')
            ->orderByDesc('special_saving_transaction_id')
            ->first();

        $opening = $lastBefore
            ? (float) $lastBefore->special_saving_transaction_principal_balance_after
            : 0;

        $transactions = DB::table('sacco_special_saving_transactions')
            ->where('special_saving_transaction_account_id', $account->special_saving_account_id)
            ->where('special_saving_transaction_deleted', 'N')
            ->where('special_saving_transaction_reversed', 'N')
            ->whereBetween('special_saving_transaction_date', [$startDate, $endDate])
            ->orderBy('special_saving_transaction_date')
            ->orderBy('special_saving_transaction_id')
            ->get();

        $minimum = $opening;
        $closing = $opening;

        foreach ($transactions as $txn) {
            $closing = (float) $txn->special_saving_transaction_principal_balance_after;
            $minimum = min($minimum, $closing);
        }

        if ($transactions->count() === 0 && $opening == 0) {
            $opening = (float) $account->special_saving_account_principal_balance;
            $closing = (float) $account->special_saving_account_principal_balance;
            $minimum = (float) $account->special_saving_account_principal_balance;
        }

        $method = strtoupper($product->special_saving_product_interest_method);

        if ($method === 'CLOSING_BALANCE') {
            $qualifying = $closing;

            $depositMinimumDays = (int) $product->special_saving_product_deposit_minimum_days;

            if ($depositMinimumDays > 0) {
                $lateDepositStart = Carbon::parse($endDate)->subDays($depositMinimumDays - 1)->toDateString();

                $lateDeposits = DB::table('sacco_special_saving_transactions')
                    ->where('special_saving_transaction_account_id', $account->special_saving_account_id)
                    ->where('special_saving_transaction_deleted', 'N')
                    ->where('special_saving_transaction_reversed', 'N')
                    ->whereIn('special_saving_transaction_type', ['DEPOSIT', 'TRANSFER_IN'])
                    ->whereBetween('special_saving_transaction_date', [$lateDepositStart, $endDate])
                    ->sum('special_saving_transaction_principal_amount');

                $qualifying = max(0, $qualifying - (float) $lateDeposits);
            }
        } elseif ($method === 'DAILY_BALANCE') {
            $qualifying = $minimum;
        } else {
            $qualifying = $minimum;
        }

        return [
            'opening_balance' => round(max(0, $opening), 2),
            'closing_balance' => round(max(0, $closing), 2),
            'minimum_balance' => round(max(0, $minimum), 2),
            'qualifying_balance' => round(max(0, $qualifying), 2),
        ];
    }

    private function resolveMonthlyRate($product, float $amount): float
    {
        if ($product->special_saving_product_rate_mode !== 'TIERED') {
            return (float) $product->special_saving_product_monthly_interest_rate;
        }

        $tier = DB::table('sacco_special_saving_product_rate_tiers')
            ->where('special_saving_rate_tier_product_id', $product->special_saving_product_id)
            ->where('special_saving_rate_tier_deleted', 'N')
            ->where('special_saving_rate_tier_status', 'Active')
            ->where('special_saving_rate_tier_min_amount', '<=', $amount)
            ->where(function ($query) use ($amount) {
                $query->whereNull('special_saving_rate_tier_max_amount')
                    ->orWhere('special_saving_rate_tier_max_amount', '>=', $amount);
            })
            ->orderByDesc('special_saving_rate_tier_min_amount')
            ->first();

        if (!$tier) {
            return (float) $product->special_saving_product_monthly_interest_rate;
        }

        return (float) $tier->special_saving_rate_tier_monthly_rate;
    }

    private function buildVestingItems(string $processDate, ?int $productId = null)
    {
        return DB::table('sacco_special_saving_accounts as a')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'a.special_saving_account_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'a.special_saving_account_product_id')
            ->where('a.special_saving_account_deleted', 'N')
            ->where('a.special_saving_account_status', 'Active')
            ->where('a.special_saving_account_accrued_interest_balance', '>', 0)
            ->whereDate('a.special_saving_account_next_free_withdrawal_date', '<=', $processDate)
            ->when($productId, fn($q) => $q->where('a.special_saving_account_product_id', $productId))
            ->select('a.*', 'm.member_name', 'm.member_sacco_id', 'p.special_saving_product_name')
            ->get();
    }

    private function createSavingTransaction(array $payload): int
    {
        $balances = $payload['balances'];

        return DB::table('sacco_special_saving_transactions')->insertGetId([
            'special_saving_transaction_account_id' => $payload['account_id'],
            'special_saving_transaction_member_id' => $payload['member_id'],
            'special_saving_transaction_product_id' => $payload['product_id'],

            'special_saving_transaction_type' => $payload['type'],
            'special_saving_transaction_direction' => $payload['direction'],
            'special_saving_transaction_amount' => $this->money($payload['amount']),

            'special_saving_transaction_principal_amount' => $this->money($payload['principal_amount'] ?? 0),
            'special_saving_transaction_interest_amount' => $this->money($payload['interest_amount'] ?? 0),
            'special_saving_transaction_penalty_amount' => $this->money($payload['penalty_amount'] ?? 0),
            'special_saving_transaction_charge_amount' => $this->money($payload['charge_amount'] ?? 0),

            'special_saving_transaction_date' => $payload['date'],
            'special_saving_transaction_period' => $payload['period'],

            'special_saving_transaction_doc_no' => $payload['doc_no'],
            'special_saving_transaction_reference' => $payload['reference'] ?? null,
            'special_saving_transaction_source' => $payload['source'] ?? 'MANUAL',

            'special_saving_transaction_sub_account_id' => $payload['sub_account_id'] ?? null,
            'special_saving_transaction_ledger_posted' => 'N',
            'special_saving_transaction_ledger_ref' => null,

            'special_saving_transaction_principal_balance_after' => $balances['principal'],
            'special_saving_transaction_accrued_interest_after' => $balances['accrued_interest'],
            'special_saving_transaction_available_interest_after' => $balances['available_interest'],
            'special_saving_transaction_total_balance_after' => $balances['total'],

            'special_saving_transaction_reversed' => 'N',
            'special_saving_transaction_description' => $payload['description'] ?? null,

            'special_saving_transaction_by' => $this->userId(),
            'special_saving_transaction_ip' => $payload['request'] instanceof Request ? $payload['request']->ip() : 'SYSTEM',
            'special_saving_transaction_transdate' => now(),
            'special_saving_transaction_deleted' => 'N',
        ]);
    }

    private function applyAccountBalanceDelta(int $accountId, array $delta, Request $request): array
    {
        $account = DB::table('sacco_special_saving_accounts')
            ->where('special_saving_account_id', $accountId)
            ->lockForUpdate()
            ->first();

        if (!$account) {
            throw new \Exception('Special saving account not found.');
        }

        $principal = round((float) $account->special_saving_account_principal_balance + (float) ($delta['principal'] ?? 0), 2);
        $accrued = round((float) $account->special_saving_account_accrued_interest_balance + (float) ($delta['accrued_interest'] ?? 0), 2);
        $available = round((float) $account->special_saving_account_available_interest_balance + (float) ($delta['available_interest'] ?? 0), 2);
        $forfeited = round((float) $account->special_saving_account_forfeited_interest_balance + (float) ($delta['forfeited_interest'] ?? 0), 2);

        $principal = max(0, $principal);
        $accrued = max(0, $accrued);
        $available = max(0, $available);
        $forfeited = max(0, $forfeited);

        $total = round($principal + $accrued + $available, 2);

        DB::table('sacco_special_saving_accounts')
            ->where('special_saving_account_id', $accountId)
            ->update([
                'special_saving_account_principal_balance' => $principal,
                'special_saving_account_accrued_interest_balance' => $accrued,
                'special_saving_account_available_interest_balance' => $available,
                'special_saving_account_forfeited_interest_balance' => $forfeited,
                'special_saving_account_total_balance' => $total,
                'special_saving_account_by' => $this->userId(),
                'special_saving_account_ip' => $request->ip(),
                'special_saving_account_transdate' => now(),
            ]);

        return [
            'principal' => $principal,
            'accrued_interest' => $accrued,
            'available_interest' => $available,
            'forfeited_interest' => $forfeited,
            'total' => $total,
        ];
    }

    private function reverseTransactionEffect($transaction, Request $request): void
    {
        $type = $transaction->special_saving_transaction_type;

        $delta = [
            'principal' => 0,
            'accrued_interest' => 0,
            'available_interest' => 0,
            'forfeited_interest' => 0,
        ];

        if ($type === 'DEPOSIT' || $type === 'TRANSFER_IN') {
            $delta['principal'] = -1 * (float) $transaction->special_saving_transaction_principal_amount;
        }

        if ($type === 'WITHDRAWAL') {
            $delta['principal'] = (float) $transaction->special_saving_transaction_principal_amount;
            $delta['available_interest'] = (float) $transaction->special_saving_transaction_interest_amount;
        }

        if ($type === 'INTEREST_ACCRUAL') {
            $delta['accrued_interest'] = -1 * (float) $transaction->special_saving_transaction_interest_amount;
        }

        if ($type === 'INTEREST_VESTING') {
            $delta['accrued_interest'] = (float) $transaction->special_saving_transaction_interest_amount;
            $delta['available_interest'] = -1 * (float) $transaction->special_saving_transaction_interest_amount;
        }

        if ($type === 'INTEREST_FORFEITURE') {
            $delta['accrued_interest'] = (float) $transaction->special_saving_transaction_interest_amount;
            $delta['forfeited_interest'] = -1 * (float) $transaction->special_saving_transaction_interest_amount;
        }

        $this->applyAccountBalanceDelta($transaction->special_saving_transaction_account_id, $delta, $request);
    }

    private function getProduct($id)
    {
        $product = DB::table('sacco_special_saving_products')
            ->where('special_saving_product_id', $id)
            ->where('special_saving_product_deleted', 'N')
            ->first();

        abort_if(!$product, 404);

        return $product;
    }

    private function getAccount($id)
    {
        $account = DB::table('sacco_special_saving_accounts')
            ->where('special_saving_account_id', $id)
            ->where('special_saving_account_deleted', 'N')
            ->first();

        abort_if(!$account, 404);

        return $account;
    }

    private function accountWithDetails($id)
    {
        $record = DB::table('sacco_special_saving_accounts as a')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'a.special_saving_account_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'a.special_saving_account_product_id')
            ->where('a.special_saving_account_id', $id)
            ->where('a.special_saving_account_deleted', 'N')
            ->select('a.*', 'm.member_name', 'm.member_sacco_id', 'm.member_national_id', 'p.special_saving_product_name', 'p.special_saving_product_code')
            ->first();

        abort_if(!$record, 404);

        return $record;
    }

    private function withdrawalWithDetails($id)
    {
        $record = DB::table('sacco_special_saving_withdrawal_requests as w')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'w.special_saving_withdrawal_member_id')
            ->leftJoin('sacco_special_saving_products as p', 'p.special_saving_product_id', '=', 'w.special_saving_withdrawal_product_id')
            ->leftJoin('sacco_special_saving_accounts as a', 'a.special_saving_account_id', '=', 'w.special_saving_withdrawal_account_id')
            ->where('w.special_saving_withdrawal_id', $id)
            ->where('w.special_saving_withdrawal_deleted', 'N')
            ->select('w.*', 'm.member_name', 'm.member_sacco_id', 'm.member_national_id', 'p.special_saving_product_name', 'a.special_saving_account_number')
            ->first();

        abort_if(!$record, 404);

        return $record;
    }

    private function changeAccountStatus(Request $request, int $id, string $status)
    {
        $account = $this->getAccount($id);

        DB::table('sacco_special_saving_accounts')
            ->where('special_saving_account_id', $account->special_saving_account_id)
            ->update([
                'special_saving_account_status' => $status,
                'special_saving_account_by' => $this->userId(),
                'special_saving_account_ip' => $request->ip(),
                'special_saving_account_transdate' => now(),
            ]);

        return back()->with('success', "Account status changed to {$status}.");
    }

    private function activeProducts()
    {
        return DB::table('sacco_special_saving_products')
            ->where('special_saving_product_deleted', 'N')
            ->where('special_saving_product_status', 'Active')
            ->orderBy('special_saving_product_name')
            ->get();
    }

    private function subAccountsList()
    {
        return DB::table('sacco_sub_account')
            ->where('sub_account_deleted', 'N')
            ->orderBy('sub_account_name')
            ->get();
    }

    private function generateAccountNumber(int $accountId, string $productCode): string
    {
        return strtoupper($productCode) . '-' . str_pad((string) $accountId, 6, '0', STR_PAD_LEFT);
    }

    private function generateDocNo(string $prefix): string
    {
        return $prefix . '-' . now()->format('YmdHis') . '-' . random_int(100, 999);
    }

    private function periodStartDate(string $period): string
    {
        return Carbon::createFromFormat('Ym', $period)->startOfMonth()->toDateString();
    }

    private function periodEndDate(string $period): string
    {
        return Carbon::createFromFormat('Ym', $period)->endOfMonth()->toDateString();
    }

    private function money($value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return round((float) $value, 2);
    }

    private function userId()
    {
        return Auth::id();
    }

    private function csvResponse(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    
}
