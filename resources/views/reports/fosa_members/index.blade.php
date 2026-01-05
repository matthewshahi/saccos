@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4">

      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">FOSA Members Report</h3>

        <div class="dropdown dropleft text-end w-50 float-end">
          <button class="btn bg-gray-100" type="button" data-bs-toggle="dropdown">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="{{ route('reports.fosa.members') }}">Reset Filters</a>
          </div>
        </div>
      </div>

      <div class="card-body border-bottom">
        <form method="get">
          <div class="row g-2">

            <div class="col-md-5">
              <input type="text" name="search" class="form-control"
                     placeholder="Search member name"
                     value="{{ request('search') }}">
            </div>

            <div class="col-md-4">
              <select name="fosa_type_id" class="form-control">
                <option value="">All FOSA Types</option>
                @foreach ($fosaTypes as $type)
                  <option value="{{ $type->type_id }}" @selected(request('fosa_type_id') == $type->type_id)>
                    {{ $type->type_name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3">
              <button class="btn btn-primary w-100">Apply</button>
            </div>

          </div>

          <div class="row g-2 mt-2">
            <div class="col-md-3">
              <input type="text" name="period_from" class="form-control"
                     placeholder="Period From (YYYYMM)"
                     value="{{ request('period_from') }}">
            </div>
            <div class="col-md-3">
              <input type="text" name="period_to" class="form-control"
                     placeholder="Period To (YYYYMM)"
                     value="{{ request('period_to') }}">
            </div>
          </div>
        </form>
      </div>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table text-center">
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
              @forelse($records as $i => $row)
                <tr>
                  <th scope="row">{{ $records->firstItem() + $i }}</th>
                  <td>{{ $row->member_name }}</td>
                  <td>{{ $row->department_name }}</td>
                  <td>{{ $row->company_name }}</td>
                  <td>{{ number_format($row->txn_count) }}</td>
                  <td>{{ number_format($row->total_fosa_amount, 2) }}</td>
                  <td>{{ $row->last_txn_date }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-muted">No records found.</td>
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
