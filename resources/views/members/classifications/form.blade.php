@extends('layouts.app')

@section('content')

    @php
        $isEdit = !empty($classification);
    @endphp

    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <div>
            <h1>
                {{ $isEdit ? 'Edit Member Classification' : 'Add Member Classification' }}
            </h1>

            <ul>
                <li>
                    <a href="{{ route('members.classifications.index') }}">
                        Member Classifications
                    </a>
                </li>

                <li>
                    {{ $isEdit ? 'Edit' : 'Add' }}
                </li>
            </ul>
        </div>

        <div class="header-part-right">

            <a
                href="{{ route('members.classifications.index') }}"
                class="btn btn-outline-secondary"
            >
                Back to Classifications
            </a>

        </div>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    <div class="row">

        <div class="col-md-12">

            <div class="card mb-4">

                <div class="card-body">

                    <div class="card-title mb-3">
                        {{ $isEdit ? 'Classification Details' : 'New Classification' }}
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form
                        method="POST"
                        action="{{
                            $isEdit
                                ? route(
                                    'members.classifications.update',
                                    $classification->classification_id
                                )
                                : route('members.classifications.store')
                        }}"
                    >

                        @csrf

                        @if ($isEdit)
                            @method('PUT')
                        @endif

                        <div class="row">

                            <div class="col-md-6 form-group mb-3">

                                <label for="classification_name">
                                    Classification / Role Name *
                                </label>

                                <input
                                    type="text"
                                    name="classification_name"
                                    id="classification_name"
                                    class="form-control"
                                    required
                                    maxlength="150"
                                    value="{{ old(
                                        'classification_name',
                                        $classification->classification_name ?? ''
                                    ) }}"
                                    placeholder="e.g. Credit Committee Chairperson"
                                >

                            </div>

                            <div class="col-md-6 form-group mb-3">

                                <label for="classification_code">
                                    Classification Code *
                                </label>

                                <input
                                    type="text"
                                    name="classification_code"
                                    id="classification_code"
                                    class="form-control"
                                    required
                                    maxlength="120"
                                    value="{{ old(
                                        'classification_code',
                                        $classification->classification_code ?? ''
                                    ) }}"
                                    placeholder="e.g. CREDIT_COMMITTEE_CHAIRPERSON"
                                >

                                <small class="text-muted">
                                    Codes are automatically normalised to uppercase with underscores.
                                </small>

                            </div>

                            <div class="col-md-6 form-group mb-3">

                                <label for="classification_category">
                                    Category
                                </label>

                                <input
                                    type="text"
                                    name="classification_category"
                                    id="classification_category"
                                    class="form-control"
                                    maxlength="120"
                                    list="classification-categories"
                                    value="{{ old(
                                        'classification_category',
                                        $classification->classification_category ?? ''
                                    ) }}"
                                    placeholder="e.g. CREDIT_COMMITTEE"
                                >

                                <datalist id="classification-categories">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">
                                    @endforeach
                                </datalist>

                                <small class="text-muted">
                                    Category is for grouping and display only. It does not restrict membership.
                                </small>

                            </div>

                            <div class="col-md-3 form-group mb-3">

                                <label for="classification_active">
                                    Status *
                                </label>

                                <select
                                    name="classification_active"
                                    id="classification_active"
                                    class="form-control"
                                    required
                                >
                                    <option
                                        value="Y"
                                        {{
                                            old(
                                                'classification_active',
                                                $classification->classification_active ?? 'Y'
                                            ) === 'Y'
                                                ? 'selected'
                                                : ''
                                        }}
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="N"
                                        {{
                                            old(
                                                'classification_active',
                                                $classification->classification_active ?? 'Y'
                                            ) === 'N'
                                                ? 'selected'
                                                : ''
                                        }}
                                    >
                                        Inactive
                                    </option>
                                </select>

                            </div>

                            <div class="col-md-3 form-group mb-3">

                                <label for="classification_sort_order">
                                    Display Order *
                                </label>

                                <input
                                    type="number"
                                    name="classification_sort_order"
                                    id="classification_sort_order"
                                    class="form-control"
                                    required
                                    min="0"
                                    max="100000"
                                    value="{{ old(
                                        'classification_sort_order',
                                        $classification->classification_sort_order ?? 0
                                    ) }}"
                                >

                            </div>

                            <div class="col-md-12 form-group mb-3">

                                <label for="classification_description">
                                    Description
                                </label>

                                <textarea
                                    name="classification_description"
                                    id="classification_description"
                                    class="form-control"
                                    rows="4"
                                    maxlength="5000"
                                    placeholder="Optional description of this role"
                                >{{ old(
                                    'classification_description',
                                    $classification->classification_description ?? ''
                                ) }}</textarea>

                            </div>

                            <div class="col-md-12">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    {{ $isEdit ? 'Update Classification' : 'Add Classification' }}
                                </button>

                                <a
                                    href="{{ route('members.classifications.index') }}"
                                    class="btn btn-outline-secondary"
                                >
                                    Cancel
                                </a>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

@endsection
