@extends('layouts.app')

@section('content')
<div class="container-fluid">

    <h4 class="mb-3">FOSA Contributions – Member Summary</h4>

    {{-- Filters --}}
    <form method="get" class="card mb-3">
        <div class="card-body">
            <div class="row g-2">

                <div class="col-md-3">
                    <input type="text" name="search" class="form-control"
                        placeholder="Search member name / number"
                        value="{{ request('search') }}">
                </div>

                <div class="col-md-2">
                    <select name="fosa_type_id" class="form-control">
                        <option value="">All FOSA Types</option>
                        @foreach ($fosaTypes as $type)
                            <option value="{{ $type->type_id }}"
                                @selected(request('fosa_type_id') == $type->type_id)>
                                {{ $type->type_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="text" name="period_from" class="form-control"
                        placeholder="Period From (YYYYMM)"
                        value="{{ request('period_from') }}">
                </div>

                <div class="col-md-2">
                    <input type="text" name="period_to" class="form-control"
                        placeholder="Period To (YYYYMM)"
                        value="{{ request('period_to') }}">
                </div>

                <div class="col-md-3">
                    <button class="btn btn-primary">Apply Filters</button>
                    <a href="{{ route('reports.fosa.members') }}"
                       class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>

            </div>
        </div>
    </form>

    {{-- Results --}}
    <div class="card">
        <div class="card-body table-responsive">

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Member No</th>
                        <th>Member Name</th>
                        <th>Department</th>
                        <th>Company</th>
                        <th class="text-end">Transactions</th>
                        <th class="text-end">Total FOSA</th>
                        <th>Last Transaction</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $row)
                        <tr>
                            <td>{{ $row->member_no }}</td>
                            <td>{{ $row->member_name }}</td>
                            <td>{{ $row->department_name }}</td>
                            <td>{{ $row->company_name }}</td>
                            <td class="text-end">{{ number_format($row->txn_count) }}</td>
                            <td class="text-end">{{ number_format($row->total_fosa_amount, 2) }}</td>
                            <td>{{ $row->last_txn_date }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                No records found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $records->links() }}

        </div>
    </div>

</div>
@endsection
