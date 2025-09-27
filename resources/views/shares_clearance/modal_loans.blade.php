<div>
  <h6>
    Loans for {{ $member->member_name }} ({{ $member->member_sacco_id }})
  </h6>
  <p class="text-muted mb-2">
    Phone: <b>{{ $member->member_phone_no }}</b> |
    National ID: <b>{{ $member->member_national_id }}</b>
  </p>
  <p class="text-muted mb-3">
    Shares Available: <b>{{ number_format($member->member_total_share, 2) }}</b>
  </p>

  @if($loans->isEmpty())
    <p class="text-muted">No outstanding loans found.</p>
  @else
    <form method="POST" action="{{ route('shares.clearance.process') }}">
      @csrf
      <div class="mb-3">
  <label for="period" class="form-label">Period (YYYYmm)</label>
  <input type="text" name="period" id="period" 
         class="form-control" 
         value="{{ $activePeriod ?? '' }}" 
         maxlength="6" pattern="\d{6}" required>
  <small class="text-muted">Enter in format YYYYmm (e.g., 202509)</small>
</div>
      <input type="hidden" name="member_id" value="{{ $member->member_id }}">

       
      

      <!-- Interest toggle -->
      <div class="form-check mb-3">
        <input type="checkbox" name="use_interest" value="1"
               class="form-check-input" id="useInterest" checked>
        <label for="useInterest" class="form-check-label">
          Include Interest in Clearance
        </label>
      </div>

      <!-- Loans Table -->
      <table class="table table-bordered table-sm">
        <thead>
          <tr>
            <th>Select</th>
            <th>Doc. No.</th>
            <th>Loan Type</th>
            <th class="text-end">Period (Months)</th>
            <th class="text-end">Amount Taken</th>
            <th class="text-end">Amount Paid</th>
            <th class="text-end">Principal Balance</th>
            <th class="text-end">Amount to Apply</th>
          </tr>
        </thead>
        <tbody>
          @foreach($loans as $loan)
          <tr>
            <td>
      <input type="checkbox" 
             name="loan_ids[]" 
             value="{{ $loan->loan_id }}"
             @if($loan->principal_balance > 0) checked @endif>
    </td>
            <td>{{ $loan->loan_doc_no ?? '-' }}</td>
            <td>{{ $loan->loan_type_name ?? '-' }}</td>
            <td class="text-end">{{ $loan->loan_taken_period }}</td>
            <td class="text-end">{{ number_format($loan->loan_amount, 2) }}</td>
            <td class="text-end">{{ number_format($loan->loan_paid, 2) }}</td>
            <td class="text-end">{{ number_format($loan->principal_balance, 2) }}</td>
            <td>
          <input type="number"
       name="loan_payments[{{ $loan->loan_id }}]"
       class="form-control form-control-sm loan-apply-input"
       value="{{ number_format($loan->principal_balance, 4, '.', '') }}"
       min="0"
       max="{{ $loan->principal_balance }}"
       step="0.0001">
          </tr>
          @endforeach
        </tbody>
      </table>

      <button type="submit" class="btn btn-primary">
        Apply Shares to Selected Loans
      </button>
    </form>
  @endif
</div>