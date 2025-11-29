@extends('layouts.app')

@section('content')

<div class="row mb-3">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="w-50 float-start card-title m-0">
                    Transport — MPESA Collections
                </h3>

                <form method="GET" class="d-flex align-items-center gap-2">
                    <div>
                        <label class="small text-muted mb-0">From</label>
                        <input type="date" name="from" class="form-control"
                            value="{{ $from }}">
                    </div>

                    <div>
                        <label class="small text-muted mb-0">To</label>
                        <input type="date" name="to" class="form-control"
                            value="{{ $to }}">
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-primary btn-sm">Filter</button>
                    </div>
                </form>
            </div>

            <div class="card-body">

                <h5 class="mb-3">
                    Total MPESA Collections:
                    <span class="badge bg-success">
                        Ksh {{ number_format($total, 2) }}
                    </span>
                </h5>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered text-center align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Amount (Ksh)</th>
                                <th>Type</th>
                                <th>Operator</th>
                                <th>Vehicle</th>
                                <th>Description</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($records as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->coll_date }}</td>
                                    <td>
                                        <strong>Ksh {{ number_format($row->coll_amount, 2) }}</strong>
                                    </td>
                                    <td>{{ ucfirst($row->coll_type) }}</td>

                                    <td>
                                        @if($row->operator_name)
                                            {{ $row->operator_name }}<br>
                                            <small class="text-muted">{{ $row->operator_phone }}</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $row->vehicle_reg ?? '—' }}
                                    </td>

                                    <td>{{ $row->coll_description ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-muted py-3">
                                        No MPESA records found for selected dates.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection
