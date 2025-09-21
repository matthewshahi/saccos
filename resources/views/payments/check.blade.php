@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4>Check Payment Status</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('payment.check.submit') }}">
                @csrf
                <div class="form-group mb-3">
                    <label for="reference_number">Enter Reference Number</label>
                    <input type="text" name="reference_number" id="reference_number"
                           class="form-control @error('reference_number') is-invalid @enderror"
                           placeholder="e.g. QLK9D12ABC" required>
                    @error('reference_number')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="btn btn-success">Check Payment</button>
            </form>
        </div>
    </div>
</div>
@endsection