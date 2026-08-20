@extends('layouts.app')

@section('content')

    <div class="breadcrumb d-flex justify-content-between align-items-start flex-wrap">

        <div>
            <h1 class="mb-1">
                Member Roles / Classifications
            </h1>

            <ul>
                <li>
                    <a href="{{ route('members.listing') }}">
                        Members
                    </a>
                </li>

                <li>
                    <a href="{{ route('members.edit', $member->member_id) }}">
                        {{ $member->member_name }}
                    </a>
                </li>

                <li>
                    Roles / Classifications
                </li>
            </ul>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">

            <a
                href="{{ route('members.listing') }}"
                class="btn btn-outline-secondary"
            >
                <i class="i-Business-Mens me-1"></i>
                All Members
            </a>

            <a
                href="{{ route('members.edit', $member->member_id) }}"
                class="btn btn-outline-primary"
            >
                <i class="i-Pen-2 me-1"></i>
                Edit Member
            </a>

            <a
                href="{{ route('members.edit.image', $member->member_id) }}"
                class="btn btn-outline-primary"
            >
                <i class="i-File-Pictures me-1"></i>
                Member Documents
            </a>

            <a
                href="{{ route('members.classifications.index') }}"
                class="btn btn-outline-dark"
            >
                <i class="i-Settings-Window me-1"></i>
                Manage Classifications
            </a>

            <a
                href="{{ route(
                    'members.classifications.create',
                    ['member_id' => $member->member_id]
                ) }}"
                class="btn btn-primary"
            >
                <i class="i-Add me-1"></i>
                Add New Classification
            </a>

        </div>

    </div>

    <div class="separator-breadcrumb border-top"></div>

    @if (session('success'))
        <div class="alert alert-success">
            <strong>Success:</strong>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please correct the following:</strong>

            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>
                        {{ $error }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- MEMBER SUMMARY --}}
    <div class="row mb-4">

        <div class="col-md-12">

            <div class="card">

                <div class="card-body">

                    <div class="row align-items-center">

                        <div class="col-md-8">

                            <h4 class="mb-2">
                                {{ $member->member_name }}
                            </h4>

                            <div class="text-muted">

                                @if (!empty($member->member_sacco_id))
                                    SACCO No:
                                    <strong>
                                        {{ $member->member_sacco_id }}
                                    </strong>
                                @endif

                                @if (!empty($member->member_national_id))
                                    <span class="mx-2">
                                        |
                                    </span>

                                    ID/Passport:
                                    <strong>
                                        {{ $member->member_national_id }}
                                    </strong>
                                @endif

                            </div>

                        </div>

                        <div class="col-md-4 text-md-end mt-3 mt-md-0">

                            @if ($currentClassifications->count() > 0)

                                <span class="badge bg-success p-2">
                                    {{ $currentClassifications->count() }}
                                    Current
                                    {{
                                        $currentClassifications->count() === 1
                                            ? 'Role'
                                            : 'Roles'
                                    }}
                                </span>

                            @else

                                <span class="badge bg-secondary p-2">
                                    0 Current Roles
                                </span>

                            @endif

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- ASSIGN A ROLE --}}
    <div class="row mb-4" id="assign-role">

        <div class="col-md-12">

            <div class="card">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">

                        <div>
                            <div class="card-title mb-1">
                                Assign Role / Classification
                            </div>

                            <p class="text-muted mb-0">
                                A member may hold several roles at the same time.
                            </p>
                        </div>

                        <a
                            href="{{ route(
                                'members.classifications.create',
                                ['member_id' => $member->member_id]
                            ) }}"
                            class="btn btn-sm btn-outline-primary mt-2 mt-md-0"
                        >
                            <i class="i-Add me-1"></i>
                            Create New Classification
                        </a>

                    </div>

                    @if ($availableClassifications->isEmpty())

                        <div class="alert alert-info mb-0">
                            All currently active classifications have already been assigned to this member.
                        </div>

                    @else

                        <form
                            method="POST"
                            action="{{ route(
                                'members.classifications.assign',
                                $member->member_id
                            ) }}"
                        >

                            @csrf

                            <div class="row">

                                <div class="col-md-6 form-group mb-3">

                                    <label for="classification_id">
                                        Role / Classification *
                                    </label>

                                    <select
                                        name="classification_id"
                                        id="classification_id"
                                        class="form-control"
                                        required
                                    >

                                        <option value="">
                                            -- Select Role / Classification --
                                        </option>

                                        @foreach ($availableByCategory as $category => $categoryClassifications)

                                            <optgroup
                                                label="{{ str_replace('_', ' ', $category) }}"
                                            >

                                                @foreach ($categoryClassifications as $classification)

                                                    <option
                                                        value="{{ $classification->classification_id }}"
                                                        {{
                                                            (string) old('classification_id') ===
                                                            (string) $classification->classification_id
                                                                ? 'selected'
                                                                : ''
                                                        }}
                                                    >
                                                        {{ $classification->classification_name }}
                                                    </option>

                                                @endforeach

                                            </optgroup>

                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-3 form-group mb-3">

                                    <label for="classification_date_from">
                                        Start Date
                                    </label>

                                    <input
                                        type="date"
                                        name="classification_date_from"
                                        id="classification_date_from"
                                        class="form-control"
                                        value="{{ old(
                                            'classification_date_from',
                                            now()->format('Y-m-d')
                                        ) }}"
                                    >

                                </div>

                                <div class="col-md-3 form-group mb-3">

                                    <label for="classification_date_to">
                                        End Date
                                    </label>

                                    <input
                                        type="date"
                                        name="classification_date_to"
                                        id="classification_date_to"
                                        class="form-control"
                                        value="{{ old('classification_date_to') }}"
                                    >

                                    <small class="text-muted">
                                        Leave blank for an ongoing role.
                                    </small>

                                </div>

                                <div class="col-md-12 form-group mb-3">

                                    <label for="classification_member_notes">
                                        Notes
                                    </label>

                                    <textarea
                                        name="classification_member_notes"
                                        id="classification_member_notes"
                                        class="form-control"
                                        rows="3"
                                        maxlength="5000"
                                        placeholder="Optional appointment, election or role notes"
                                    >{{ old('classification_member_notes') }}</textarea>

                                </div>

                                <div class="col-md-12">

                                    <button
                                        type="submit"
                                        class="btn btn-success"
                                    >
                                        <i class="i-Add-User me-1"></i>
                                        Assign Role to Member
                                    </button>

                                </div>

                            </div>

                        </form>

                    @endif

                </div>

            </div>

        </div>

    </div>


    {{-- CURRENT ROLES --}}
    <div class="row mb-4">

        <div class="col-md-12">

            <div class="card">

                <div class="card-body">

                    <div class="card-title mb-3">
                        Current Roles / Classifications
                    </div>

                    @if ($currentClassifications->isEmpty())

                        <div class="alert alert-info mb-0">
                            This member currently has no active roles or classifications assigned.
                        </div>

                    @else

                        <div class="table-responsive">

                            <table class="table table-bordered table-hover">

                                <thead>
                                    <tr>
                                        <th style="width: 60px;">
                                            #
                                        </th>

                                        <th>
                                            Role / Classification
                                        </th>

                                        <th>
                                            Category / Group
                                        </th>

                                        <th style="width: 130px;">
                                            From
                                        </th>

                                        <th style="width: 130px;">
                                            To
                                        </th>

                                        <th>
                                            Notes
                                        </th>

                                        <th style="width: 130px;">
                                            Action
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>

                                    @foreach ($currentClassifications as $classification)

                                        <tr>

                                            <td>
                                                {{ $loop->iteration }}
                                            </td>

                                            <td>

                                                <strong>
                                                    {{ $classification->classification_name }}
                                                </strong>

                                                <div class="text-muted text-small mt-1">
                                                    {{ $classification->classification_code }}
                                                </div>

                                            </td>

                                            <td>

                                                @if ($classification->classification_category)

                                                    <span class="badge bg-light text-dark">
                                                        {{ str_replace(
                                                            '_',
                                                            ' ',
                                                            $classification->classification_category
                                                        ) }}
                                                    </span>

                                                @else
                                                    —
                                                @endif

                                            </td>

                                            <td>
                                                {{ $classification->classification_date_from ?: '—' }}
                                            </td>

                                            <td>
                                                {{ $classification->classification_date_to ?: 'Ongoing' }}
                                            </td>

                                            <td>
                                                {{ $classification->classification_member_notes ?: '—' }}
                                            </td>

                                            <td>

                                                <form
                                                    method="POST"
                                                    action="{{ route(
                                                        'members.classifications.end',
                                                        [
                                                            'member_id' => $member->member_id,
                                                            'assignment_id' => $classification->member_classification_id
                                                        ]
                                                    ) }}"
                                                    onsubmit="return confirm('End this role for this member?');"
                                                >

                                                    @csrf
                                                    @method('PATCH')

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                    >
                                                        End Role
                                                    </button>

                                                </form>

                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @endif

                </div>

            </div>

        </div>

    </div>


    {{-- PREVIOUS ROLES --}}
    @if ($previousClassifications->isNotEmpty())

        <div class="row mb-4">

            <div class="col-md-12">

                <div class="card">

                    <div class="card-body">

                        <div class="card-title mb-3">
                            Previous / Inactive Roles
                        </div>

                        <div class="table-responsive">

                            <table class="table table-bordered table-hover">

                                <thead>

                                    <tr>
                                        <th style="width: 60px;">
                                            #
                                        </th>

                                        <th>
                                            Role / Classification
                                        </th>

                                        <th>
                                            Category / Group
                                        </th>

                                        <th>
                                            From
                                        </th>

                                        <th>
                                            To
                                        </th>

                                        <th>
                                            Notes
                                        </th>
                                    </tr>

                                </thead>

                                <tbody>

                                    @foreach ($previousClassifications as $classification)

                                        <tr>

                                            <td>
                                                {{ $loop->iteration }}
                                            </td>

                                            <td>

                                                <strong>
                                                    {{ $classification->classification_name }}
                                                </strong>

                                                <div class="text-muted text-small">
                                                    {{ $classification->classification_code }}
                                                </div>

                                            </td>

                                            <td>

                                                {{ str_replace(
                                                    '_',
                                                    ' ',
                                                    $classification->classification_category ?? ''
                                                ) ?: '—' }}

                                            </td>

                                            <td>
                                                {{ $classification->classification_date_from ?: '—' }}
                                            </td>

                                            <td>
                                                {{ $classification->classification_date_to ?: '—' }}
                                            </td>

                                            <td>
                                                {{ $classification->classification_member_notes ?: '—' }}
                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    @endif

@endsection
