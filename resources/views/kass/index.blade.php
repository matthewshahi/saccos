@extends('layouts.app')

@section('content')
<div class="container">

    <h2>KASS SACCO Migration Tool</h2>
    <p>Upload individual CSV files or a ZIP containing multiple sheets.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Upload Single --}}
    <form action="{{ route('kass.upload.single') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <label>Upload Savings/Loans CSV</label>
        <input type="file" name="file" class="form-control" required>
        <button class="btn btn-primary mt-2">Upload Single File</button>
    </form>

    <hr>

    {{-- Upload Batch Zip --}}
    <form action="{{ route('kass.upload.batch') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <label>Upload ZIP of all company files</label>
        <input type="file" name="zip_file" class="form-control" required>
        <button class="btn btn-warning mt-2">Upload Batch</button>
    </form>

    <hr>

    {{-- Process --}}
    <form action="{{ route('kass.process') }}" method="POST">
        @csrf
        <label>File Path (optional)</label>
        <input type="text" name="file_path" class="form-control" placeholder="storage/kass_uploads/file.csv">

        <label class="mt-2">Folder Path (optional)</label>
        <input type="text" name="folder_path" class="form-control" placeholder="/full/path/to/extracted/batch">

        <button class="btn btn-success mt-3">Process File(s)</button>
    </form>

    <hr>

    <a href="{{ route('kass.staging') }}" class="btn btn-dark">View Staging Tables</a>

    <hr>

<h4>Recently Uploaded Files</h4>

<table class="table table-bordered table-sm">
    <thead>
        <tr>
            <th>File Name</th>
            <th>Full Path</th>
            <th>Uploaded</th>
            <th>Select</th>
        </tr>
    </thead>
    <tbody>
        @forelse($files as $file)
            <tr>
                <td>{{ $file['name'] }}</td>
                <td>
                    <code>{{ 'storage/' . $file['path'] }}</code>
                </td>
                <td>{{ $file['time'] }}</td>
                <td>
                    <button class="btn btn-sm btn-primary"
                        onclick="document.getElementById('file_path').value = '{{ $file['path'] }}'">
                        Use This File
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4">No uploaded files.</td>
            </tr>
        @endforelse
    </tbody>
</table>



</div>


@endsection
