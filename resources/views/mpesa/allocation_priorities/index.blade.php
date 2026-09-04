@extends('layouts.app')

@section('content')

<div class="main-content">

    {{-- ========================================================= --}}
    {{-- BREADCRUMB --}}
    {{-- ========================================================= --}}

    <div class="breadcrumb">

        <h1>M-PESA Smart Allocation</h1>

        <ul>

            <li>
                <a href="{{ route('dashboard') }}">
                    Dashboard
                </a>
            </li>

            <li>
                Allocation Priority
            </li>

        </ul>

    </div>

    <div class="separator-breadcrumb border-top"></div>


    {{-- ========================================================= --}}
    {{-- FLASH MESSAGES --}}
    {{-- ========================================================= --}}

    @if (session('success'))

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            {{ session('success') }}

            <button
                type="button"
                class="close"
                data-dismiss="alert"
                aria-label="Close"
            >
                <span aria-hidden="true">
                    &times;
                </span>
            </button>

        </div>

    @endif


    @if (session('error'))

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            {{ session('error') }}

            <button
                type="button"
                class="close"
                data-dismiss="alert"
                aria-label="Close"
            >
                <span aria-hidden="true">
                    &times;
                </span>
            </button>

        </div>

    @endif


    @if ($errors->any())

        <div class="alert alert-danger">

            <strong>
                Unable to complete the request.
            </strong>

            <ul class="mb-0 mt-2">

                @foreach ($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- MODULE DESCRIPTION --}}
    {{-- ========================================================= --}}

    <div class="row">

        <div class="col-md-12">

            <div class="card mb-4">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-start flex-wrap"
                    >

                        <div>

                            <div class="card-title mb-3">
                                Allocation Priority
                            </div>

                            <p class="mb-2">

                                Set the order in which the SACCO will later
                                consider products when an incoming M-PESA
                                payment cannot be allocated using a valid
                                payment reference.

                            </p>

                            <p class="text-muted mb-0">

                                A valid payment code supplied by the member
                                will always override this priority list.

                            </p>

                        </div>


                        <div class="mt-3 mt-md-0">

                            <form
                                method="POST"
                                action="{{ route(
                                    'mpesa.allocation.priorities.sync'
                                ) }}"
                                class="m-0"
                            >

                                @csrf

                                <button
                                    type="submit"
                                    class="btn btn-primary btn-sm"
                                >
                                    <i class="i-Refresh me-1"></i>
                                    Refresh Products
                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- ACCOUNTING READINESS --}}
    {{-- ========================================================= --}}

    <div class="row">


        {{-- M-PESA INCOMING --}}

        <div class="col-lg-3 col-md-6 mb-4">

            <div class="card h-100">

                <div class="card-body">

                    <div class="card-title mb-3">
                        M-PESA Incoming
                    </div>

                    @if ($readiness['mpesa']['ledger_ready'])

                        <span class="badge badge-success">
                            Ready
                        </span>

                        <div class="small text-muted mt-2">

                            Ledger ID:

                            <strong>
                                {{ $readiness['mpesa']['ledger_account'] }}
                            </strong>

                        </div>

                    @else

                        <span class="badge badge-danger">
                            Missing Ledger
                        </span>

                        <div class="small text-danger mt-2">
                            default_mpesa_in_account
                        </div>

                    @endif

                </div>

            </div>

        </div>


        {{-- CAPITAL --}}

        <div class="col-lg-3 col-md-6 mb-4">

            <div class="card h-100">

                <div class="card-body">

                    <div class="card-title mb-3">
                        Capital
                    </div>

                    <div class="mb-2">

                        Minimum:

                        <strong>
                            KES
                            {{ number_format(
                                $readiness['capital']['required_amount'],
                                2
                            ) }}
                        </strong>

                    </div>


                    @if ($readiness['capital']['ledger_ready'])

                        <span class="badge badge-success">
                            Ready
                        </span>

                        <div class="small text-muted mt-2">

                            Ledger ID:

                            <strong>
                                {{ $readiness['capital']['ledger_account'] }}
                            </strong>

                        </div>

                    @else

                        <span class="badge badge-danger">
                            Missing Ledger
                        </span>

                        <div class="small text-danger mt-2">
                            default_share_capital_account
                        </div>

                    @endif

                </div>

            </div>

        </div>


        {{-- REGISTRATION FEE --}}

        <div class="col-lg-3 col-md-6 mb-4">

            <div class="card h-100">

                <div class="card-body">

                    <div class="card-title mb-3">
                        Registration Fee
                    </div>

                    <div class="mb-2">

                        Required:

                        <strong>
                            KES
                            {{ number_format(
                                $readiness['registration_fee']['required_amount'],
                                2
                            ) }}
                        </strong>

                    </div>


                    @if ($readiness['registration_fee']['ledger_ready'])

                        <span class="badge badge-success">
                            Ready
                        </span>

                        <div class="small text-muted mt-2">

                            Ledger ID:

                            <strong>
                                {{ $readiness['registration_fee']['ledger_account'] }}
                            </strong>

                        </div>

                    @else

                        <span class="badge badge-danger">
                            Missing Ledger
                        </span>

                        <div class="small text-danger mt-2">
                            default_member_ship_fee_account
                        </div>

                    @endif

                </div>

            </div>

        </div>


        {{-- SAVINGS --}}

        <div class="col-lg-3 col-md-6 mb-4">

            <div class="card h-100">

                <div class="card-body">

                    <div class="card-title mb-3">
                        Savings / Deposits
                    </div>


                    @if ($readiness['shares']['ledger_ready'])

                        <span class="badge badge-success">
                            Ready
                        </span>

                        <div class="small text-muted mt-2">

                            Ledger ID:

                            <strong>
                                {{ $readiness['shares']['ledger_account'] }}
                            </strong>

                        </div>

                    @else

                        <span class="badge badge-danger">
                            Missing Ledger
                        </span>

                        <div class="small text-danger mt-2">
                            default_share_account
                        </div>

                    @endif

                </div>

            </div>

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- PRIORITY TABLE --}}
    {{-- ========================================================= --}}

    <div class="row">

        <div class="col-md-12">

            <div class="card mb-4">

                <div
                    class="card-header d-flex justify-content-between align-items-center flex-wrap"
                >

                    <div>

                        <h3 class="card-title m-0">
                            Product Priority
                        </h3>

                        <small class="text-muted">
                            The first item is considered first.
                        </small>

                    </div>


                    <div class="mt-2 mt-md-0">

                        <span class="badge badge-primary">

                            {{ $priorities->count() }}

                            {{ $priorities->count() === 1
                                ? 'Product'
                                : 'Products'
                            }}

                        </span>

                    </div>

                </div>


                <div class="card-body">

                    <div class="table-responsive">

                        <table
                            class="table table-bordered table-striped table-hover align-middle"
                        >

                            <thead class="table-light">

                                <tr>

                                    <th
                                        style="width:90px;"
                                        class="text-center"
                                    >
                                        Priority
                                    </th>

                                    <th style="min-width:250px;">
                                        Product
                                    </th>

                                    <th style="width:160px;">
                                        Family
                                    </th>

                                    <th style="width:130px;">
                                        Code
                                    </th>

                                    <th style="min-width:250px;">
                                        Details
                                    </th>

                                    <th style="width:180px;">
                                        Product Status
                                    </th>

                                    <th
                                        style="width:160px;"
                                        class="text-center"
                                    >
                                        Move
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                @forelse (
                                    $priorities
                                    as
                                    $index => $priority
                                )

                                    <tr>


                                        {{-- PRIORITY --}}

                                        <td
                                            class="text-center fw-bold"
                                            style="font-size:18px;"
                                        >

                                            {{ $priority->priority_order }}

                                        </td>


                                        {{-- PRODUCT --}}

                                        <td>

                                            <div class="fw-bold">

                                                {{ $priority->display_name }}

                                            </div>


                                            @if (
                                                $priority->priority_source_id
                                            )

                                                <div class="small text-muted mt-1">

                                                    Source ID:

                                                    {{ $priority->priority_source_id }}

                                                </div>

                                            @endif

                                        </td>


                                        {{-- FAMILY --}}

                                        <td>

                                            @if (
                                                $priority->priority_type
                                                ===
                                                'LOAN_TYPE'
                                            )

                                                <span class="badge badge-danger">
                                                    Loan
                                                </span>

                                            @elseif (
                                                $priority->priority_type
                                                ===
                                                'FOSA_TYPE'
                                            )

                                                <span class="badge badge-info">
                                                    FOSA
                                                </span>

                                            @elseif (
                                                $priority->priority_type
                                                ===
                                                'SPECIAL_SAVING_PRODUCT'
                                            )

                                                <span class="badge badge-warning">
                                                    Special Savings
                                                </span>

                                            @else

                                                <span class="badge badge-primary">
                                                    Core
                                                </span>

                                            @endif

                                        </td>


                                        {{-- CODE --}}

                                        <td>

                                            @if (
                                                $priority->display_code
                                            )

                                                <code
                                                    style="
                                                        font-size:14px;
                                                        font-weight:700;
                                                    "
                                                >

                                                    {{ $priority->display_code }}

                                                </code>

                                            @else

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            @endif

                                        </td>


                                        {{-- DETAILS --}}

                                        <td>

                                            <span class="text-muted">

                                                {{ $priority->details }}

                                            </span>

                                        </td>


                                        {{-- PRODUCT STATUS --}}

                                        <td>

                                            @if (
                                                $priority->source_active
                                            )

                                                <span class="badge badge-success">

                                                    {{ $priority->source_status }}

                                                </span>

                                            @else

                                                <span class="badge badge-secondary">

                                                    {{ $priority->source_status }}

                                                </span>

                                            @endif

                                        </td>


                                        {{-- MOVE --}}

                                        <td class="text-center">

                                            <div
                                                class="d-flex justify-content-center"
                                                style="gap:6px;"
                                            >


                                                {{-- MOVE UP --}}

                                                <form
                                                    method="POST"
                                                    action="{{ route(
                                                        'mpesa.allocation.priorities.move',
                                                        $priority->priority_id
                                                    ) }}"
                                                    class="m-0"
                                                >

                                                    @csrf

                                                    <input
                                                        type="hidden"
                                                        name="direction"
                                                        value="up"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-primary btn-sm"
                                                        title="Move up"
                                                        @if ($index === 0)
                                                            disabled
                                                        @endif
                                                    >

                                                        <i class="i-Arrow-Up"></i>

                                                        ↑

                                                    </button>

                                                </form>


                                                {{-- MOVE DOWN --}}

                                                <form
                                                    method="POST"
                                                    action="{{ route(
                                                        'mpesa.allocation.priorities.move',
                                                        $priority->priority_id
                                                    ) }}"
                                                    class="m-0"
                                                >

                                                    @csrf

                                                    <input
                                                        type="hidden"
                                                        name="direction"
                                                        value="down"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-primary btn-sm"
                                                        title="Move down"
                                                        @if (
                                                            $index
                                                            ===
                                                            $priorities->count() - 1
                                                        )
                                                            disabled
                                                        @endif
                                                    >

                                                        <i class="i-Arrow-Down"></i>

                                                        ↓

                                                    </button>

                                                </form>


                                            </div>

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td
                                            colspan="7"
                                            class="text-center text-muted py-4"
                                        >

                                            No allocation products were found.

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


    {{-- ========================================================= --}}
    {{-- STAGE 1 NOTICE --}}
    {{-- ========================================================= --}}

    <div class="row">

        <div class="col-md-12">

            <div class="alert alert-info">

                <strong>
                    Stage 1 — Configuration only.
                </strong>

                This priority list does not yet change how incoming
                M-PESA transactions are posted.

            </div>

        </div>

    </div>

</div>

@endsection