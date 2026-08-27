<div class="col-md-3 form-group mb-3">
    <label for="loan_type_grace_days_after_due">
        Grace Days After Due
        <span class="text-danger">*</span>
    </label>

    <input
        type="number"
        min="0"
        step="1"
        class="form-control @error('loan_type_grace_days_after_due') is-invalid @enderror"
        id="loan_type_grace_days_after_due"
        name="loan_type_grace_days_after_due"
        value="{{ old('loan_type_grace_days_after_due', $loanType->loan_type_grace_days_after_due ?? 45) }}"
        required
    >

    @error('loan_type_grace_days_after_due')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    <small class="form-text text-muted">
        Calendar days allowed after the contractual due date before an unpaid obligation is in default. Use 0 for no grace period.
    </small>
</div>

<div class="col-md-3 form-group mb-3">
    <label for="loan_type_default_interest">
        Default Interest Rate (%)
    </label>

    <input
        type="number"
        min="0"
        step="0.01"
        class="form-control @error('loan_type_default_interest') is-invalid @enderror"
        id="loan_type_default_interest"
        name="loan_type_default_interest"
        value="{{ old('loan_type_default_interest', $loanType->loan_type_default_interest ?? '') }}"
        placeholder="Blank = normal interest rate"
    >

    @error('loan_type_default_interest')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    <small class="form-text text-muted">
        Interest rate used after default. Leave blank to use the normal loan interest rate.
    </small>
</div>
