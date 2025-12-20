@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <h4>MEMBER MASTER IMPORT</h4>

    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ route('kass.member_master_import.preview') }}">
        @csrf
        <input type="file" name="excel_file" class="form-control mb-2" required>
        <button class="btn btn-primary">PREVIEW IMPORT</button>
    </form>

    @if(session('summary'))
        <pre class="mt-3 bg-light p-3">{{ print_r(session('summary'), true) }}</pre>
    @endif
</div>
@endsection
