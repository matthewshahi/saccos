@extends('layouts.app')

@section('content')
<div class="container">
    <h4>{{ isset($penalty) ? 'Edit Penalty' : 'Add Penalty' }}</h4>
    <p class="text-muted">Note: The penalty amount will be deducted directly from the member's deposits (shares).</p>

    <form method="POST" action="{{ isset($penalty) ? route('penalties.update', $penalty->id) : route('penalties.store') }}">
        @csrf

        <div class="mb-3">
            <label>Date of Penalty</label>
            <input type="date" name="penalty_date" class="form-control" value="{{ old('penalty_date', $penalty->penalty_date ?? '') }}" required>
        </div>

        <div class="mb-3">
            <label>Member</label>
            <select name="penalty_member_id" class="form-control" required>
                <option value="">Select Member</option>
                @foreach($members as $member)
                    <option value="{{ $member->member_id }}" {{ old('penalty_member_id', $penalty->penalty_member_id ?? '') == $member->member_id ? 'selected' : '' }}>
                        {{ $member->member_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label>Description of Penalty</label>
            <input type="text" name="penalty_description" class="form-control" value="{{ old('penalty_description', $penalty->penalty_description ?? '') }}" required>
            <small class="text-muted">E.g. Late remittance, missing target, misconduct</small>
        </div>

        <div class="mb-3">
            <label>Penalty Amount (KES)</label>
            <input type="number" name="penalty_amount" class="form-control" value="{{ old('penalty_amount', $penalty->penalty_amount ?? '') }}" required>
        </div>

        <button type="submit" class="btn btn-success">Save Penalty</button>
    </form>
</div>
@endsection