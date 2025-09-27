@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    @include('partials.alerts')

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4>Preview Import</h4>
      </div>

      <div class="card-body">
        <form action="{{ route('fosa.transactions.import.process') }}" method="POST">
          @csrf
          <input type="hidden" name="csv_path" value="{{ $csv_path }}">

          <div class="mb-3">
            <label for="ledger_id" class="form-label">Ledger / Counter Account <span class="text-danger">*</span></label>
            <div class="form-text text-muted">
              Select the <strong>ledger account where the balancing entry should go</strong>  
              (e.g., Bank Account, Cash Account, Mobile Money).
            </div>
            <select name="ledger_id" id="ledger_id" class="form-control" required></select>
            
          </div>

          <div class="table-responsive mt-4">
            <table class="table table-bordered table-hover">
              <thead class="table-light">
                <tr>
                  <th>Member No</th>
                  <th>National ID</th>
                  <th>Amount</th>
                  <th>Type</th>
                  <th>Description</th>
                  <th>Doc No</th>
                  <th>Period</th>
                  <th>Date Paid</th>
                  <th>FOSA Paid By</th>
                  <th>FOSA Type</th>
                </tr>
              </thead>
              <tbody>
                @foreach($data as $row)
                <tr>
                  <td>{{ $row['member_no'] }}</td>
                  <td>{{ $row['national_id'] }}</td>
                  <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
                  <td>
                    @if(strtolower($row['type']) === 'deposit')
                      <span class="badge bg-success">Deposit</span>
                    @else
                      <span class="badge bg-danger">Withdrawal</span>
                    @endif
                  </td>
                  <td>{{ $row['description'] }}</td>
                  <td>{{ $row['doc_no'] }}</td>
                  <td>{{ $row['period'] }}</td>
                  <td>{{ $row['date_paid'] }}</td>
                  <td>{{ $row['fosa_paid_by'] }}</td>
                  <td>{{ $row['fosa_type'] }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="mt-4">
            <button type="submit" class="btn btn-success">✅ Confirm & Import</button>
            <a href="{{ route('fosa.transactions.import') }}" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script>
$(document).ready(function() {
    $('#ledger_id').select2({
        theme: 'bootstrap4',
        placeholder: '-- Search Ledger Account --',
        allowClear: true,
        ajax: {
            url: "{{ route('api.accounts.search') }}", // 👈 reuse your account search
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { term: params.term };
            },
            processResults: function(data) {
                return { results: data };
            }
        }
    });
});
</script>
@endsection