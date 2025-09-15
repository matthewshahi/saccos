@extends('layouts.app')

@section('content')
<?php
if (!function_exists('formatAmount')) {
    function formatAmount($amount)
    {
        $isNegative = $amount < 0; // Check if the number is negative
        $amount = abs($amount); // Work with the absolute value

        if ($amount >= 1000000) {
            $formatted = number_format($amount / 1000000, 1, '.', ',') . 'M'; // Example: 14.6M
        } else {
            $formatted = number_format($amount, 2, '.', ','); // Example: -51,037.70
        }

        return $isNegative ? '-' . ltrim($formatted, '-') : $formatted; // Ensure negative sign is properly formatted
    }
}
?>
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Dashboard</h1>
        <div class="header-part-right">
            <ul>
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
                @if(isset($currentPeriod))
                    <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
                @endif
                <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>

    <div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-4">
        <a href="{{ url('members/active/y') }}" class="card-link">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="card-title">Active Members</h6>
                    <div class="d-flex justify-content-center align-items-center">
                        <i class="i-Business-Man text-primary" style="font-size: 2rem; margin-right: 0.5rem;"></i>
                        <p class="card-text mb-0" id="activeMembersCount" style="font-size: 1.5rem;">{{ number_format($activeMembersCount) }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
    <a href="{{ url('members/active/y') }}" class="card-link">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="card-title">New Members ({{ $currentPeriod->period_name }})</h6>
                    <div class="d-flex justify-content-center align-items-center">
                        <i class="i-Add-User text-success" style="font-size: 2rem; margin-right: 0.5rem;"></i>
                        <p class="card-text mb-0" id="newMembersCount" style="font-size: 1.5rem;">{{ number_format($newMembersCount) }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <a href="{{ url('/reports/accounts/profit-loss') }}" class="card-link">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="card-title">Profitability ({{date('Ym')}})</h6>
                    <div class="d-flex justify-content-center align-items-center">
                        <i class="i-Waiter text-warning" style="font-size: 2rem; margin-right: 0.5rem;"></i>
                        <p class="card-text mb-0" id="pendingAppsCount" style="font-size: 1.5rem;">{{ number_format($pendingAppsCount) }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <a href="{{ url('/reports/members/status') }}" class="card-link">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="card-title">All Time Deposits</h6>
                    <div class="d-flex justify-content-center align-items-center">
                        <i class="i-Safe-Box text-info" style="font-size: 2rem; margin-right: 0.5rem;"></i>
                        <p class="card-text mb-0" id="savingsDepositsTotal" style="font-size: 1.5rem;">KES {{ number_format($savingsDepositsTotal, 2) }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <a href="{{ url('/reports/loans/issued') }}" class="card-link">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="card-title">All Time Loans</h6>
                    <div class="d-flex justify-content-center align-items-center">
                        <i class="i-Money-2 text-primary" style="font-size: 2rem; margin-right: 0.5rem;"></i>
                        <p class="card-text mb-0" id="loansIssuedTotal" style="font-size: 1.5rem;">KES {{ number_format($loansIssuedTotal, 2) }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <a href="{{ url('/reports/loans/repayments') }}" class="card-link">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="card-title">Repayments ({{ $currentPeriod->period_name }})</h6>
                    <div class="d-flex justify-content-center align-items-center">
                        <i class="i-Money-Bag text-success" style="font-size: 2rem; margin-right: 0.5rem;"></i>
                        <p class="card-text mb-0" id="repaymentsTotal" style="font-size: 1.5rem;">
                             KES {{ formatAmount($repaymentsTotal) }}
                             </p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <a href="{{ url('/reports/loans/issued') }}" class="card-link">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="card-title">Active Loans</h6>
                    <div class="d-flex justify-content-center align-items-center">
                        <i class="i-Checked-User text-primary" style="font-size: 2rem; margin-right: 0.5rem;"></i>
                        <p class="card-text mb-0" id="activeLoansCount" style="font-size: 1.5rem;">{{ number_format($activeLoansCount) }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
    <a href="{{ url('/reports/sasra/loan_performance/') }}" class="card-link">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="card-title">Delinquent Loans</h6>
                    <div class="d-flex justify-content-center align-items-center">
                        <i class="i-Danger text-danger" style="font-size: 2rem; margin-right: 0.5rem;"></i>
                        <p class="card-text mb-0" id="delinquentLoansCount" style="font-size: 1.5rem;">{{ number_format($delinquentLoansCount) }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
  @include("dashboard.member_dashboard_mpesa") 
  
</div>

<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
<script>
    $(document).ready(function() {
        function formatNumber(num) {
            let isNegative = num < 0;
            num = Math.abs(num);

            if (num >= 1000000) {
                formatted = (num / 1000000).toFixed(1) + 'M';
            } else {
                formatted = num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            return isNegative ? '-' + formatted : formatted;
        }

        $('#activeMembersCount').text(formatNumber(@json($activeMembersCount)));
        $('#newMembersCount').text(formatNumber(@json($newMembersCount)));
        $('#pendingAppsCount').text(formatNumber(@json($pendingAppsCount)));
        $('#savingsDepositsTotal').text('KES ' + formatNumber(@json($savingsDepositsTotal)));
        $('#loansIssuedTotal').text('KES ' + formatNumber(@json($loansIssuedTotal)));
        $('#repaymentsTotal').text('KES ' + formatNumber(@json($repaymentsTotal)));
        $('#activeLoansCount').text(formatNumber(@json($activeLoansCount)));
        $('#delinquentLoansCount').text(formatNumber(@json($delinquentLoansCount)));
    });
</script>


    <!-- <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="{{ route('dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="card-title">Active Members</h6>
                        <div class="d-flex justify-content-center align-items-center">
                            <i class="i-Business-Man text-primary me-2"></i>
                            <p class="card-text mb-0">{{ number_format($activeMembersCount) }}</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="{{ route('dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="card-title">New Members ({{ $currentPeriod->period_name }})</h6>
                        <div class="d-flex justify-content-center align-items-center">
                            <i class="i-Add-User text-success me-2"></i>
                            <p class="card-text mb-0">{{ number_format($newMembersCount) }}</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="{{ route('dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="card-title">Pending Loan Applications</h6>
                        <div class="d-flex justify-content-center align-items-center">
                            <i class="i-Waiter text-warning me-2"></i>
                            <p class="card-text mb-0">{{ number_format($pendingAppsCount) }}</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="{{ route('dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="card-title">All Time Savings/Deposits/Fosa</h6>
                        <div class="d-flex justify-content-center align-items-center">
                            <i class="i-Safe-Box text-info me-2"></i>
                            <p class="card-text mb-0">KES {{ number_format($savingsDepositsTotal, 2) }}</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="{{ route('dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="card-title">All Time Loans Issued</h6>
                        <div class="d-flex justify-content-center align-items-center">
                            <i class="i-Money-2 text-primary me-2"></i>
                            <p class="card-text mb-0">KES {{ number_format($loansIssuedTotal, 2) }}</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="{{ route('dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="card-title">Repayments ({{ $currentPeriod->period_name }})</h6>
                        <div class="d-flex justify-content-center align-items-center">
                            <i class="i-Money-Bag text-success me-2"></i>
                            <p class="card-text mb-0">KES {{ number_format($repaymentsTotal, 2) }}</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="{{ route('dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="card-title">Active Loans</h6>
                        <div class="d-flex justify-content-center align-items-center">
                            <i class="i-Checked-User text-primary me-2"></i>
                            <p class="card-text mb-0">{{ number_format($activeLoansCount) }}</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="{{ route('dashboard') }}" class="card-link">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="card-title">Delinquent Loans</h6>
                        <div class="d-flex justify-content-center align-items-center">
                            <i class="i-Danger text-danger me-2"></i>
                            <p class="card-text mb-0">{{ number_format($delinquentLoansCount) }}</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div> -->

    <div class="row mb-4">
        <div class="col-lg-8 col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title">Loans Taken Vs Loan Repayments (Last 12 months)</div>
                    <div id="echartBar" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title">Top 10 Loan Balances</div>
                    <div id="echartPie" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title">Savings Per Month</div>
                    <div id="savingsPerMonth" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="ul-widget__head">
                        <div class="ul-widget__head-label">
                            <h3 class="ul-widget__head-title">Members</h3>
                        </div>
                        <div class="ul-widget__head-toolbar">
                            <ul class="nav nav-tabs nav-tabs-line nav-tabs-bold ul-widget-nav-tabs-line" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active show" data-bs-toggle="tab" href="#__g-widget4-tab1-content" role="tab" aria-selected="true">Newest Members</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#__g-widget4-tab2-content" role="tab" aria-selected="false">Sacco Officials</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="ul-widget__body">
                        <div class="tab-content">
                            <div class="tab-pane active show" id="__g-widget4-tab1-content">
                                <div class="ul-widget1">
                                    @foreach($latestMembers as $index => $member)
                                    <div class="ul-widget4__item ul-widget4__users">
                                    <div class="ul-widget4__img">
    <i class="i-Business-Man text-20" style="color: #663399;"></i>
</div>
                                        <div class="ul-widget2__info ul-widget4__users-info">
                                            <a class="ul-widget2__title" href="#">{{ $member->member_name }}</a>
                                            <span class="ul-widget2__username">{{ $member->company_name }}, Joined: {{ date('d/m/Y', strtotime($member->member_date_joined)) }}</span>
                                        </div>
                                        <div class="ul-widget4__actions">
                                            <a href="{{ route('members.statement', $member->member_id) }}" class="btn btn-outline-danger m-1">View Statement</a>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="tab-pane" id="__g-widget4-tab2-content">
                                <div class="ul-widget1">
                                    @foreach($saccoOfficials as $index => $member)
                                    <div class="ul-widget4__item ul-widget4__users">
                                    <div class="ul-widget4__img">
    <i class="i-Business-Man text-20" style="color: #663399;"></i>
</div>
                                        <div class="ul-widget2__info ul-widget4__users-info">
                                            <a class="ul-widget2__title" href="#">{{ $member->member_name }}</a>
                                            <span class="ul-widget2__username">{{ $member->company_name }}, Joined: {{ date('d/m/Y', strtotime($member->member_date_joined)) }}</span>
                                        </div>
                                        <div class="ul-widget4__actions">
                                            <a href="{{ route('members.statement', $member->member_id) }}" class="btn btn-outline-danger m-1">View Statement</a>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var echartBar = echarts.init(document.getElementById('echartBar'));
        var echartPie = echarts.init(document.getElementById('echartPie'));
        var savingsPerMonthChart = echarts.init(document.getElementById('savingsPerMonth'));

        var loanData = @json($loansAndRepayments['loans']->pluck('total_loans'));
        var repaymentData = @json($loansAndRepayments['repayments']->pluck('total_repayments'));
        var periods = @json($loansAndRepayments['loans']->pluck('period'));
        var topLoanBalances = @json($topLoanBalances);
        var savingsPerMonth = @json($savingsPerMonth);

        var topLoanNames = topLoanBalances.map(item => item.member_name.split(' ')[0]);
        var topLoanAmounts = topLoanBalances.map(item => {
            let amount = item.member_total_loan;
            if (amount >= 1000000) {
                return {
                    value: amount,
                    formatted: (amount / 1000000).toFixed(2) + 'M'
                };
            } else if (amount >= 1000) {
                return {
                    value: amount,
                    formatted: (amount / 1000).toFixed(2) + 'K'
                };
            } else {
                return {
                    value: amount,
                    formatted: amount.toFixed(2)
                };
            }
        });

        var barOption = {
            legend: {
                borderRadius: 0,
                orient: 'horizontal',
                x: 'right',
                data: ['Loans', 'Repayments']
            },
            grid: {
                left: '8px',
                right: '8px',
                bottom: '0',
                containLabel: true
            },
            tooltip: {
                show: true,
                backgroundColor: 'rgba(0, 0, 0, .8)'
            },
            xAxis: [
                {
                    type: 'category',
                    data: periods,
                    axisTick: {
                        alignWithLabel: true
                    },
                    splitLine: {
                        show: false
                    },
                    axisLine: {
                        show: true
                    }
                }
            ],
            yAxis: [
                {
                    type: 'value',
                    axisLabel: {
                        formatter: 'KES {value}'
                    },
                    min: 0,
                    axisLine: {
                        show: false
                    },
                    splitLine: {
                        show: true,
                        interval: 'auto'
                    }
                }
            ],
            series: [
                {
                    name: 'Loans',
                    data: loanData,
                    type: 'bar',
                    barGap: 0,
                    color: '#bcbbdd',
                    smooth: true,
                    itemStyle: {
                        emphasis: {
                            shadowBlur: 10,
                            shadowOffsetX: 0,
                            shadowOffsetY: -2,
                            shadowColor: 'rgba(0, 0, 0, 0.3)'
                        }
                    }
                },
                {
                    name: 'Repayments',
                    data: repaymentData,
                    type: 'bar',
                    color: '#7569b3',
                    smooth: true,
                    itemStyle: {
                        emphasis: {
                            shadowBlur: 10,
                            shadowOffsetX: 0,
                            shadowOffsetY: -2,
                            shadowColor: 'rgba(0, 0, 0, 0.3)'
                        }
                    }
                }
            ]
        };

        var pieOption = {
            tooltip: {
                trigger: 'item'
            },
            series: [
                {
                    name: 'Loan Balances',
                    type: 'pie',
                    radius: '50%',
                    data: topLoanNames.map((name, index) => ({
                        value: topLoanAmounts[index].value,
                        name: name + ' (' + topLoanAmounts[index].formatted + ')'
                    })),
                    emphasis: {
                        itemStyle: {
                            shadowBlur: 10,
                            shadowOffsetX: 0,
                            shadowColor: 'rgba(0, 0, 0, 0.5)'
                        }
                    }
                }
            ]
        };

        var savingsOption = {
            legend: {
                borderRadius: 0,
                orient: 'horizontal',
                x: 'right',
                data: ['Savings']
            },
            grid: {
                left: '8px',
                right: '8px',
                bottom: '0',
                containLabel: true
            },
            tooltip: {
                show: true,
                backgroundColor: 'rgba(0, 0, 0, .8)'
            },
            xAxis: [
                {
                    type: 'category',
                    data: savingsPerMonth.map(item => item.share_period),
                    axisTick: {
                        alignWithLabel: true
                    },
                    splitLine: {
                        show: false
                    },
                    axisLine: {
                        show: true
                    }
                }
            ],
            yAxis: [
                {
                    type: 'value',
                    axisLabel: {
                        formatter: 'KES {value}'
                    },
                    min: 0,
                    axisLine: {
                        show: false
                    },
                    splitLine: {
                        show: true,
                        interval: 'auto'
                    }
                }
            ],
            series: [
                {
                    name: 'Savings',
                    data: savingsPerMonth.map(item => item.total_savings),
                    type: 'line',
                    color: '#83bff6',
                    smooth: true,
                    itemStyle: {
                        emphasis: {
                            shadowBlur: 10,
                            shadowOffsetX: 0,
                            shadowOffsetY: -2,
                            shadowColor: 'rgba(0, 0, 0, 0.3)'
                        }
                    }
                }
            ]
        };

        echartBar.setOption(barOption);
        echartPie.setOption(pieOption);
        savingsPerMonthChart.setOption(savingsOption);

        window.addEventListener('resize', function () {
            setTimeout(function () {
                echartBar.resize();
                echartPie.resize();
                savingsPerMonthChart.resize();
            }, 500);
        });
    });
    </script>
@endsection
