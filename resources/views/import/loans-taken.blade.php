@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h3 class="mb-3">Import Loans Taken</h3>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif
  @if (session('skipped_rows'))
    <div class="alert alert-warning">
      Skipped rows:
      <ul class="mb-0">
        @foreach(session('skipped_rows') as $row)
          <li>{{ $row }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card">
    <div class="card-body">
      <form action="{{ route('import.loans.taken.run') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label class="form-label">Loans Taken CSV</label>
          <input type="file" name="csv_file" accept=".csv,.txt" class="form-control" required>
          <div class="form-text">
            Use <em>sacco_loans_taken_import_names_fixed.csv</em>.<br>
            Columns (case-insensitive): loan_member (name) or member_sacco_id, loan_amount, loan_taken_period (YYYYMM),
            loan_payment_period (months or days), loan_monthly_repayment_amount (or leave blank; we’ll compute as amount+interest),
            loan_doc_no, loan_on (YYYY-MM-DD), loan_description, loan_batch_no, loan_start_deduction_period (YYYYMM), loan_taken_start_period (YYYYMM).
            Any of these can be blank; defaults will be applied per rules.
          </div>
        </div>
        <button class="btn btn-primary">Import Loans Taken</button>
      </form>
    </div>
  </div>
</div>
@endsection