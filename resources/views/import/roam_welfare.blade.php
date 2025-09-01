@extends('layouts.app')

@section('content')
<div class="container">
    <h4>Import Welfare Payments (CSV Only)</h4>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('roamwelfare.import') }}" method="POST" enctype="multipart/form-data" class="card p-4 mt-3">
        @csrf
        <div class="mb-3">
            <label for="csv_file" class="form-label">Upload CSV File</label>
            <input type="file" name="csv_file" id="csv_file" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-success">Import Welfare</button>
    </form>
</div>
@endsection