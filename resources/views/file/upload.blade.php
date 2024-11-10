@extends('layouts.app')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">

<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>File Upload</h1>
    
</div>
<div class="separator-breadcrumb border-top"></div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

<div class="col-md-6 mb-4">
    <div class="card text-start">
    
        <div class="card-body">
        <div class="header-part-right d-flex justify-content-end">
        <a href="{{ route('files.list') }}" class="btn btn-outline-primary btn-sm">List Files</a>
    </div>            <h4 class="card-title">Upload Files</h4>  

            
            <form action="{{ route('file.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group mb-3">
                    <label for="file_description">File Description / Title</label>
                    <input type="text" class="form-control" id="file_description" name="file_description" placeholder="Enter file description or title" required>
                </div>

                <div class="form-group mb-3">
                    <label for="accessibility">File Accessibility</label>
                    <select class="form-control" id="accessibility" name="accessibility" required>
                        <option value="public">Public</option>
                        <option value="admin">Admin Only</option>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label for="start_date">Start Date (Viewable From)</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ date('Y-m-d') }}" required>
                </div>

                <div class="form-group mb-3">
                    <label for="end_date">Last Viewable Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="9999-12-31">
                </div>

                <div class="form-group mb-3">
                    <label for="files">Select Files to Upload</label>
                    <input type="file" class="form-control" id="files" name="files[]" multiple required>
                </div>

                <button type="submit" class="btn btn-primary btn-block mt-3">Upload Files</button>
            </form>
        </div>
    </div>
</div>
@endsection

 
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    @if(session('success'))
        toastr.success("{{ session('success') }}");
    @endif
    @if(session('error'))
        toastr.error("{{ session('error') }}");
    @endif
</script>
 