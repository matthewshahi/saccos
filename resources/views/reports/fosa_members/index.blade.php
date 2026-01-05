@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4">

      {{-- HEADER --}}
      <div class="card-header d-flex align-items-center">
        <h3 class="w-75 card-title m-0">FOSA Members Report</h3>
        <div class="dropdown dropleft text-end w-25">
          <button class="btn bg-gray-100" data-bs-toggle="dropdown">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="{{ route('reports.fosa.members') }}">Reset</a>
          </div>
        </div>
      </div>

      {{-- FILTERS --}}
      <div class="card-body border-bottom">
        <form method="get">
          <div class="row g-2">

            <div class="col-md-4">
              <input type="text"
                     name="search"
                     class="form-control"
                     placeholder="Search member name"
                     value="{{ request('search') }}">
            </div>

            <div class="col-md-4">
              <select name="fosa_type_id" class="form-control">
                <option value="">All FOSA Types</option>
                @foreach ($fosaTypes as $type)
                  <option value="{{ $type->type_id }}"
                    @selected(request('fosa_type_id') == $type->type_id)>
                    {{ $type->type_name }}
                  </option>
                @endforeach
                <option value="__NULL__"
                  @selected(request('fosa_type_id') === '__NULL__')>
                  Unsorted / Uncategorized
                </option>
              </select>
            </div>

            <div class="col-md-4">
              <input type="text"
                     name="description_search"
                     class="form-control"
                     placeholder="Search FOSA description (e.g. REFLECTOR)"
                     value="{{ request('description_search') }}">
            </div>

            <div class="col-md-2">
              <input type="text"
                     name="period_from"
                     class="form-control"
                     placeholder="Period From (YYYYMM)"
                     value="{{ request('period_from', '000000') }}">
            </div>

            <div class="col-md-2">
              <input type="text"
                     name="period_to"
                     class="form-control"
                     placeholder="Period To (YYYYMM)"
                     value="{{ request('period_to', '999999') }}">
            </div>

            <div class="col-md-2">
              <input type="date"
                     name="date_from"
                     class="form-control"
                     value="{{ request('date_from') }}">
            </div>

            <div class="col-md-2">
              <input type="date"
                     name="date_to"
                     class="form-control"
                     value="{{ request('date_to') }}">
            </div>

            <div class="col-md-12 mt-2">
              <button class="btn btn-primary">Apply Filters</button>
              <a href="{{ route('reports.fosa.members') }}"
                 class="btn btn-outline-secondary">Reset</a>
            </div>

          </div>
        </form>
      </div>
      @if(request()->filled('description_search'))
<div class="card-body pt-3 pb-0">
    <div class="alert alert-warning d-flex align-items-start" role="alert">
        <i class="nav-icon i-Warning-Window me-2 mt-1"></i>
        <div>
            <strong>Important:</strong> You are searching using transaction descriptions.
            <br>
            Descriptions are <em>free-text</em> and may not reflect the official FOSA category.
            This means:
            <ul class="mb-1">
                <li>A member may appear when searching <strong>REFLECTOR</strong> even if the payment was actually a different category (e.g. Registration).</li>
                <li>The same member may appear under multiple description searches.</li>
            </ul>
            <strong>Best practice:</strong> Use official FOSA categorisation for accurate reporting.
            Description search is intended for <em>data review and cleanup</em> only.
        </div>
    </div>
</div>
@endif


      {{-- TABLE --}}
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
<tr>
    <th class="text-start">#</th>
    <th class="text-start">Member</th>
    <th class="text-start">SACCO No</th>
    <th class="text-start">National ID</th>
    <th class="text-start">KRA PIN</th>
    <th class="text-start">Phone</th>
    <th class="text-start">Department</th>
    <th class="text-start">Company</th>
    <th class="text-end">Txns</th>
    <th class="text-end">Total FOSA</th>
    <th class="text-end">Last Txn</th>
</tr>
</thead>

<tbody>
@forelse ($records as $i => $row)
<tr>
    <td class="text-start">{{ $records->firstItem() + $i }}</td>

    <td class="text-start fw-semibold">{{ $row->member_name }}</td>

    <td class="text-start text-muted">{{ $row->member_sacco_id ?? '—' }}</td>

    <td class="text-start text-muted">{{ $row->member_national_id ?? '—' }}</td>

    <td class="text-start text-muted">{{ $row->member_kra_pin ?? '—' }}</td>

    <td class="text-start">{{ $row->member_phone_no ?? '—' }}</td>

    <td class="text-start">{{ $row->department_name }}</td>

    <td class="text-start">{{ $row->company_name }}</td>

    <td class="text-end">{{ number_format($row->txn_count) }}</td>

    <td class="text-end fw-bold">{{ number_format($row->total_fosa_amount, 2) }}</td>

    <td class="text-end">
        {{ \Carbon\Carbon::parse($row->last_txn_date)->format('d/m/Y H:i') }}
    </td>
</tr>
@empty
<tr>
    <td colspan="11" class="text-center text-muted">
        No matching records found
    </td>
</tr>
@endforelse
</tbody>

          </table>
        </div>

        {{ $records->links() }}
      </div>

    </div>
  </div>
</div>
@endsection
