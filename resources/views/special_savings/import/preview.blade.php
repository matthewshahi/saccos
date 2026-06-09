@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Import Preview</h1>
        <ul>
            <li><a href="{{ route('special_savings.import.form') }}">Import</a></li>
            <li>Preview</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Preview Rows</div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    @foreach($header as $column)
                                        <th>{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($preview as $row)
                                    <tr>
                                        @foreach($header as $column)
                                            <td>{{ $row[$column] ?? '' }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($header) }}" class="text-center text-muted">No rows found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('special_savings.import.process') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-primary" onclick="return confirm('Process this import?')">Process Import</button>
                    </form>

                    <form method="POST" action="{{ route('special_savings.import.cancel') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-outline-danger">Cancel Import</button>
                    </form>

                    <a href="{{ route('special_savings.import.form') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection