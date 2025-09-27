@extends('layouts.app')

@section('content')
<div class="row">
  @include('partials.alerts')
  <div class="col-md-12">
    <div class="card mb-4" id="fosaFormCard">
      <div class="card-body">
        <h4 class="card-title mb-3">Add / Reduce FOSA</h4>

        <form action="{{ route('fosa.transactions.store') }}" method="POST" id="fosaForm" novalidate>
          @csrf
          <div class="row g-3">
            <!-- Member -->
            <div class="col-md-6">
              <label for="fosa_member_id" class="form-label">Member <span class="text-danger">*</span></label>
              <select name="fosa_member_id" id="fosa_member_id" class="form-control" required></select>
              <div class="invalid-feedback">Please select a member.</div>
            </div>

            <!-- Amount -->
            <div class="col-md-6">
              <label for="fosa_amount_paying" class="form-label">Amount <span class="text-danger">*</span></label>
              <input type="number" step="0.01" name="fosa_amount_paying" id="fosa_amount_paying"
                class="form-control" placeholder="Enter amount" required>
              <div class="invalid-feedback">Amount is required.</div>
            </div>

            <div class="col-md-6">
              <label for="fosa_type_id" class="form-label">FOSA Type <span class="text-danger">*</span></label>
              <select name="fosa_type_id" id="fosa_type_id" class="form-control" required>
                <option value="">-- Select Type --</option>
                @foreach($fosaTypes as $type)
                <option value="{{ $type->type_id }}">{{ $type->type_name }}</option>
                @endforeach
              </select>
              <div class="invalid-feedback">Please select a FOSA type.</div>
            </div>


            <!-- Transaction Type -->
            <div class="col-md-6">
              <label for="fosa_trans_type" class="form-label">Transaction Type <span class="text-danger">*</span></label>
              <select name="fosa_trans_type" id="fosa_trans_type" class="form-control" required>
                <option value="debit" selected>Withdrawal (Reduce)</option>
                <option value="credit">Deposit (Add)</option>
              </select>
              <div class="invalid-feedback">Please select transaction type.</div>
            </div>

            <!-- Counter Account -->
            <div class="col-md-6">
              <label for="counter_account" class="form-label">Counter Account <span class="text-danger">*</span></label>
              <select name="counter_account" id="counter_account" class="form-control" required></select>
              <div class="form-text text-muted">
                <br>Select the <strong>account where money is coming from or going to</strong>
                (e.g., Bank Account, Cash Account, Mobile Money).</br>
              </div>
              <div class="invalid-feedback">Please select a counter account.</div>
            </div>

            <!-- Period -->
            <div class="col-md-6">
              <label for="fosa_period" class="form-label">
                Period (YYYYMM) <span class="text-danger">*</span>
              </label>
              <input type="text"
                name="fosa_period"
                id="fosa_period"
                class="form-control"
                value="{{ $activePeriod }}"
                required>
              <div class="invalid-feedback">Please enter period in YYYYMM format.</div>
            </div>

            <!-- Date Paid -->
            <div class="col-md-6">
              <label for="fosa_date_paid" class="form-label">Date Paid <span class="text-danger">*</span></label>
              <input type="datetime-local" name="fosa_date_paid" id="fosa_date_paid"
                class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" required>
              <div class="invalid-feedback">Please select the date paid.</div>
            </div>

            <!-- Description -->
            <div class="col-md-6">
              <label for="fosa_description" class="form-label">Description <span class="text-danger">*</span></label>
              <input type="text" name="fosa_description" id="fosa_description"
                class="form-control" placeholder="Enter description" required>
              <div class="invalid-feedback">Description is required.</div>
            </div>

            <!-- Document No -->
            <div class="col-md-6">
              <label for="fosa_doc_no" class="form-label">Document No <span class="text-danger">*</span></label>
              <input type="text" name="fosa_doc_no" id="fosa_doc_no"
                class="form-control" placeholder="Enter document number" required>
              <div class="invalid-feedback">Document number is required.</div>
            </div>
          </div>

          <!-- Submit buttons -->
          <div class="mt-4">
            <button type="submit" class="btn btn-primary">Submit Transaction</button>
            <a href="{{ route('fosa.transactions.index') }}" class="btn btn-secondary">Back to List</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.6.2/dist/select2-bootstrap4.min.css" rel="stylesheet" />

<script>
  $(document).ready(function() {
    // Member search
    $('#fosa_member_id').select2({
      theme: 'bootstrap4',
      placeholder: '-- Search Member --',
      allowClear: true,
      ajax: {
        url: "{{ route('api.members.search') }}",
        dataType: 'json',
        delay: 250,
        data: function(params) {
          return {
            term: params.term
          };
        },
        processResults: function(data) {
          return {
            results: data
          };
        }
      },
      dropdownParent: $('#fosaFormCard')
    });

    // Counter account search
    $('#counter_account').select2({
      theme: 'bootstrap4',
      placeholder: '-- Search Account --',
      allowClear: true,
      ajax: {
        url: "{{ route('api.accounts.search') }}",
        dataType: 'json',
        delay: 250,
        data: function(params) {
          return {
            term: params.term
          };
        },
        processResults: function(data) {
          return {
            results: data
          };
        }
      },
      dropdownParent: $('#fosaFormCard')
    });

    // Bootstrap validation
    (function() {
      'use strict'
      var forms = document.querySelectorAll('#fosaForm')
      Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
          if (!form.checkValidity()) {
            event.preventDefault()
            event.stopPropagation()
          }
          form.classList.add('was-validated')
        }, false)
      })
    })()
  });
</script>
@endsection