@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Special Saving Categories</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li>Categories</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="card-title mb-0">Categories</div>
                        <a href="{{ route('special_savings.categories.create') }}" class="btn btn-primary btn-sm">Add Category</a>
                    </div>

                    <form method="GET" class="row mb-3">
                        <div class="col-md-4 form-group mb-2">
                            <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Search category">
                        </div>
                        <div class="col-md-2 form-group mb-2">
                            <button class="btn btn-secondary btn-block">Search</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Liability Ledger</th>
                                    <th style="width:180px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $row)
                                    <tr>
                                        <td>{{ $row->special_saving_category_name }}</td>
                                        <td>{{ $row->special_saving_category_code }}</td>
                                        <td>{{ $row->special_saving_category_status }}</td>
                                        <td>{{ $row->special_saving_liability_sub_account_id }}</td>
                                        <td>
                                            <a href="{{ route('special_savings.categories.edit', $row->special_saving_category_id) }}" class="btn btn-sm btn-primary">Edit</a>
                                            <form action="{{ route('special_savings.categories.toggle', $row->special_saving_category_id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary">Toggle</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No categories found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $records->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection