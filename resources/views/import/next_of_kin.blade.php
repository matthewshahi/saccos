@extends('layouts.app')

@section('content')
<div class="container">
    <h4>Import Next of Kin</h4>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('members.import.kin') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
            <label for="csv_file" class="form-label">Upload CSV File</label>
            <input type="file" name="csv_file" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">Import Next of Kin</button>
    </form>
</div>
@endsection