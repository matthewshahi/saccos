@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="m-0">Monthly Contributions for Active Loans</h5>
            <small class="text-muted">
                Min balance threshold: {{ number_format($minThreshold, 2) }}
                @if($loanCalcMethod)
                    <span class="ms-3 badge bg-info text-dark text-uppercase">
                        {{ $loanCalcMethod }}
                    </span>
                @endif
            </small>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('reports.loans.active.index') }}" class="row g-3 align-items-end">

    {{-- 🔍 Smart Search --}}
    <div class="col-md-3">
        <label class="form-label fw-semibold">Smart Search</label>
        <input type="text" name="q" value="{{ $term }}" class="form-control"
               placeholder="Name, phone, company, doc no, category ...">
    </div>

    {{-- 💼 Loan Type --}}
    <div class="col-md-3">
        <label class="form-label fw-semibold">Loan Type</label>
        <select name="loan_type" class="form-select">
            <option value="">All</option>
            @foreach($loanTypes as $t)
                <option value="{{ $t->loan_type_id }}" @selected($loanType == $t->loan_type_id)>
                    {{ $t->loan_type_name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- 🏢 Company --}}
    <div class="col-md-3">
        <label class="form-label fw-semibold">Company</label>
        <select name="company_id" class="form-select">
            <option value="">All</option>
            @foreach($companies as $c)
                <option value="{{ $c->company_id }}" @selected($companyId == $c->company_id)>
                    {{ $c->company_name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- 📄 Per Page --}}
    <div class="col-md-2">
        <label class="form-label fw-semibold">Per Page</label>
        <select name="per_page" class="form-select">
            @foreach([25,50,100,150,200] as $pp)
                <option value="{{ $pp }}" @selected($perPage == $pp)>{{ $pp }}</option>
            @endforeach
        </select>
    </div>

    {{-- ⚙️ Actions --}}
    <div class="col-md-4 d-flex flex-wrap gap-2 mt-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-search"></i> Go
        </button>

        <a href="{{ route('reports.loans.active.export', ['format' => 'xlsx'] + request()->query()) }}"
           class="btn btn-success" title="Export to Excel">
            <i class="bi bi-file-earmark-excel"></i> Excel
        </a>

       
    </div>
</form>
        </div>

        <div class="card-footer">
            <div class="row text-center small">
                <div class="col-md-2"><strong>Rows:</strong> {{ number_format($totals['count_rows'] ?? 0) }}</div>
                <div class="col-md-2"><strong>Total Monthly Principal:</strong> {{ number_format($totals['sum_monthly_principal'] ?? 0, 2) }}</div>
                <div class="col-md-2"><strong>Total Monthly Amount:</strong> {{ number_format($totals['sum_monthly_amount'] ?? 0, 2) }}</div>
                <div class="col-md-3"><strong>Total Loan Amount:</strong> {{ number_format($totals['sum_loan_amount'] ?? 0, 2) }}</div>
                <div class="col-md-3"><strong>Total Current Balance:</strong> {{ number_format($totals['sum_current_balance'] ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- ===== Loans Table ===== --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th></th>
                        <th>Loan#</th>
                        <th>Member</th>
                        <th>Company</th>
                        <th>Type</th>
                        <th class="text-end">Loan Amount</th>
                        <th class="text-end">Balance</th>
                        @if($loanCalcMethod === 'principle')
                            <th class="text-end">Monthly Principal</th>
                        @endif
                        <th class="text-end">Expected Interest</th>
                        <th class="text-end">Monthly Amount</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($records as $r)
                       @php
    $interestMethod = strtoupper(trim($r->interest_method ?? 'FIXED INTEREST'));
    $annualRate = (float)($r->annual_rate ?? 0);
    $balance = (float)($r->current_balance ?? 0);
    $principal = (float)($r->loan_monthly_repayment_principal ?? 0);

    // 💡 calculate expected interest per loan type
    if ($interestMethod === 'REDUCING BALANCE' || $interestMethod === 'REDUCING') {
        $expectedInterest = round($balance * ($annualRate / 12 / 100), 2);
    } else {
        // fixed or flat interest
        $expectedInterest = round($principal * ($annualRate / 100), 2);
    }
@endphp

                        <tr id="row-{{ $r->loan_id }}">
                            <td>{{ $loop->iteration + ($records->firstItem() - 1) }}</td>
                            <td>{{ $r->loan_id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $r->member_name }}</div>
                                <div class="small text-muted">{{ $r->member_phone_no }} · {{ $r->member_sacco_id }}</div>
                            </td>
                            <td>{{ $r->company_name }}</td>
                            <td>{{ $r->loan_type_name }}</td>
                            <td class="text-end">{{ number_format($r->loan_amount, 2) }}</td>
                            <td class="text-end">{{ number_format($r->current_balance, 2) }}</td>

                            {{-- Monthly Principal --}}
                            @if($loanCalcMethod === 'principle')
                            <td class="text-end" style="min-width:160px">
                                @if($canEdit)
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0"
                                            class="form-control text-end js-principal"
                                            value="{{ number_format($r->loan_monthly_repayment_principal ?? 0, 2, '.', '') }}"
                                            data-loan="{{ $r->loan_id }}"
                                            data-interest-method="{{ $interestMethod }}"
                                            data-annual-rate="{{ $annualRate }}"
                                            data-balance="{{ $r->current_balance }}">
                                        <button type="button"
                                            class="btn btn-outline-primary js-save-principal"
                                            data-endpoint="{{ route('reports.loans.active.updatePrincipal', ['loan' => $r->loan_id]) }}">
                                            Save
                                        </button>
                                    </div>
                                @else
                                    {{ number_format($r->loan_monthly_repayment_principal ?? 0, 2) }}
                                @endif
                            </td>
                            @endif

                            {{-- Expected Interest --}}
                            <td class="text-end text-info fw-semibold">
                                {{ number_format($expectedInterest, 2) }}
                                
                            </td>

                            {{-- Monthly Amount --}}
                            <td class="text-end" style="min-width:160px">
                                @if($canEdit)
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0"
                                            class="form-control text-end js-amount"
                                            value="{{ number_format($r->loan_monthly_repayment_amount ?? 0, 2, '.', '') }}"
                                            data-loan="{{ $r->loan_id }}">
                                        <button type="button"
                                            class="btn btn-outline-primary js-save-amount"
                                            data-endpoint="{{ route('reports.loans.active.updateAmount', ['loan' => $r->loan_id]) }}">
                                            Save
                                        </button>
                                    </div>
                                @else
                                    {{ number_format($r->loan_monthly_repayment_amount ?? 0, 2) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted p-4">No active loans found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="card-footer">
            <div class="row align-items-center">
                <div class="col-md-6 text-muted small mb-2 mb-md-0">
                    Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ $records->total() }} results
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end justify-content-center">
                        {{ $records->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log("✅ Loans JS fully loaded and DOM ready");

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const fmt = n => (Math.round((+n || 0) * 100) / 100).toFixed(2);

    // 🔹 Auto recalc interest & monthly amount in real-time
    document.querySelectorAll('.js-principal').forEach(inp => {
        inp.addEventListener('input', e => {
            const row = e.target.closest('tr');
            const balance = parseFloat(inp.dataset.balance || 0);
            const annualRate = parseFloat(inp.dataset.annualRate || 0);
            const method = (inp.dataset.interestMethod || '').toUpperCase().trim();
            const principal = parseFloat(inp.value || 0);

            let interest = 0;

            if (method === 'REDUCING BALANCE' || method === 'REDUCING') {
                // Monthly interest on remaining balance
                interest = balance * (annualRate / 12 / 100);
            } else {
                // Fixed/flat interest directly on principal
                interest = principal * (annualRate / 100);
            }

            interest = Math.round(interest * 100) / 100;
            const total = Math.round((principal + interest) * 100) / 100;

            // 🔹 Update the displayed interest
            const interestCell = row.querySelector('.text-info');
            if (interestCell) {
                interestCell.textContent = interest.toFixed(2);
                interestCell.classList.add('text-success');
                setTimeout(() => interestCell.classList.remove('text-success'), 1000);
            }

            // 🔹 Update the monthly amount field
            const amtInput = row.querySelector('.js-amount');
            if (amtInput && !isNaN(total)) amtInput.value = total.toFixed(2);
        });
    });

    // 💾 Save Monthly Principal
    document.querySelectorAll('.js-save-principal').forEach(btn => {
        btn.addEventListener('click', async () => {
            console.log("🔥 Save principal clicked for loan:", btn.dataset.loan);
            const row = btn.closest('tr');
            const input = row.querySelector('.js-principal');
            const principal = parseFloat(input.value || 0);

            btn.disabled = true;
            const oldText = btn.textContent;
            btn.textContent = 'Saving...';

            try {
                const res = await fetch(btn.dataset.endpoint, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ principal })
                });

                const data = await res.json();
                console.log("Response:", data);

                if (data.ok) {
                    input.classList.add('is-valid');
                    setTimeout(() => input.classList.remove('is-valid'), 1000);
                } else {
                    alert(data.message || 'Update failed.');
                }
            } catch (err) {
                console.error('❌ Network/Server error:', err);
                alert('Network or server error. Check console.');
            }

            btn.disabled = false;
            btn.textContent = oldText;
        });
    });

    // 💾 Save Monthly Amount
    document.querySelectorAll('.js-save-amount').forEach(btn => {
        btn.addEventListener('click', async () => {
            console.log("🔥 Save amount clicked for loan:", btn.dataset.loan);
            const row = btn.closest('tr');
            const input = row.querySelector('.js-amount');
            const amount = parseFloat(input.value || 0);

            btn.disabled = true;
            const oldText = btn.textContent;
            btn.textContent = 'Saving...';

            try {
                const res = await fetch(btn.dataset.endpoint, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ amount })
                });

                const data = await res.json();
                console.log("Response:", data);

                if (data.ok) {
                    input.classList.add('is-valid');
                    setTimeout(() => input.classList.remove('is-valid'), 1000);
                } else {
                    alert(data.message || 'Update failed.');
                }
            } catch (err) {
                console.error('❌ Network/Server error:', err);
                alert('Network or server error. Check console.');
            }

            btn.disabled = false;
            btn.textContent = oldText;
        });
    });
});
</script>

@endsection