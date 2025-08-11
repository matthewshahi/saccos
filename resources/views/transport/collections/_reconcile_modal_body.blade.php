@if($loans->isEmpty())
    <div class="alert alert-warning">
        This member currently has no outstanding loans to reconcile this repayment against.
    </div>
@else
    <p><strong>Member:</strong> {{ $member->member_name ?? 'Unknown' }}</p>
    <p><strong>Collection Amount:</strong> KES {{ number_format($collection->coll_amount, 2) }}</p>

    <form action="{{ route('collections.attachLoan', $collection->id) }}" method="POST">
        @csrf

        <div class="mb-3">
            <label for="loan_id" class="form-label">Select Loan to Attach Payment</label>
            <select name="loan_id" class="form-select" required>
                @foreach($loans as $loan)
                    <option value="{{ $loan->loan_id }}">
                        Loan #{{ $loan->loan_id }} — {{ $loan->loan_type_name }} 
                        (Balance: KES {{ number_format(($loan->loan_amount - ($loan->loan_loan_paid ?? 0)), 2) }})
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Attach Payment</button>
    </form>
@endif