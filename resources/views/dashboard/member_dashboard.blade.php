@extends('layouts.app')

@section('content')
    @include('member_name')
    @include('dashboard.junior-context-banner')
    @include('dashboard.payment_options')


    <div class="row">
        <!-- Icon Cards Section -->
        <div class="col-lg-6 col-md-12">
            <div class="row">
                <!-- Total Shares -->
                <div class="col-lg-4 col-md-6 col-sm-6 col-6">
                    <div class="card card-icon mb-4" style="min-height: 150px;">
                        <div class="card-body text-center">
                            <i class="i-Money-Bag"></i>
                            <p class="text-muted mt-2 mb-2">Total Savings</p>
                            <p class="text-primary text-24 line-height-1 m-0">
                                {{ number_format($data['member']->member_total_share ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Capital Shares -->
                <div class="col-lg-4 col-md-6 col-sm-6 col-6">
                    <div class="card card-icon mb-4" style="min-height: 150px;">
                        <div class="card-body text-center">
                            <i class="i-Safe-Box"></i>
                            <p class="text-muted mt-2 mb-2">Capital Shares</p>
                            <p class="text-primary text-24 line-height-1 m-0">
                                {{ number_format($data['member']->member_total_share_capital ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Total FOSA -->
                <div class="col-lg-4 col-md-6 col-sm-6 col-6">
                    <div class="card card-icon mb-4" style="min-height: 150px;">
                        <div class="card-body text-center">
                            <i class="i-Bank"></i>
                            <p class="text-muted mt-2 mb-2">Total FOSA / Others</p>
                            <p class="text-primary text-24 line-height-1 m-0">
                                {{ number_format($data['member']->member_total_fosa ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Total Loans -->
                <div class="col-lg-4 col-md-6 col-sm-6 col-6">
                    <div class="card card-icon mb-4" style="min-height: 150px;">
                        <div class="card-body text-center">
                            <i class="i-Money-2"></i>
                            <p class="text-muted mt-2 mb-2">Total Loans</p>
                            <p class="text-primary text-24 line-height-1 m-0">
                                {{ number_format($data['member']->member_total_loan ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Guaranteed to Self -->
                <div class="col-lg-4 col-md-6 col-sm-6 col-6">
                    <div class="card card-icon mb-4" style="min-height: 150px;">
                        <div class="card-body text-center">
                            <i class="i-Shield"></i>
                            <p class="text-muted mt-2 mb-2">Guaranteed to Self</p>
                            <p class="text-primary text-24 line-height-1 m-0">
                                {{ number_format($data['member']->member_tied_shares_self ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Guaranteed to Others -->
                <div class="col-lg-4 col-md-6 col-sm-6 col-6">
                    <div class="card card-icon mb-4" style="min-height: 150px;">
                        <div class="card-body text-center">
                            <i class="i-Administrator"></i>
                            <p class="text-muted mt-2 mb-2">Guaranteed to Others</p>
                            <p class="text-primary text-24 line-height-1 m-0">
                                {{ number_format($data['member']->member_tied_shares ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Chart -->
        <div class="col-lg-6 col-md-12">
            <div class="card mb-4">
                <div class="card-body p-0">
                    <h5 class="card-title m-0 p-3">Savings |
                        <?php
                        
                        ?>
                        <a href="{{ url('/mobile/stkpush/SH' . $data['member']->member_id) }}">
                            Deposit From mPesa
                        </a>

                    </h5>
                    <div id="echart4" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <!-- Pending Loans -->
        <div class="col-lg-6 col-md-12">
            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center border-0">
                    <h3 class="w-50 float-start card-title m-0">Pending Loans</h3>

                </div>
                <div>
                    <div class="table-responsive">
                        <table class="table text-center" id="pending_loans_table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Loan Type</th>
                                    <th scope="col">Original Loan</th>
                                    <th scope="col">Principal Balance</th>
                                    <th scope="col">Amount to Pay</th>
                                    <th scope="col">Period Taken</th>
                                    <th scope="col">Repay</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($data['pendingLoans'] as $index => $loan)
                                    <tr>
                                        <th scope="row">
                                            {{ $index + 1 }}
                                        </th>

                                        <td>
                                            {{ $loan->loan_type_name }}
                                        </td>

                                        <td>
                                            Ksh {{ number_format($loan->loan_amount, 2) }}
                                        </td>

                                        <td>
                                            Ksh {{ number_format($loan->principal_balance ?? $loan->loan_balance, 2) }}
                                        </td>

                                        <td>
                                            <strong>
                                                Ksh {{ number_format($loan->amount_to_pay ?? $loan->loan_balance, 2) }}
                                            </strong>

                                            @if (($loan->interest_to_pay ?? 0) > 0)
                                                <div class="small text-muted">
                                                    Includes Ksh
                                                    {{ number_format($loan->interest_to_pay, 2) }}
                                                    interest
                                                </div>
                                            @endif
                                        </td>

                                        <td>
                                            {{ \Carbon\Carbon::createFromFormat('Ym', $loan->loan_taken_period)->format('M Y') }}
                                        </td>

                                        <td>
                                            <a href="{{ url('/mobile/stkpush/LN' . $loan->loan_id) }}">
                                                LN{{ $loan->loan_id }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach

                                @if ($data['pendingLoans']->isEmpty())
                                    <tr>
                                        <td colspan="7" class="text-muted">
                                            No pending loans available
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next of Kin -->
        <div class="col-lg-6 col-md-12">
            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center border-0">
                    <h3 class="w-50 float-start card-title m-0">Next of Kin</h3>
                </div>
                <div>
                    <div class="table-responsive">
                        <table class="table text-center" id="next_of_kin_table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Address</th>
                                    <th scope="col">National ID</th>
                                    <th scope="col">Relationship</th>
                                    <th scope="col">Percent (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data['nextOfKin'] as $index => $kin)
                                    <tr>
                                        <th scope="row">{{ $index + 1 }}</th>
                                        <td>{{ $kin->kin_names }}</td>
                                        <td>{{ $kin->kin_address }}</td>
                                        <td>{{ $kin->kin_national_id }}</td>
                                        <td>{{ $kin->kin_relationship }}</td>
                                        <td>{{ $kin->kin_percent }}%</td>
                                    </tr>
                                @endforeach
                                @if ($data['nextOfKin']->isEmpty())
                                    <tr>
                                        <td colspan="6" class="text-muted">No next of kin information available</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @if (config('sacco.transport_sacco') === 'Y')
            <div class="col-lg-12 col-md-12">
                <div class="card o-hidden mb-4">
                    <div class="card-header d-flex align-items-center border-0">
                        <h3 class="w-50 float-start card-title m-0">Assigned Operators</h3>

                        <div class="w-50 text-end">
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal"
                                data-bs-target="#addOperatorModal">
                                <i class="i-Add"></i> Add Your Operators
                            </button>
                        </div>
                    </div>


                    <div>
                        <div class="table-responsive">
                            <table class="table text-center" id="assigned_operators_table">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Operator Name</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Phone</th>
                                        <th scope="col">Assigned Vehicle</th>
                                        <th>Pay As Operator</th>

                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse($data['operators'] as $index => $op)
                                        <tr>
                                            <th scope="row">{{ $index + 1 }}</th>
                                            <td>{{ $op->full_name }}</td>
                                            <td>{{ ucfirst($op->operator_type) }}</td>
                                            <td>{{ $op->phone }}</td>
                                            <td>
                                                @if ($op->vehicle_reg_no)
                                                    <span class="badge bg-success">{{ $op->vehicle_reg_no }}</span>
                                                @else
                                                    <span class="badge bg-secondary">No Vehicle</span>
                                                @endif
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                    data-bs-target="#operatorPaymentModal{{ $op->operator_id }}">
                                                    View Codes
                                                </button>
                                            </td>

                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-muted">No operators found</td>
                                        </tr>
                                    @endforelse
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif



    </div>
    @if (config('sacco.transport_sacco') === 'Y')
        @include('dashboard.operator_options')
        @include('dashboard.add_operator_options')
    @endif



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Line Chart for Share Payments -->
    <script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var chartDom = document.getElementById('echart4');
            var myChart = echarts.init(chartDom);

            var option = {
                title: {
                    text: 'Last 6 Savings / Depositis',
                    left: 'center'
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'cross'
                    }
                },
                xAxis: {
                    type: 'category',
                    boundaryGap: false,
                    data: {!! json_encode($data['labels']) !!} // Periods (formatted as YYYY-MM)
                },
                yAxis: {
                    type: 'value',
                    name: 'Amount (Ksh)'
                },
                series: [{
                    name: 'Share Payments',
                    type: 'line',
                    data: {!! json_encode($data['amounts']) !!}, // Payment amounts
                    smooth: true,
                    itemStyle: {
                        color: '#4CAF50'
                    },
                    areaStyle: {
                        opacity: 0.3,
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
                                offset: 0,
                                color: '#4CAF50'
                            },
                            {
                                offset: 1,
                                color: '#A5D6A7'
                            }
                        ])
                    }
                }]
            };

            myChart.setOption(option);

            // Ensure responsiveness
            window.addEventListener('resize', function() {
                myChart.resize();
            });
        });
    </script>
@endsection
