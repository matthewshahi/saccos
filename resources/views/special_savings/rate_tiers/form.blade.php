@php
    $r = $record ?? null;
@endphp

<div class="row">
    <div class="col-md-6 form-group mb-3">
        <label>Minimum Amount</label>
        <input type="number" step="0.01" name="special_saving_rate_tier_min_amount" value="{{ old('special_saving_rate_tier_min_amount', $r->special_saving_rate_tier_min_amount ?? 0) }}" class="form-control" required>
    </div>

    <div class="col-md-6 form-group mb-3">
        <label>Maximum Amount</label>
        <input type="number" step="0.01" name="special_saving_rate_tier_max_amount" value="{{ old('special_saving_rate_tier_max_amount', $r->special_saving_rate_tier_max_amount ?? '') }}" class="form-control">
    </div>

    <div class="col-md-6 form-group mb-3">
        <label>Annual Rate (%)</label>
        <input type="number" step="0.000001" name="special_saving_rate_tier_annual_rate" value="{{ old('special_saving_rate_tier_annual_rate', $r->special_saving_rate_tier_annual_rate ?? 0) }}" class="form-control" required>
    </div>

    <div class="col-md-6 form-group mb-3">
        <label>Status</label>
        <select name="special_saving_rate_tier_status" class="form-control">
            <option value="Active" {{ old('special_saving_rate_tier_status', $r->special_saving_rate_tier_status ?? 'Active') == 'Active' ? 'selected' : '' }}>Active</option>
            <option value="Inactive" {{ old('special_saving_rate_tier_status', $r->special_saving_rate_tier_status ?? 'Active') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>

    <div class="col-md-12">
        <button class="btn btn-primary">{{ $r ? 'Update Tier' : 'Save Tier' }}</button>
        <a href="{{ route('special_savings.rate_tiers.index', $product->special_saving_product_id) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>