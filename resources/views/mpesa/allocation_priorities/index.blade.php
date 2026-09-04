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
    {{-- FLASH / AJAX MESSAGE HOLDER --}}
    {{-- ========================================================= --}}

    <div id="allocationPriorityAjaxAlert" style="display:none;"></div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">

            {{ session('success') }}

            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">
                    &times;
                </span>
            </button>

        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">

            {{ session('error') }}

            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
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

                    <div class="d-flex justify-content-between align-items-start flex-wrap">

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
                                action="{{ route('mpesa.allocation.priorities.sync') }}"
                                class="m-0"
                            >
                                @csrf

                                <button type="submit" class="btn btn-primary btn-sm">
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
                            Ledger Account:
                        </div>

                        <div class="fw-bold">
                            {{ $readiness['mpesa']['ledger_account_name'] ?? 'Account not found' }}
                        </div>

                        <div class="small text-muted">
                            ID: {{ $readiness['mpesa']['ledger_account'] }}
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
                            KES {{ number_format($readiness['capital']['required_amount'], 2) }}
                        </strong>
                    </div>

                    @if ($readiness['capital']['ledger_ready'])
                        <span class="badge badge-success">
                            Ready
                        </span>

                        <div class="small text-muted mt-2">
                            Ledger Account:
                        </div>

                        <div class="fw-bold">
                            {{ $readiness['capital']['ledger_account_name'] ?? 'Account not found' }}
                        </div>

                        <div class="small text-muted">
                            ID: {{ $readiness['capital']['ledger_account'] }}
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
                            KES {{ number_format($readiness['registration_fee']['required_amount'], 2) }}
                        </strong>
                    </div>

                    @if ($readiness['registration_fee']['ledger_ready'])
                        <span class="badge badge-success">
                            Ready
                        </span>

                        <div class="small text-muted mt-2">
                            Ledger Account:
                        </div>

                        <div class="fw-bold">
                            {{ $readiness['registration_fee']['ledger_account_name'] ?? 'Account not found' }}
                        </div>

                        <div class="small text-muted">
                            ID: {{ $readiness['registration_fee']['ledger_account'] }}
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
                            Ledger Account:
                        </div>

                        <div class="fw-bold">
                            {{ $readiness['shares']['ledger_account_name'] ?? 'Account not found' }}
                        </div>

                        <div class="small text-muted">
                            ID: {{ $readiness['shares']['ledger_account'] }}
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

                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">

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
                            <span id="priorityProductCount">
                                {{ $priorities->count() }}
                            </span>

                            <span id="priorityProductCountLabel">
                                {{ $priorities->count() === 1 ? 'Product' : 'Products' }}
                            </span>
                        </span>

                    </div>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered table-striped table-hover align-middle">

                            <thead class="table-light">
                                <tr>
                                    <th style="width:90px;" class="text-center">
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

                                    <th style="width:160px;" class="text-center">
                                        Move
                                    </th>
                                </tr>
                            </thead>

                            <tbody id="priorityTableBody">

                                @forelse ($priorities as $index => $priority)

                                    <tr>

                                        <td class="text-center fw-bold" style="font-size:18px;">
                                            {{ $priority->priority_order }}
                                        </td>

                                        <td>

                                            <div class="fw-bold">
                                                {{ $priority->display_name }}
                                            </div>

                                            @if ($priority->priority_source_id)
                                                <div class="small text-muted mt-1">
                                                    Source ID:

                                                    {{ $priority->priority_source_id }}
                                                </div>
                                            @endif

                                        </td>

                                        <td>

                                            @if ($priority->priority_type === 'LOAN_TYPE')
                                                <span class="badge badge-danger">
                                                    Loan
                                                </span>
                                            @elseif ($priority->priority_type === 'FOSA_TYPE')
                                                <span class="badge badge-info">
                                                    FOSA
                                                </span>
                                            @elseif ($priority->priority_type === 'SPECIAL_SAVING_PRODUCT')
                                                <span class="badge badge-warning">
                                                    Special Savings
                                                </span>
                                            @else
                                                <span class="badge badge-primary">
                                                    Core
                                                </span>
                                            @endif

                                        </td>

                                        <td>

                                            @if ($priority->display_code)
                                                <code style="font-size:14px; font-weight:700;">
                                                    {{ $priority->display_code }}
                                                </code>
                                            @else
                                                <span class="text-muted">
                                                    —
                                                </span>
                                            @endif

                                        </td>

                                        <td>
                                            <span class="text-muted">
                                                {{ $priority->details }}
                                            </span>
                                        </td>

                                        <td>

                                            @if ($priority->source_active)
                                                <span class="badge badge-success">
                                                    {{ $priority->source_status }}
                                                </span>
                                            @else
                                                <span class="badge badge-secondary">
                                                    {{ $priority->source_status }}
                                                </span>
                                            @endif

                                        </td>

                                        <td class="text-center">

                                            <div class="d-flex justify-content-center" style="gap:6px;">

                                                <form
                                                    method="POST"
                                                    action="{{ route('mpesa.allocation.priorities.move', $priority->priority_id) }}"
                                                    class="m-0 js-priority-move-form"
                                                >
                                                    @csrf

                                                    <input type="hidden" name="direction" value="up">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-primary btn-sm js-priority-move-btn"
                                                        title="Move up"
                                                        @if ($index === 0) disabled @endif
                                                    >
                                                        <i class="i-Arrow-Up"></i>
                                                        ↑
                                                    </button>

                                                </form>

                                                <form
                                                    method="POST"
                                                    action="{{ route('mpesa.allocation.priorities.move', $priority->priority_id) }}"
                                                    class="m-0 js-priority-move-form"
                                                >
                                                    @csrf

                                                    <input type="hidden" name="direction" value="down">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-primary btn-sm js-priority-move-btn"
                                                        title="Move down"
                                                        @if ($index === $priorities->count() - 1) disabled @endif
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
                                        <td colspan="7" class="text-center text-muted py-4">
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


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.getElementById('priorityTableBody');
        const alertBox = document.getElementById('allocationPriorityAjaxAlert');
        const countBox = document.getElementById('priorityProductCount');
        const countLabelBox = document.getElementById('priorityProductCountLabel');

        if (!tableBody) {
            return;
        }

        document.addEventListener('submit', function (event) {
            const form = event.target.closest('.js-priority-move-form');

            if (!form) {
                return;
            }

            event.preventDefault();

            const button = form.querySelector('.js-priority-move-btn');

            if (button && button.disabled) {
                return;
            }

            const originalHtml = button ? button.innerHTML : '';

            if (button) {
                button.disabled = true;
                button.innerHTML = '...';
            }

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: new FormData(form),
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Unable to update priority.');
                    }

                    return response.json();
                })
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.message || 'Unable to update priority.');
                    }

                    rebuildPriorityRows(data.priorities || []);

                    updateCount(data.count || 0);

                    showAjaxMessage(
                        data.message || 'Allocation priority updated.',
                        'success'
                    );
                })
                .catch(function (error) {
                    showAjaxMessage(
                        error.message || 'Unable to update priority.',
                        'danger'
                    );

                    if (button) {
                        button.disabled = false;
                        button.innerHTML = originalHtml;
                    }
                });
        });

        function rebuildPriorityRows(priorities) {
            if (!priorities.length) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No allocation products were found.
                        </td>
                    </tr>
                `;

                return;
            }

            let html = '';

            priorities.forEach(function (priority) {
                const codeHtml = priority.display_code
                    ? `<code style="font-size:14px; font-weight:700;">${escapeHtml(priority.display_code)}</code>`
                    : `<span class="text-muted">—</span>`;

                const sourceIdHtml = priority.priority_source_id
                    ? `
                        <div class="small text-muted mt-1">
                            Source ID: ${escapeHtml(priority.priority_source_id)}
                        </div>
                    `
                    : '';

                const statusHtml = priority.source_active
                    ? `<span class="badge badge-success">${escapeHtml(priority.source_status)}</span>`
                    : `<span class="badge badge-secondary">${escapeHtml(priority.source_status)}</span>`;

                html += `
                    <tr>
                        <td class="text-center fw-bold" style="font-size:18px;">
                            ${escapeHtml(priority.priority_order)}
                        </td>

                        <td>
                            <div class="fw-bold">
                                ${escapeHtml(priority.display_name)}
                            </div>

                            ${sourceIdHtml}
                        </td>

                        <td>
                            ${buildFamilyBadge(priority.priority_type)}
                        </td>

                        <td>
                            ${codeHtml}
                        </td>

                        <td>
                            <span class="text-muted">
                                ${escapeHtml(priority.details)}
                            </span>
                        </td>

                        <td>
                            ${statusHtml}
                        </td>

                        <td class="text-center">
                            <div class="d-flex justify-content-center" style="gap:6px;">
                                ${buildMoveForm(priority, 'up')}
                                ${buildMoveForm(priority, 'down')}
                            </div>
                        </td>
                    </tr>
                `;
            });

            tableBody.innerHTML = html;
        }

        function buildMoveForm(priority, direction) {
            const disabled = direction === 'up'
                ? priority.is_first
                : priority.is_last;

            const iconClass = direction === 'up'
                ? 'i-Arrow-Up'
                : 'i-Arrow-Down';

            const arrow = direction === 'up'
                ? '↑'
                : '↓';

            const title = direction === 'up'
                ? 'Move up'
                : 'Move down';

            return `
                <form
                    method="POST"
                    action="${escapeAttribute(priority.move_url)}"
                    class="m-0 js-priority-move-form"
                >
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="direction" value="${direction}">

                    <button
                        type="submit"
                        class="btn btn-outline-primary btn-sm js-priority-move-btn"
                        title="${title}"
                        ${disabled ? 'disabled' : ''}
                    >
                        <i class="${iconClass}"></i>
                        ${arrow}
                    </button>
                </form>
            `;
        }

        function buildFamilyBadge(priorityType) {
            if (priorityType === 'LOAN_TYPE') {
                return '<span class="badge badge-danger">Loan</span>';
            }

            if (priorityType === 'FOSA_TYPE') {
                return '<span class="badge badge-info">FOSA</span>';
            }

            if (priorityType === 'SPECIAL_SAVING_PRODUCT') {
                return '<span class="badge badge-warning">Special Savings</span>';
            }

            return '<span class="badge badge-primary">Core</span>';
        }

        function updateCount(count) {
            if (countBox) {
                countBox.innerText = count;
            }

            if (countLabelBox) {
                countLabelBox.innerText = count === 1
                    ? 'Product'
                    : 'Products';
            }
        }

        function showAjaxMessage(message, type) {
            if (!alertBox) {
                return;
            }

            alertBox.style.display = 'block';
            alertBox.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${escapeHtml(message)}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">
                            &times;
                        </span>
                    </button>
                </div>
            `;

            setTimeout(function () {
                alertBox.innerHTML = '';
                alertBox.style.display = 'none';
            }, 2500);
        }

        function escapeHtml(value) {
            if (value === null || value === undefined) {
                return '';
            }

            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function escapeAttribute(value) {
            return escapeHtml(value);
        }
    });
</script>

@endsection