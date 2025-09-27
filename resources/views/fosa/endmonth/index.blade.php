@extends('layouts.app')

@section('content')
<div class="container">
  @include('partials.alerts')

  <h3>End Month FOSA Processing</h3>

  <!-- Period Filter -->
  <form method="GET" class="mb-3 d-flex align-items-center gap-2">
    <label class="me-2">Period (YYYYMM)</label>
    <input type="text" name="period" 
           value="{{ old('period', request('period', $period)) }}" 
           class="form-control w-auto">

    <button class="btn btn-primary">Change</button>

    <input type="text" name="search" id="searchInput" 
           value="{{ old('search', request('search')) }}"
           class="form-control w-50 ms-auto"
           placeholder="🔍 Search by Name, Sacco ID, National ID, or Company...">
</form>

  <!-- Processing Form -->
  <form action="{{ route('fosa.endmonth.process') }}" method="POST"
        onsubmit="return confirm('Process selected contributions for period {{ $period }}?')">
    @csrf
    <input type="hidden" name="period" value="{{ $period }}">

    <div class="mb-3">
  <label>
    Counter Ledger Account <span class="text-danger">*</span>
    <small class="text-muted"> (Bank, Cash, Mobile Money, etc.)</small>
  </label>
  <select name="ledger_id" id="ledger_id" class="form-control" required></select>
</div>

<div class="mb-3">
  <label>
    Document Prefix <span class="text-danger">*</span>
  </label>
  <input type="text" name="doc_prefix" 
         value="FOSA -{{ $period }}" 
         class="form-control" required>
</div>

    <div class="table-responsive">

    <p class="text-danger fw-bold">
  ⚠️ Only the members you select below will be processed.  
  If a member is already processed for period {{ $period }}, they will be skipped automatically.
</p>
      <table class="table table-bordered table-hover" id="membersTable">
        <thead class="table-light">
  <tr>
    <th><input type="checkbox" id="selectAll"></th>
    <th>Sacco ID</th>
    <th>National ID</th>
    <th>Company</th>
    <th>Department</th>
    <th>Name</th>
    <th class="text-end">Monthly FOSA</th>
    <th class="text-end">Total FOSA</th>
  </tr>
</thead>
<tbody>
  @foreach($members as $m)
  <tr>
    <td><input type="checkbox" name="selected_members[]" value="{{ $m->member_id }}" class="row-check"></td>
    <td>{{ $m->member_sacco_id }}</td>
    <td>{{ $m->member_national_id }}</td>
    <td>{{ $m->company_name ?? '-' }}</td>
    <td>{{ $m->department_name ?? '-' }}</td>
    <td>{{ $m->member_name }}</td>
    <td class="text-end">
      <input type="number" step="0.01"
             value="{{ $m->member_fosa_contr_monthly }}"
             data-id="{{ $m->member_id }}"
             class="form-control form-control-sm contr-input text-end">
    </td>
    <td class="text-end">{{ number_format($m->member_total_fosa, 2) }}</td>
  </tr>
  @endforeach
</tbody>
<tfoot class="table-light fw-bold">
      <tr>
        <td colspan="6" class="text-end">Total Monthly FOSA:</td>
        <td class="text-end" id="totalMonthlyFosa">0.00</td>
        <td></td>
      </tr>
    </tfoot>
      </table>
    </div>

    <p class="text-danger fw-bold">
  ⚠️ Only the members you select below will be processed.  
  If a member is already processed for period {{ $period }}, they will be skipped automatically.
</p>
    <button type="submit" class="btn btn-success mt-3">Process Selected Members for {{ $period }}</button>
  </form>
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){

  // 🔄 Auto update contributions
  $('.contr-input').change(function(){
    var id = $(this).data('id');
    var amount = $(this).val();
    $.post("{{ url('fosa/endmonth/update') }}/" + id,
      {_token: '{{ csrf_token() }}', amount: amount},
      function(resp){ console.log(resp); });
  });

  // ✅ Select/Deselect all
  $('#selectAll').on('change', function(){
    $('.row-check').prop('checked', $(this).is(':checked'));
  });

  // 🔍 Search filter
  $('#searchInput').on('keyup', function(){
    var value = $(this).val().toLowerCase();
    $("#membersTable tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });

});
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.6.2/dist/select2-bootstrap4.min.css" rel="stylesheet" />

<script>
$(function(){

  // 🔄 Auto update contributions
  $('.contr-input').change(function(){
    var id = $(this).data('id');
    var amount = $(this).val();
    $.post("{{ url('fosa/endmonth/update') }}/" + id,
      {_token: '{{ csrf_token() }}', amount: amount},
      function(resp){ console.log(resp); });
  });

  // ✅ Select/Deselect all
  $('#selectAll').on('change', function(){
    $('.row-check').prop('checked', $(this).is(':checked'));
  });

  // 🔍 Search filter
  $('#searchInput').on('keyup', function(){
    var value = $(this).val().toLowerCase();
    $("#membersTable tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });

  // ⚡ Smart select for ledger accounts
  $('#ledger_id').select2({
    theme: 'bootstrap4',
    placeholder: '-- Search Ledger Account --',
    allowClear: true,
    ajax: {
      url: "{{ route('api.accounts.search') }}",  // ✅ must return JSON [{id,text}]
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

function updateTotals() {
  let total = 0;
  $("#membersTable tbody tr:visible").each(function() {
    let val = parseFloat($(this).find('.contr-input').val());
    if (!isNaN(val) && val > 0) {
      total += val;
    }
  });
  $("#totalMonthlyFosa").text(total.toFixed(2));
}

$(function(){

  // 🔄 Auto update contributions
  $('.contr-input').on('input', function(){
    var id = $(this).data('id');
    var amount = $(this).val();
    $.post("{{ url('fosa/endmonth/update') }}/" + id,
      {_token: '{{ csrf_token() }}', amount: amount},
      function(resp){ console.log(resp); });

    updateTotals();
  });

  // ✅ Select/Deselect all
  $('#selectAll').on('change', function(){
    $('.row-check').prop('checked', $(this).is(':checked'));
  });

  // 🔍 Search filter
  $('#searchInput').on('keyup', function(){
    var value = $(this).val().toLowerCase();
    $("#membersTable tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
    updateTotals();
  });

  // ⚡ Smart select for ledger accounts
  $('#ledger_id').select2({
    theme: 'bootstrap4',
    placeholder: '-- Search Ledger Account --',
    allowClear: true,
    ajax: {
      url: "{{ route('api.accounts.search') }}",
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

  // 🧮 Initial calculation
  updateTotals();
});
</script>
@endsection