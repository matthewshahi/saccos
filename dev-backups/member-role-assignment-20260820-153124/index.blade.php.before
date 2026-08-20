@extends('layouts.app')

@section('content')

    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <div>
            <h1>Member Classifications</h1>
            <ul>
                <li>Members</li>
                <li>Roles / Classifications</li>
            </ul>
        </div>

        <div class="header-part-right">
            <a
                href="{{ route('members.classifications.create') }}"
                class="btn btn-primary"
            >
                <i class="i-Add"></i>
                Add Classification
            </a>
        </div>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">

        <div class="col-md-12">

            <div class="card mb-4">
                <div class="card-body">

                    <div class="card-title mb-3">
                        Classification Catalogue
                    </div>

                    <form
                        method="GET"
                        action="{{ route('members.classifications.index') }}"
                        class="mb-4"
                    >

                        <div class="row">

                            <div class="col-md-5 form-group mb-3">
                                <label for="search">
                                    Search
                                </label>

                                <input
                                    type="text"
                                    name="search"
                                    id="search"
                                    class="form-control"
                                    value="{{ request('search') }}"
                                    placeholder="Name, code, category or description"
                                >
                            </div>

                            <div class="col-md-3 form-group mb-3">
                                <label for="category">
                                    Category
                                </label>

                                <select
                                    name="category"
                                    id="category"
                                    class="form-control"
                                >
                                    <option value="">
                                        All Categories
                                    </option>

                                    @foreach ($categories as $category)
                                        <option
                                            value="{{ $category }}"
                                            {{ request('category') === $category ? 'selected' : '' }}
                                        >
                                            {{ str_replace('_', ' ', $category) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2 form-group mb-3">
                                <label for="active">
                                    Status
                                </label>

                                <select
                                    name="active"
                                    id="active"
                                    class="form-control"
                                >
                                    <option value="">
                                        All
                                    </option>

                                    <option
                                        value="Y"
                                        {{ request('active') === 'Y' ? 'selected' : '' }}
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="N"
                                        {{ request('active') === 'N' ? 'selected' : '' }}
                                    >
                                        Inactive
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-2 form-group mb-3 d-flex align-items-end">

                                <button
                                    type="submit"
                                    class="btn btn-primary me-2"
                                >
                                    Filter
                                </button>

                                <a
                                    href="{{ route('members.classifications.index') }}"
                                    class="btn btn-outline-secondary"
                                >
                                    Reset
                                </a>

                            </div>

                        </div>

                    </form>

                    <div class="table-responsive">

                        <table class="table table-hover table-bordered">

                            <thead>
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Classification / Role</th>
                                    <th>Category</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th style="width: 90px;">Order</th>
                                    <th style="width: 100px;">Action</th>
                                </tr>
                            </thead>

                            <tbody>

                                @forelse ($classifications as $classification)

                                    <tr>

                                        <td>
                                            {{ $loop->iteration }}
                                        </td>

                                        <td>
                                            <strong>
                                                {{ $classification->classification_name }}
                                            </strong>

                                            @if (!empty($classification->classification_description))
                                                <div class="text-muted text-small mt-1">
                                                    {{ $classification->classification_description }}
                                                </div>
                                            @endif
                                        </td>

                                        <td>
                                            @if ($classification->classification_category)
                                                <span class="badge bg-light text-dark">
                                                    {{ str_replace('_', ' ', $classification->classification_category) }}
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>

                                        <td>
                                            <code>
                                                {{ $classification->classification_code }}
                                            </code>
                                        </td>

                                        <td>
                                            @if ($classification->classification_active === 'Y')
                                                <span class="badge bg-success">
                                                    Active
                                                </span>
                                            @else
                                                <span class="badge bg-secondary">
                                                    Inactive
                                                </span>
                                            @endif
                                        </td>

                                        <td>
                                            {{ $classification->classification_sort_order }}
                                        </td>

                                        <td>
                                            <a
                                                href="{{ route(
                                                    'members.classifications.edit',
                                                    $classification->classification_id
                                                ) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Edit
                                            </a>
                                        </td>

                                    </tr>

                                @empty

                                    <tr>
                                        <td
                                            colspan="7"
                                            class="text-center text-muted py-4"
                                        >
                                            No classifications found.
                                        </td>
                                    </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>
            </div>

        </div>

    </div>

@endsection
