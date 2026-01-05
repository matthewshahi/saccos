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
                     value="{{ request('date_from', $dateFrom) }}">
            </div>

            <div class="col-md-2">
              <input type="date"
                     name="date_to"
                     class="form-control"
                     value="{{ request('date_to', $dateTo) }}">
            </div>

            <div class="col-md-12 mt-2">
              <button class="btn btn-primary">Apply Filters</button>
              <a href="{{ route('reports.fosa.members') }}"
                 class="btn btn-outline-secondary">Reset</a>
            </div>

          </div>
        </form>
      </div>

      {{-- TABLE --}}
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped text-center">
            <thead>
              <tr>
                <th>#</th>
                <th>Member</th>
                <th>Department</th>
                <th>Company</th>
                <th>Txns</th>
                <th>Total FOSA</th>
                <th>Last Txn</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($records as $i => $row)
                <tr>
                  <th>{{ $records->firstItem() + $i }}</th>
                  <td>{{ $row->member_name }}</td>
                  <td>{{ $row->department_name }}</td>
                  <td>{{ $row->company_name }}</td>
                  <td>{{ number_format($row->txn_count) }}</td>
                  <td>{{ number_format($row->total_fosa_amount, 2) }}</td>
                  <td>{{ $row->last_txn_date }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-muted">No matching records.</td>
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
