@extends('layouts.app')
@section('content')
<div class="container py-4">
  <h3 class="mb-3">Import Deposits / Shares</h3>
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
  @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
  @if (session('skipped_rows'))
    <div class="alert alert-warning">
      Skipped rows:
      <ul class="mb-0">
        @foreach(session('skipped_rows') as $row) <li>{{ $row }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('import.shares.run') }}" method="POST" enctype="multipart/form-data" class="card card-body">
    @csrf
    <input type="file" name="csv_file" accept=".csv,.txt" class="form-control mb-3" required>
    <small class="text-muted">
      CSV headers: member_name (or member_sacco_id), share_amount_paying, share_paid_by, share_period (YYYYMM),
      share_description, share_doc_no, share_date_paid (YYYY-MM-DD), share_by, share_ip, share_transdate, share_end_month_proc.
      <br>Tip: you can upload the file I generated: <em>sacco_shares_import_names_fixed.csv</em>.
    </small>
    <button class="btn btn-primary mt-2">Import Deposits</button>
  </form>
</div>
@endsection