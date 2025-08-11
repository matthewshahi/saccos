@extends('layouts.app')

@section('content')
<div class="card">
     @include('transport._nav_links')
    <div class="card-header">
        <h5 class="mb-0">Add Penalty</h5>
        <small class="text-muted">Use this form to record a penalty. <strong>This amount will be deducted from the member’s deposit shares.</strong></small>
    </div>
    

    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('penalties.store') }}">
            @csrf

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="penalty_date" class="form-label">Penalty Date</label>
                    <input type="date" name="penalty_date" class="form-control" value="{{ old('penalty_date') }}" required>
                </div>

                <div class="col-md-6">
                    <label for="penalty_member_id" class="form-label">Select Member</label>
                    <select name="penalty_member_id" class="form-select" required>
                        <option value="">-- Choose Member --</option>
                        @foreach($members as $member)
                            <option value="{{ $member->member_id }}" {{ old('penalty_member_id') == $member->member_id ? 'selected' : '' }}>
                                {{ $member->member_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-8">
                    <label for="penalty_description" class="form-label">Penalty Description</label>
                    <input type="text" name="penalty_description" class="form-control" value="{{ old('penalty_description') }}" required>
                    <small class="text-muted">E.g. Late Target, Vehicle Misuse, Absenteeism, etc.</small>
                </div>

                <div class="col-md-4">
                    <label for="penalty_amount" class="form-label">Amount (KES)</label>
                    <input type="number" name="penalty_amount" class="form-control" min="1" step="0.01" value="{{ old('penalty_amount') }}" required>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-success">Save Penalty</button>
            </div>
        </form>
    </div>
</div>
@endsection