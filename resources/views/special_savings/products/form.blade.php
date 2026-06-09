@php
    $r = $record ?? null;
@endphp

<div class="row">
    <div class="col-md-6 form-group mb-3">
        <label>Category</label>
        <select name="special_saving_product_category_id" class="form-control" required>
            <option value="">Select category</option>
            @foreach($categories as $category)
                <option value="{{ $category->special_saving_category_id }}"
                    {{ old('special_saving_product_category_id', $r->special_saving_product_category_id ?? '') == $category->special_saving_category_id ? 'selected' : '' }}>
                    {{ $category->special_saving_category_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 form-group mb-3">
        <label>Product Name</label>
        <input type="text" name="special_saving_product_name" value="{{ old('special_saving_product_name', $r->special_saving_product_name ?? '') }}" class="form-control" required>
    </div>

    <div class="col-md-6 form-group mb-3">
        <label>Product Code</label>
        <input type="text" name="special_saving_product_code" value="{{ old('special_saving_product_code', $r->special_saving_product_code ?? '') }}" class="form-control" required>
    </div>

    <div class="col-md-6 form-group mb-3">
        <label>Rate Mode</label>
        <select name="special_saving_product_rate_mode" class="form-control" required>
            @foreach(['FLAT' => 'Flat Rate', 'TIERED' => 'Tiered Amount-Based Rate'] as $key => $label)
                <option value="{{ $key }}" {{ old('special_saving_product_rate_mode', $r->special_saving_product_rate_mode ?? 'FLAT') == $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Annual Interest Rate (%)</label>
        <input type="number" step="0.000001" name="special_saving_product_annual_interest_rate" value="{{ old('special_saving_product_annual_interest_rate', $r->special_saving_product_annual_interest_rate ?? 12.5) }}" class="form-control" required>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Interest Method</label>
        <select name="special_saving_product_interest_method" class="form-control" required>
            @foreach(['MINIMUM_MONTHLY_BALANCE', 'CLOSING_BALANCE', 'DAILY_BALANCE'] as $method)
                <option value="{{ $method }}" {{ old('special_saving_product_interest_method', $r->special_saving_product_interest_method ?? 'MINIMUM_MONTHLY_BALANCE') == $method ? 'selected' : '' }}>{{ $method }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Posting Frequency</label>
        <select name="special_saving_product_interest_posting_frequency" class="form-control" required>
            @foreach(['MONTHLY', 'DAILY', 'WEEKLY'] as $freq)
                <option value="{{ $freq }}" {{ old('special_saving_product_interest_posting_frequency', $r->special_saving_product_interest_posting_frequency ?? 'MONTHLY') == $freq ? 'selected' : '' }}>{{ $freq }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Require Full Month</label>
        <select name="special_saving_product_require_full_month" class="form-control">
            <option value="Y" {{ old('special_saving_product_require_full_month', $r->special_saving_product_require_full_month ?? 'Y') == 'Y' ? 'selected' : '' }}>Yes</option>
            <option value="N" {{ old('special_saving_product_require_full_month', $r->special_saving_product_require_full_month ?? 'Y') == 'N' ? 'selected' : '' }}>No</option>
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Minimum Days in Product</label>
        <input type="number" name="special_saving_product_member_minimum_days" value="{{ old('special_saving_product_member_minimum_days', $r->special_saving_product_member_minimum_days ?? 30) }}" class="form-control">
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Deposit Minimum Days</label>
        <input type="number" name="special_saving_product_deposit_minimum_days" value="{{ old('special_saving_product_deposit_minimum_days', $r->special_saving_product_deposit_minimum_days ?? 10) }}" class="form-control">
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Withdrawal Cycle Months</label>
        <input type="number" name="special_saving_product_withdrawal_cycle_months" value="{{ old('special_saving_product_withdrawal_cycle_months', $r->special_saving_product_withdrawal_cycle_months ?? 6) }}" class="form-control">
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Allow Early Withdrawal</label>
        <select name="special_saving_product_early_withdrawal_allowed" class="form-control">
            <option value="Y" {{ old('special_saving_product_early_withdrawal_allowed', $r->special_saving_product_early_withdrawal_allowed ?? 'Y') == 'Y' ? 'selected' : '' }}>Yes</option>
            <option value="N" {{ old('special_saving_product_early_withdrawal_allowed', $r->special_saving_product_early_withdrawal_allowed ?? 'Y') == 'N' ? 'selected' : '' }}>No</option>
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Early Withdrawal Interest Policy</label>
        <select name="special_saving_product_early_withdrawal_interest_policy" class="form-control">
            <option value="FORFEIT_UNVESTED_INTEREST" {{ old('special_saving_product_early_withdrawal_interest_policy', $r->special_saving_product_early_withdrawal_interest_policy ?? 'FORFEIT_UNVESTED_INTEREST') == 'FORFEIT_UNVESTED_INTEREST' ? 'selected' : '' }}>Forfeit Unvested Interest</option>
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Interest Vesting Policy</label>
        <select name="special_saving_product_interest_vesting_policy" class="form-control">
            <option value="AFTER_WITHDRAWAL_CYCLE" {{ old('special_saving_product_interest_vesting_policy', $r->special_saving_product_interest_vesting_policy ?? 'AFTER_WITHDRAWAL_CYCLE') == 'AFTER_WITHDRAWAL_CYCLE' ? 'selected' : '' }}>After Withdrawal Cycle</option>
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Interest Credit Policy</label>
        <select name="special_saving_product_interest_credit_policy" class="form-control">
            <option value="KEEP_SEPARATE" {{ old('special_saving_product_interest_credit_policy', $r->special_saving_product_interest_credit_policy ?? 'KEEP_SEPARATE') == 'KEEP_SEPARATE' ? 'selected' : '' }}>Keep Separate</option>
            <option value="ADD_TO_PRINCIPAL" {{ old('special_saving_product_interest_credit_policy', $r->special_saving_product_interest_credit_policy ?? 'KEEP_SEPARATE') == 'ADD_TO_PRINCIPAL' ? 'selected' : '' }}>Add to Principal</option>
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Minimum Opening Amount</label>
        <input type="number" step="0.01" name="special_saving_product_minimum_opening_amount" value="{{ old('special_saving_product_minimum_opening_amount', $r->special_saving_product_minimum_opening_amount ?? 0) }}" class="form-control">
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Minimum Monthly Contribution</label>
        <input type="number" step="0.01" name="special_saving_product_minimum_monthly_contribution" value="{{ old('special_saving_product_minimum_monthly_contribution', $r->special_saving_product_minimum_monthly_contribution ?? 0) }}" class="form-control">
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Minimum Balance</label>
        <input type="number" step="0.01" name="special_saving_product_minimum_balance" value="{{ old('special_saving_product_minimum_balance', $r->special_saving_product_minimum_balance ?? 0) }}" class="form-control">
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Can Secure Loan</label>
        <select name="special_saving_product_can_secure_loan" class="form-control">
            <option value="N" {{ old('special_saving_product_can_secure_loan', $r->special_saving_product_can_secure_loan ?? 'N') == 'N' ? 'selected' : '' }}>No</option>
            <option value="Y" {{ old('special_saving_product_can_secure_loan', $r->special_saving_product_can_secure_loan ?? 'N') == 'Y' ? 'selected' : '' }}>Yes</option>
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Allow Member Transfer</label>
        <select name="special_saving_product_allow_member_transfer" class="form-control">
            <option value="Y" {{ old('special_saving_product_allow_member_transfer', $r->special_saving_product_allow_member_transfer ?? 'Y') == 'Y' ? 'selected' : '' }}>Yes</option>
            <option value="N" {{ old('special_saving_product_allow_member_transfer', $r->special_saving_product_allow_member_transfer ?? 'Y') == 'N' ? 'selected' : '' }}>No</option>
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Allow M-Pesa Collection</label>
        <select name="special_saving_product_allow_mpesa_collection" class="form-control">
            <option value="Y" {{ old('special_saving_product_allow_mpesa_collection', $r->special_saving_product_allow_mpesa_collection ?? 'Y') == 'Y' ? 'selected' : '' }}>Yes</option>
            <option value="N" {{ old('special_saving_product_allow_mpesa_collection', $r->special_saving_product_allow_mpesa_collection ?? 'Y') == 'N' ? 'selected' : '' }}>No</option>
        </select>
    </div>

    <div class="col-md-4 form-group mb-3">
        <label>Status</label>
        <select name="special_saving_product_status" class="form-control">
            <option value="Active" {{ old('special_saving_product_status', $r->special_saving_product_status ?? 'Active') == 'Active' ? 'selected' : '' }}>Active</option>
            <option value="Inactive" {{ old('special_saving_product_status', $r->special_saving_product_status ?? 'Active') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>

    <div class="col-md-12 form-group mb-3">
        <label>Description</label>
        <textarea name="special_saving_product_description" class="form-control" rows="2">{{ old('special_saving_product_description', $r->special_saving_product_description ?? '') }}</textarea>
    </div>

    <div class="col-md-12">
        <button class="btn btn-primary">{{ $r ? 'Update Product' : 'Save Product' }}</button>
        <a href="{{ route('special_savings.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>