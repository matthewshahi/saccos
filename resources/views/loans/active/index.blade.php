@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="m-0">Active Loans Report</h5>
            <small class="text-muted">Min balance threshold: {{ number_format($minThreshold,2) }}</small>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('reports.loans.active.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Smart Search</label>
                    <input type="text" name="q" value="{{ $term }}" class="form-control" placeholder="Name, phone, company, doc no, category ...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Loan Type</label>
                    <select name="loan_type" class="form-select">
                        <option value="">All</option>
                        @foreach($loanTypes as $t)
                            <option value="{{ $t->type_id }}" @selected($loanType==$t->type_id)>{{ $t->type_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select">
                        <option value="">All</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->company_id }}" @selected($companyId==$c->company_id)>{{ $c->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Per Page</label>
                    <select name="per_page" class="form-select">
                        @foreach([25,50,100,150,200] as $pp)
                            <option value="{{ $pp }}" @selected($perPage==$pp)>{{ $pp }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100">Go</button>
                </div>
            </form>
        </div>

        <div class="card-footer">
            <div class="row text-center">
                <div class="col-md-2"><strong>Rows:</strong> {{ number_format($totals['count_rows'] ?? 0) }}</div>
                <div class="col-md-2"><strong>Total Monthly Principal:</strong> {{ number_format($totals['sum_monthly_principal'] ?? 0, 2) }}</div>
                <div class="col-md-2"><strong>Total Monthly Amount:</strong> {{ number_format($totals['sum_monthly_amount'] ?? 0, 2) }}</div>
                <div class="col-md-3"><strong>Total Loan Amount:</strong> {{ number_format($totals['sum_loan_amount'] ?? 0, 2) }}</div>
                <div class="col-md-3"><strong>Total Current Balance:</strong> {{ number_format($totals['sum_current_balance'] ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Loan#</th>
                        <th>Member</th>
                        <th>Company</th>
                        <th>Type</th>
                        <th class="text-end">Loan Amount</th>
                        <th class="text-end">Balance</th>
                        <th class="text-end">Monthly Principal (Editable)</th>
                        <th class="text-end">Monthly Amount (Editable)</th>
                        <th>Interest Method</th>
                        <th>Doc/Desc</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $r)
                        @php
                            $interestMethod = $r->interest_method ?? 'flat';
                            $monthlyRate = (float)($r->monthly_rate ?? 0);
                            $flatInterest = 0.0;
                            if ($interestMethod !== 'reducing') {
                                $months = max(1, (int)$r->loan_payment_period);
                                $flatInterest = round(((float)$r->loan_interest_payable) / $months, 2);
                            }
                        @endphp
                        <tr id="row-{{ $r->loan_id }}">
                            <td>{{ $r->loan_id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $r->member_name }}</div>
                                <div class="small text-muted">{{ $r->member_phone_no }} · {{ $r->member_sacco_id }}</div>
                            </td>
                            <td>{{ $r->company_name }}</td>
                            <td>{{ $r->loan_type_name }}</td>
                            <td class="text-end">{{ number_format($r->loan_amount,2) }}</td>
                            <td class="text-end">{{ number_format($r->current_balance,2) }}</td>

                            <td class="text-end" style="min-width:160px">
                                @can('LoansReportEdit')
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0" class="form-control text-end js-principal"
                                           value="{{ number_format($r->loan_monthly_repayment_principal ?? 0, 2, '.', '') }}"
                                           data-loan="{{ $r->loan_id }}"
                                           data-interest-method="{{ $interestMethod }}"
                                           data-monthly-rate="{{ $monthlyRate }}"
                                           data-balance="{{ $r->current_balance }}"
                                           data-flat-interest="{{ $flatInterest }}">
                                    <button class="btn btn-outline-primary js-save-principal" data-loan="{{ $r->loan_id }}">Save</button>
                                </div>
                                @else
                                    {{ number_format($r->loan_monthly_repayment_principal ?? 0, 2) }}
                                @endcan
                            </td>

                            <td class="text-end" style="min-width:160px">
                                @can('LoansReportEdit')
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0" class="form-control text-end js-amount"
                                           value="{{ number_format($r->loan_monthly_repayment_amount ?? 0, 2, '.', '') }}"
                                           data-loan="{{ $r->loan_id }}">
                                    <button class="btn btn-outline-primary js-save-amount" data-loan="{{ $r->loan_id }}">Save</button>
                                </div>
                                @else
                                    {{ number_format($r->loan_monthly_repayment_amount ?? 0, 2) }}
                                @endcan
                            </td>

                            <td>{{ strtoupper($interestMethod) }}@if($monthlyRate>0) ({{ $monthlyRate*100 }}%)@endif</td>
                            <td>
                                <div class="small">{{ $r->loan_doc_no }}</div>
                                <div class="text-muted small">{{ $r->loan_description }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted p-4">No active loans found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center">
            <div class="text-muted">Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ $records->total() }}</div>
            {{ $records->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const fmt = n => (Math.round(n * 100) / 100).toFixed(2);

    // Auto recompute total amount when principal changes
    document.querySelectorAll('.js-principal').forEach(inp => {
        inp.addEventListener('input', e => {
            const row = e.target.closest('tr');
            const amtInput = row.querySelector('.js-amount');
            const principal = parseFloat(e.target.value || 0);
            const method = e.target.dataset.interestMethod;
            const rate = parseFloat(e.target.dataset.monthlyRate || 0);
            const balance = parseFloat(e.target.dataset.balance || 0);
            const flatInterest = parseFloat(e.target.dataset.flatInterest || 0);
            let interest = 0;

            if (method === 'reducing') {
                interest = balance * rate;
            } else {
                interest = flatInterest;
            }
            if (amtInput) amtInput.value = fmt(principal + interest);
        });
    });

    async function patch(url, data) {
        const res = await fetch(url, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        return res.json();
    }

    document.querySelectorAll('.js-save-principal').forEach(btn => {
        btn.addEventListener('click', async () => {
            const loanId = btn.dataset.loan;
            const row = btn.closest('tr');
            const principalInput = row.querySelector('.js-principal');
            const amountInput = row.querySelector('.js-amount');
            const principal = parseFloat(principalInput.value || 0);
            btn.disabled = true;

            const data = await patch(`{{ url('/reports/loans/active') }}/${loanId}/principal`, { principal });
            btn.disabled = false;

            if (data.ok) {
                amountInput.value = fmt(data.amount);
                principalInput.classList.add('is-valid');
                setTimeout(() => principalInput.classList.remove('is-valid'), 800);
            } else {
                alert(data.message || 'Update failed');
            }
        });
    });

    document.querySelectorAll('.js-save-amount').forEach(btn => {
        btn.addEventListener('click', async () => {
            const loanId = btn.dataset.loan;
            const row = btn.closest('tr');
            const amountInput = row.querySelector('.js-amount');
            const amount = parseFloat(amountInput.value || 0);
            btn.disabled = true;

            const data = await patch(`{{ url('/reports/loans/active') }}/${loanId}/amount`, { amount });
            btn.disabled = false;

            if (data.ok) {
                amountInput.classList.add('is-valid');
                setTimeout(() => amountInput.classList.remove('is-valid'), 800);
            } else {
                alert(data.message || 'Update failed');
            }
        });
    });
})();
</script>
@endpush