@extends('layouts.app')

@section('content')

    <div class="breadcrumb d-flex justify-content-between align-items-center">

        <div>
            <h1>Member Roles / Classifications</h1>

            <ul>
                <li>
                    <a href="{{ route('members.edit', $member->member_id) }}">
                        {{ $member->member_name }}
                    </a>
                </li>

                <li>Roles / Classifications</li>
            </ul>
        </div>

        <div class="header-part-right">

            <a
                href="{{ route('members.edit', $member->member_id) }}"
                class="btn btn-outline-secondary"
            >
                Back to Member
            </a>

        </div>

    </div>

    <div class="separator-breadcrumb border-top"></div>

    <div class="row mb-4">

        <div class="col-md-12">

            <div class="card mb-4">

                <div class="card-body">

                    <div class="row">

                        <div class="col-md-8">

                            <h4 class="mb-1">
                                {{ $member->member_name }}
                            </h4>

                            <p class="text-muted mb-0">

                                @if (!empty($member->member_sacco_id))
                                    SACCO No:
                                    <strong>
                                        {{ $member->member_sacco_id }}
                                    </strong>
                                @endif

                                @if (!empty($member->member_national_id))
                                    <span class="mx-2">|</span>

                                    ID/Passport:
                                    <strong>
                                        {{ $member->member_national_id }}
                                    </strong>
                                @endif

                            </p>

                        </div>

                        <div class="col-md-4 text-md-end mt-3 mt-md-0">

                            <span class="badge bg-primary p-2">
                                {{ $currentClassifications->count() }}
                                Current
                                {{ $currentClassifications->count() === 1 ? 'Role' : 'Roles' }}
                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <div class="row">

        <div class="col-md-12">

            <div class="card mb-4">

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
                                        <th style="width: 60px;">#</th>
                                        <th>Role / Classification</th>
                                        <th>Category</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Notes</th>
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

                                                <div class="text-muted text-small">
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

                    @endif

                </div>

            </div>

        </div>

    </div>

    @if ($previousClassifications->isNotEmpty())

        <div class="row">

            <div class="col-md-12">

                <div class="card mb-4">

                    <div class="card-body">

                        <div class="card-title mb-3">
                            Previous / Inactive Roles
                        </div>

                        <div class="table-responsive">

                            <table class="table table-bordered table-hover">

                                <thead>
                                    <tr>
                                        <th style="width: 60px;">#</th>
                                        <th>Role / Classification</th>
                                        <th>Category</th>
                                        <th>From</th>
                                        <th>To</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    @foreach ($previousClassifications as $classification)

                                        <tr>

                                            <td>
                                                {{ $loop->iteration }}
                                            </td>

                                            <td>
                                                {{ $classification->classification_name }}
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
