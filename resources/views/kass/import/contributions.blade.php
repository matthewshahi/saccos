@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <div class="card">
                <div class="card-header">
                    <strong>Import Contributions (Fresh)</strong>
                </div>

                <div class="card-body">
                    <form method="POST" 
                          action="{{ route('kass.import.contributions.run') }}" 
                          enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Contribution Excel File</label>
                            <input type="file" name="file" class="form-control" required>
                        </div>

                        <div class="alert alert-warning">
                            <strong>Warning:</strong> This will clear and re-import
                            <b>contributions only</b>. Loans are untouched.
                        </div>

                        <button class="btn btn-primary">
                            Import Contributions
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
