@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-8 offset-md-2">
    @include('partials.alerts')

    <div class="card" id="importCard">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Import FOSA Transactions</h4>
        <a href="{{ asset('template/fosa_sample_import.csv') }}" 
           class="btn btn-sm btn-outline-primary" 
           download>
           ⬇ Download Sample Template
        </a>
      </div>
      <div class="card-body">
        <form action="{{ route('fosa.transactions.import.preview') }}" method="POST" enctype="multipart/form-data">
          @csrf
          
          <!-- CSV Upload -->
          <div class="mb-3">
            <label for="csv_file" class="form-label">CSV File</label>
            <input type="file" name="csv_file" id="csv_file" 
                   class="form-control" accept=".csv" required>
            <div class="form-text">
              Upload a valid CSV file. Please make sure it follows the correct format.
            </div>
          </div>

          <!-- Expected Format Notes -->
          <div class="alert alert-info small">
            <strong>Expected CSV Columns (in order):</strong><br>
            <code>
              member_no, national_id, amount, type, description, doc_no, period, date_paid, fosa_paid_by, fosa_type
            </code>
            <hr class="my-2">
            <ul class="mb-0">
              <li><code>member_no</code> → SACCO Member Number (<em>member_sacco_id</em> in system)</li>
              <li><code>national_id</code> → Member National ID (<em>member_national_id</em>)</li>
              <li><code>amount</code> → Numeric transaction amount (positive for deposits, positive with <code>type=withdrawal</code> will be converted)</li>
              <li><code>type</code> → <code>deposit</code> or <code>withdrawal</code></li>
              <li><code>description</code> → Transaction description</li>
              <li><code>doc_no</code> → Supporting document / reference number</li>
              <li><code>period</code> → Accounting period in <code>YYYYMM</code> format</li>
              <li><code>date_paid</code> → Transaction date in <code>YYYY-MM-DD HH:mm</code> format</li>
              <li><code>fosa_paid_by</code> → Name of person who physically paid (optional, as recorded on receipt)</li>
              <li><code>fosa_type</code> → FOSA Type (optional, can be left blank)</li>
            </ul>
          </div>

          <button type="submit" class="btn btn-primary">Preview Import</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection