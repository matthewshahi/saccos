@extends('layouts.app')

@section('content')
<div class="card">
    @include('transport._nav_links')

    <div class="card-header"><h5 class="mb-0">Add Collection</h5></div>

    <div class="card-body">
        {{-- ALERT: Require either Operator or Vehicle --}}
        <div class="alert alert-warning mb-3">
            <strong>Note:</strong> You must select either an <strong>Operator</strong> or a <strong>Vehicle</strong>.<br>
            <ul class="mb-0">
                <li>If a <strong>Vehicle</strong> is selected, funds will be credited to the <strong>vehicle owner</strong>.</li>
                <li>If only an <strong>Operator</strong> is selected, funds go to the <strong>operator</strong>.</li>
                <li>If <strong>both</strong> are selected, the vehicle <strong>overrides</strong> the operator.</li>
            </ul>
        </div>

        <form action="{{ route('collections.store') }}" method="POST">
            @csrf

            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="coll_date" class="form-label">Date</label>
                    <input type="date" name="coll_date" class="form-control" value="{{ old('coll_date', date('Y-m-d')) }}" required>
                </div>

                <div class="col-md-4">
                    <label for="coll_type" class="form-label">Type</label>
                    <select name="coll_type" class="form-select" required>
                        <optgroup label="Core Types">
                            @foreach(['daily_target','share','loan_repayment','penalty','deposit'] as $type)
                                <option value="{{ $type }}" {{ old('coll_type') == $type ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $type)) }}
                                </option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Administrative / System">
                            @foreach(['adjustment','fine','maintenance_fee','fuel_reimbursement','other'] as $type)
                                <option value="{{ $type }}" {{ old('coll_type') == $type ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $type)) }}
                                </option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="coll_mode" class="form-label">Mode</label>
                    <select name="coll_mode" class="form-select" required>
                        @foreach(['cash','mpesa','airtel','bank','adjustment','other'] as $mode)
                            <option value="{{ $mode }}" {{ old('coll_mode') == $mode ? 'selected' : '' }}>{{ ucfirst($mode) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="coll_operator_id" class="form-label">Operator (optional)</label>
                    <select name="coll_operator_id" class="form-select">
                        <option value="">-- Select Operator --</option>
                        @foreach($operators as $op)
                            <option value="{{ $op->id }}" {{ old('coll_operator_id') == $op->id ? 'selected' : '' }}>{{ $op->full_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="coll_vehicle_id" class="form-label">Vehicle (optional)</label>
                    <select name="coll_vehicle_id" class="form-select">
                        <option value="">-- Select Vehicle --</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}" {{ old('coll_vehicle_id') == $v->id ? 'selected' : '' }}>{{ $v->vehicles_registration_number }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="coll_amount" class="form-label">Amount</label>
                    <input type="number" name="coll_amount" class="form-control" required step="0.01" min="0" value="{{ old('coll_amount') }}">
                </div>

                <div class="col-md-4">
                    <label for="coll_reference" class="form-label">Reference</label>
                    <input type="text" name="coll_reference" class="form-control" maxlength="100" value="{{ old('coll_reference') }}">
                </div>

                <div class="col-md-4">
                    <label for="coll_notes" class="form-label">Notes</label>
                    <input type="text" name="coll_notes" class="form-control" maxlength="255" value="{{ old('coll_notes') }}">
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <a href="{{ route('collections') }}" class="btn btn-outline-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-success">Save Collection</button>
            </div>
        </form>
    </div>
</div>
@endsection