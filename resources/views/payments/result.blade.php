@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h4>Payment Status Result</h4>
        </div>
        <div class="card-body">
            <p><strong>Reference Number:</strong> {{ $ref }}</p>
            <p><strong>Status:</strong>
                @if($status === 'Success')
                    <span class="text-success">{{ $status }}</span>
                @elseif($status === 'Pending')
                    <span class="text-warning">{{ $status }}</span>
                @else
                    <span class="text-danger">{{ $status }}</span>
                @endif
            </p>

            @if($response)
                <pre class="bg-light p-3">{{ $response }}</pre>
            @endif

            <a href="{{ route('payment.check') }}" class="btn btn-secondary">Check Another</a>
        </div>
    </div>
</div>
@endsection