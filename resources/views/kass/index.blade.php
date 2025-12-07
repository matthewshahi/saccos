@extends('layouts.app')

@section('content')
<div class="container py-4">

    <h2 class="mb-4">📦 KASS SACCO Data Migration Tool</h2>
    <p class="text-muted">Upload raw Excel/CSV files, choose the file, then run the import into staging.</p>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">

        {{-- =======================================
             IMPORT ALL (AUTO-PROCESS MODE)
        ======================================== --}}
        <div class="col-12">
            <form action="{{ route('kass.process.all') }}" method="GET">
                <button class="btn btn-lg btn-success w-100 my-3">
                    🚀 Import ALL Uploaded Excel Files into Staging
                </button>
            </form>

            @if(session('summary'))
                <div class="card p-3 mt-3">
                    <h5>Import Summary</h5>
                    <ul class="list-group mt-2">
                        @foreach(session('summary') as $item)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $item['file'] }}</span>
                                <strong>{{ $item['status'] }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- ===========================
             UPLOAD SINGLE FILE
        ============================ --}}
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    Upload Single File (XLS / XLSX / CSV / TXT)
                </div>
                <div class="card-body">
                    <form action="{{ route('kass.upload.single') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <label class="fw-bold">Choose File</label>
                        <input type="file" name="file" class="form-control mb-3" required>
                        <button class="btn btn-primary w-100">Upload File</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ===========================
             UPLOAD ZIP FILES
        ============================ --}}
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    Upload Batch ZIP (Multiple Companies)
                </div>
                <div class="card-body">
                    <form action="{{ route('kass.upload.batch') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <label class="fw-bold">Choose ZIP File</label>
                        <input type="file" name="zip_file" class="form-control mb-3" required>
                        <button class="btn btn-warning w-100">Upload ZIP</button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    {{-- ===========================
         IMPORT SELECTED FILE
    ============================ --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white">
            Step 3 — Import One Specific File
        </div>
        <div class="card-body">

            <form action="{{ route('kass.process') }}" method="POST">
                @csrf

                <input type="hidden" id="file_path" name="file_path">

                <div class="mb-3">
                    <label class="fw-bold">Selected File Path</label>
                    <input type="text" class="form-control" id="file_path_display"
                           placeholder="Click 'Use This File' from the list below" readonly>
                </div>

                <button class="btn btn-success w-100 py-2">
                    🚀 Start Import of This File
                </button>
            </form>
        </div>
    </div>

    {{-- ===========================
         RECENTLY UPLOADED FILES
    ============================ --}}
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            Recently Uploaded Files (Last 20)
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>File Name</th>
                        <th>Storage Path</th>
                        <th>Uploaded</th>
                        <th>Select</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($files as $file)
                        <tr>
                            <td>{{ $file['name'] }}</td>
                            <td><code>{{ $file['path'] }}</code></td>
                            <td>{{ $file['time'] }}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary"
                                    onclick="selectFile('{{ $file['path'] }}')">
                                    Use This File
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">
                                No uploaded files found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-end mt-3">
        <a href="{{ route('kass.staging') }}" class="btn btn-dark">View Staging Data</a>
    </div>

</div>

<script>
function selectFile(path) {
    document.getElementById('file_path').value = path;
    document.getElementById('file_path_display').value = path;
}
</script>

@endsection
