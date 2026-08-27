<?php

/*
| Add these rules to BOTH storeLoanType() and updateLoanType().
*/
$rules = [
    // ...existing rules...
    'loan_type_grace_days_after_due' => 'required|integer|min:0|max:3650',
    'loan_type_default_interest' => 'nullable|numeric|min:0',
];

/*
| Add these values to BOTH the INSERT and UPDATE data arrays.
*/
$data['loan_type_grace_days_after_due'] = (int) $request->input(
    'loan_type_grace_days_after_due',
    45
);

$data['loan_type_default_interest'] = $request->filled('loan_type_default_interest')
    ? (float) $request->input('loan_type_default_interest')
    : null;
