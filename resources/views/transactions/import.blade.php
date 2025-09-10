@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="mb-4">Import Transactions (FOSA / Deposits / Capital)</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (session('summary'))
    @php($s = session('summary'))
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Import Summary</h5>
        <ul class="mb-2">
          <li>Total rows scanned: <strong>{{ $s['total_rows'] }}</strong></li>
          <li>Inserted into <code>sacco_fosas</code>: <strong>{{ $s['fosa_inserted'] }}</strong></li>
          <li>Inserted into <code>sacco_shares</code> (Deposits): <strong>{{ $s['shares_inserted'] }}</strong></li>
          <li>Inserted into <code>sacco_capital_shares</code> (Capital): <strong>{{ $s['capital_inserted'] }}</strong></li>
          <li>Unmatched members (skipped): <strong>{{ count($s['unmatched']) }}</strong></li>
        </ul>

        @if (!empty($s['unmatched']))
          <details class="mt-2">
            <summary>Unmatched Names (click to expand)</summary>
            <ul class="mt-2">
              @foreach ($s['unmatched'] as $nf)
                <li>{{ $nf }}</li>
              @endforeach
            </ul>
          </details>
        @endif
      </div>
    </div>
  @endif

  <div class="card">
    <div class="card-body">
      <form action="{{ route('transactions.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label for="csv" class="form-label">CSV File</label>
          <input type="file" class="form-control" id="csv" name="csv" accept=".csv,text/csv" required>
          <div class="form-text">
            Expected headers (case-insensitive; extra spaces OK). If not detected, we'll fall back to column order:<br>
            <code>name | date | amount | notes | category</code><br>
            Date can be day-first (dd/mm/yyyy). Category must be one of: <em>Deposits</em>, <em>Capital Shares</em>, <em>FOSA</em>.
          </div>
        </div>

        <div class="alert alert-warning">
          <strong>Note:</strong> This will <u>TRUNCATE</u> <code>sacco_fosas</code>, <code>sacco_shares</code>, and <code>sacco_capital_shares</code> <u>before</u> import.
        </div>

        <button type="submit" class="btn btn-primary">Run Import</button>
      </form>
    </div>
  </div>
</div>
@endsection