@extends('layouts.app')

@section('content')

@php
    $specialSavings = $data['specialSavings'] ?? collect();

    $periodFrom = $data['period_from'] ?? '000000';
    $periodTo   = $data['period_to'] ?? '999999';

    $member = $data['member'] ?? null;

    $isViewingJunior =
        (bool) ($data['is_viewing_junior'] ?? false);

    $junior = $data['junior'] ?? null;
@endphp

<div class="container-fluid px-2 px-md-3">

    <div
        class="
            d-flex
            flex-column
            flex-md-row
            justify-content-between
            align-items-md-center
            gap-2
            mb-3
        "
    >
        <div>
            <h3 class="mb-1 fw-bold">
                Special Savings
            </h3>

            @if($member)
                <div class="text-muted">
                    {{ $member->member_name }}

                    @if(!empty($member->member_sacco_id))
                        · {{ $member->member_sacco_id }}
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Junior account context --}}
    @if($isViewingJunior && $junior)
        <div class="alert alert-info py-2 mb-3">
            <strong>Viewing Junior Account:</strong>
            {{ $junior['member_name'] ?? '' }}

            @if(!empty($junior['member_sacco_id']))
                · {{ $junior['member_sacco_id'] }}
            @endif
        </div>
    @endif


    {{-- Period Filter --}}
    <div class="card mb-3 shadow-sm">
        <div class="card-body">

            <form
                method="GET"
                action="{{ url('/contributions/special-savings') }}"
            >
                @if(request()->query('view_as_member') === 'y')
                    <input
                        type="hidden"
                        name="view_as_member"
                        value="y"
                    >
                @endif

                @if(request()->filled('jaccount'))
                    <input
                        type="hidden"
                        name="jaccount"
                        value="{{ request()->query('jaccount') }}"
                    >
                @endif

                <div class="row g-2 align-items-end">

                    <div class="col-md-4">
                        <label
                            for="period_from"
                            class="form-label fw-bold"
                        >
                            Period From
                        </label>

                        <input
                            type="text"
                            name="period_from"
                            id="period_from"
                            class="form-control"
                            inputmode="numeric"
                            maxlength="6"
                            value="{{ $periodFrom }}"
                            placeholder="YYYYMM"
                        >
                    </div>

                    <div class="col-md-4">
                        <label
                            for="period_to"
                            class="form-label fw-bold"
                        >
                            Period To
                        </label>

                        <input
                            type="text"
                            name="period_to"
                            id="period_to"
                            class="form-control"
                            inputmode="numeric"
                            maxlength="6"
                            value="{{ $periodTo }}"
                            placeholder="YYYYMM"
                        >
                    </div>

                    <div class="col-md-4">
                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            View Statement
                        </button>
                    </div>

                </div>
            </form>

        </div>
    </div>


    @if($specialSavings->isEmpty())

        <div class="card shadow-sm">
            <div class="card-body text-center py-5">

                <i
                    class="i-Coins text-muted"
                    style="font-size: 40px;"
                ></i>

                <h5 class="mt-3 mb-1">
                    No Special Savings Accounts
                </h5>

                <p class="text-muted mb-0">
                    No Special Savings account is currently
                    recorded for this member.
                </p>

            </div>
        </div>

    @else

        @foreach($specialSavings as $saving)

            @php
                $account = $saving->account;

                $productName =
                    $account->special_saving_product_name
                    ?? 'Special Savings';

                $productCode =
                    $account->special_saving_product_code
                    ?? null;

                $transactions =
                    $saving->transactions ?? collect();
            @endphp

            <div class="card shadow-sm mb-4">

                <div class="card-header bg-white">

                    <div
                        class="
                            d-flex
                            flex-column
                            flex-lg-row
                            justify-content-between
                            gap-2
                        "
                    >

                        <div>
                            <h5 class="mb-1 fw-bold">
                                {{ $productName }}
                            </h5>

                            <div class="text-muted small">

                                @if($productCode)
                                    {{ $productCode }}
                                @endif

                                @if(
                                    !empty(
                                        $account
                                            ->special_saving_account_number
                                    )
                                )
                                    @if($productCode)
                                        ·
                                    @endif

                                    Account:
                                    {{
                                        $account
                                            ->special_saving_account_number
                                    }}
                                @endif

                            </div>
                        </div>

                        <div>
                            <span class="badge bg-light text-dark border">
                                {{
                                    $account
                                        ->special_saving_account_status
                                    ?? 'N/A'
                                }}
                            </span>
                        </div>

                    </div>

                </div>


                <div class="card-body">

                    {{-- Opening Position --}}
                    <div class="mb-3">

                        <div class="fw-bold mb-2">
                            Opening Position
                        </div>

                        <div class="row g-2">

                            <div class="col-6 col-lg-3">
                                <div class="border rounded p-2 h-100">
                                    <small class="text-muted d-block">
                                        Principal
                                    </small>

                                    <strong>
                                        KES
                                        {{
                                            number_format(
                                                $saving
                                                    ->opening_principal,
                                                2
                                            )
                                        }}
                                    </strong>
                                </div>
                            </div>

                            <div class="col-6 col-lg-3">
                                <div class="border rounded p-2 h-100">
                                    <small class="text-muted d-block">
                                        Accrued Interest
                                    </small>

                                    <strong>
                                        KES
                                        {{
                                            number_format(
                                                $saving
                                                    ->opening_accrued_interest,
                                                2
                                            )
                                        }}
                                    </strong>
                                </div>
                            </div>

                            <div class="col-6 col-lg-3">
                                <div class="border rounded p-2 h-100">
                                    <small class="text-muted d-block">
                                        Available Interest
                                    </small>

                                    <strong>
                                        KES
                                        {{
                                            number_format(
                                                $saving
                                                    ->opening_available_interest,
                                                2
                                            )
                                        }}
                                    </strong>
                                </div>
                            </div>

                            <div class="col-6 col-lg-3">
                                <div class="border rounded p-2 h-100">
                                    <small class="text-muted d-block">
                                        Total
                                    </small>

                                    <strong>
                                        KES
                                        {{
                                            number_format(
                                                $saving->opening_total,
                                                2
                                            )
                                        }}
                                    </strong>
                                </div>
                            </div>

                        </div>
                    </div>


                    {{-- Transactions --}}
                    <div class="table-responsive">

                        <table
                            class="
                                table
                                table-sm
                                table-striped
                                align-middle
                                mb-0
                            "
                        >
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Period</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th class="text-end">
                                        Debit
                                    </th>
                                    <th class="text-end">
                                        Credit
                                    </th>
                                    <th class="text-end">
                                        Principal
                                    </th>
                                    <th class="text-end">
                                        Accrued Interest
                                    </th>
                                    <th class="text-end">
                                        Available Interest
                                    </th>
                                    <th class="text-end">
                                        Balance
                                    </th>
                                </tr>
                            </thead>

                            <tbody>

                                @forelse($transactions as $txn)

                                    @php
                                        $direction = strtoupper(
                                            (string)
                                            $txn
                                                ->special_saving_transaction_direction
                                        );

                                        $amount = abs(
                                            (float)
                                            $txn
                                                ->special_saving_transaction_amount
                                        );

                                        $isDebit =
                                            $direction === 'DEBIT';
                                    @endphp

                                    <tr>

                                        <td class="text-nowrap">
                                            {{
                                                $txn
                                                    ->special_saving_transaction_date
                                            }}
                                        </td>

                                        <td class="text-nowrap">
                                            {{
                                                $txn
                                                    ->special_saving_transaction_period
                                            }}
                                        </td>

                                        <td>
                                            {{
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $txn
                                                        ->special_saving_transaction_type
                                                )
                                            }}
                                        </td>

                                        <td style="min-width: 220px;">
                                            {{
                                                $txn
                                                    ->special_saving_transaction_description
                                                ?: '-'
                                            }}
                                        </td>

                                        <td class="text-end text-nowrap">
                                            @if($isDebit)
                                                {{
                                                    number_format(
                                                        $amount,
                                                        2
                                                    )
                                                }}
                                            @else
                                                -
                                            @endif
                                        </td>

                                        <td class="text-end text-nowrap">
                                            @if(!$isDebit)
                                                {{
                                                    number_format(
                                                        $amount,
                                                        2
                                                    )
                                                }}
                                            @else
                                                -
                                            @endif
                                        </td>

                                        <td class="text-end text-nowrap">
                                            {{
                                                number_format(
                                                    (float)
                                                    $txn
                                                        ->special_saving_transaction_principal_balance_after,
                                                    2
                                                )
                                            }}
                                        </td>

                                        <td class="text-end text-nowrap">
                                            {{
                                                number_format(
                                                    (float)
                                                    $txn
                                                        ->special_saving_transaction_accrued_interest_after,
                                                    2
                                                )
                                            }}
                                        </td>

                                        <td class="text-end text-nowrap">
                                            {{
                                                number_format(
                                                    (float)
                                                    $txn
                                                        ->special_saving_transaction_available_interest_after,
                                                    2
                                                )
                                            }}
                                        </td>

                                        <td
                                            class="
                                                text-end
                                                text-nowrap
                                                fw-bold
                                            "
                                        >
                                            {{
                                                number_format(
                                                    (float)
                                                    $txn
                                                        ->special_saving_transaction_total_balance_after,
                                                    2
                                                )
                                            }}
                                        </td>

                                    </tr>

                                @empty

                                    <tr>
                                        <td
                                            colspan="10"
                                            class="
                                                text-center
                                                text-muted
                                                py-4
                                            "
                                        >
                                            No transactions were recorded
                                            during this period.
                                        </td>
                                    </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>


                    {{-- Closing Position --}}
                    <div class="mt-3">

                        <div class="fw-bold mb-2">
                            Closing Position
                        </div>

                        <div class="row g-2">

                            <div class="col-6 col-lg-3">
                                <div class="border rounded p-2 h-100">
                                    <small class="text-muted d-block">
                                        Principal
                                    </small>

                                    <strong>
                                        KES
                                        {{
                                            number_format(
                                                $saving
                                                    ->closing_principal,
                                                2
                                            )
                                        }}
                                    </strong>
                                </div>
                            </div>

                            <div class="col-6 col-lg-3">
                                <div class="border rounded p-2 h-100">
                                    <small class="text-muted d-block">
                                        Accrued Interest
                                    </small>

                                    <strong>
                                        KES
                                        {{
                                            number_format(
                                                $saving
                                                    ->closing_accrued_interest,
                                                2
                                            )
                                        }}
                                    </strong>
                                </div>
                            </div>

                            <div class="col-6 col-lg-3">
                                <div class="border rounded p-2 h-100">
                                    <small class="text-muted d-block">
                                        Available Interest
                                    </small>

                                    <strong>
                                        KES
                                        {{
                                            number_format(
                                                $saving
                                                    ->closing_available_interest,
                                                2
                                            )
                                        }}
                                    </strong>
                                </div>
                            </div>

                            <div class="col-6 col-lg-3">
                                <div class="border rounded p-2 h-100">
                                    <small class="text-muted d-block">
                                        Total Balance
                                    </small>

                                    <strong>
                                        KES
                                        {{
                                            number_format(
                                                $saving->closing_total,
                                                2
                                            )
                                        }}
                                    </strong>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>
            </div>

        @endforeach

    @endif

</div>

@endsection