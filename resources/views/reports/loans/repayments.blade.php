@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loan Repayments Report</h1>
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
    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
               
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="card-title mb-3">Search Filters</div>
                            <form id="searchForm">
                                <div class="row">
                                    <div class="col-md-3 form-group mb-3">
                                        <label for="startPeriod">Start Period (YYYYMM)</label>
                                        <input class="form-control" id="startPeriod" name="startPeriod" type="text" placeholder="Start Period">
                                    </div>
                                    <div class="col-md-3 form-group mb-3">
                                        <label for="endPeriod">End Period (YYYYMM)</label>
                                        <input class="form-control" id="endPeriod" name="endPeriod" type="text" placeholder="End Period">
                                    </div>
                                    <div class="col-md-3 form-group mb-3">
                                        <label for="searchName">Member Name</label>
                                        <input class="form-control" id="searchName" name="searchName" type="text" placeholder="Search by Member Name">
                                    </div>
                                    <div class="col-md-3 form-group mb-3">
                                        <label for="searchCompany">Company</label>
                                        <input class="form-control" id="searchCompany" name="searchCompany" type="text" placeholder="Search by Company">
                                    </div>
                                    <div class="col-md-3 form-group mb-3">
                                        <label for="searchLoanType">Loan Type</label>
                                        <input class="form-control" id="searchLoanType" name="searchLoanType" type="text" placeholder="Search by Loan Type">
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">Search</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div id="loan-repayment-table-container" style="overflow-x: auto;">
                    <table class="table table-bordered" id="loan-repayment-table" style="white-space: nowrap;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member Name</th>
                                <th>Phone Number</th>
                                <th>Sacco ID</th>
                                <th>Loan Type</th>
                                <th class="text-right">Loan Amount</th>
                                <th class="text-right">Loan Balance</th>
                                <th class="text-right">Amount Paid</th>
                                <th>Period Paid</th>
                                <th>Paid On</th>
                                <th>Document Number</th>
                            </tr>
                        </thead>
                        <tbody id="loan-repayment-data"></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-right">Totals:</th>
                                <th class="text-right" id="total-loan-amount"></th>
                                <th class="text-right" id="total-loan-balance"></th>
                                <th class="text-right" id="total-amount-paid"></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="loading-status" class="loading-status">Loading...</div>
            </div>
        </div>
    </div>
</div>

<style>
    .loading-status {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 36px;
        font-weight: bold;
        color: #000;
        z-index: 9999;
        animation: blinkingText 1.2s infinite;
    }

    @keyframes blinkingText {
        0% { color: #000; }
        49% { color: #000; }
        50% { color: transparent; }
        99% { color: transparent; }
        100% { color: #000; }
    }

    .table {
        position: relative;
        z-index: 1;
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    $(document).ready(function() {
        let offset = 0;
        const limit = 30;
        let isLoading = false;
        let allRecordsLoaded = false;
        let rowCount = 1;
        let totalLoanAmount = 0;
        let totalLoanBalance = 0;
        let totalAmountPaid = 0;

        function fetchRepayments(startPeriod, endPeriod, searchName, searchCompany, searchLoanType) {
            if (isLoading || allRecordsLoaded) return;
            isLoading = true;

            const queryParams = { startPeriod: startPeriod, endPeriod: endPeriod, searchName: searchName, searchCompany: searchCompany, searchLoanType: searchLoanType, offset: offset, limit: limit };
            console.log('Fetching repayments with parameters:', queryParams);

            $.ajax({
                url: "{{ route('reports.loans.repayments.data') }}",
                method: "GET",
                data: queryParams,
                success: function(response) {
                    console.log('Response received:', response);
                    const repayments = response.data;

                    let tableRows = '';
                    repayments.forEach((repayment, index) => {
                        const loanBalance = repayment.loan_amount - repayment.loan_loan_paid;
                        tableRows += `
                            <tr>
                                <td>${rowCount++}</td>
                                <td>${repayment.member_name}</td>
                                <td>${repayment.member_phone_no}</td>
                                <td>${repayment.member_sacco_id}</td>
                                <td>${repayment.loan_type_name}</td>
                                <td class="text-right">${parseFloat(repayment.loan_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td class="text-right">${parseFloat(loanBalance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td class="text-right">${parseFloat(repayment.loan_payments_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td>${repayment.loan_payments_period}</td>
                                <td>${new Date(repayment.loan_payments_paid_on).toLocaleDateString()}</td>
                                <td>${repayment.loan_payments_docno}</td>
                            </tr>
                        `;
                        totalLoanAmount += parseFloat(repayment.loan_amount);
                        totalLoanBalance += parseFloat(loanBalance);
                        totalAmountPaid += parseFloat(repayment.loan_payments_amount);
                    });

                    $('#loan-repayment-table tbody').append(tableRows);

                    if (repayments.length < limit) {
                        allRecordsLoaded = true;
                        $('#loading-status').text('All records loaded').removeClass('loading-status');
                        displayTotals();
                    } else {
                        offset += limit;
                        isLoading = false;
                        $('#loading-status').hide();
                        fetchRepayments(startPeriod, endPeriod, searchName, searchCompany, searchLoanType); // Recursively fetch the next batch
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    $('#loading-status').text('Failed to fetch repayment data. Please try again later.');
                    isLoading = false;
                }
            });
        }

        function displayTotals() {
            $('#total-loan-amount').text(totalLoanAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#total-loan-balance').text(totalLoanBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#total-amount-paid').text(totalAmountPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        }

        function resetSearchState() {
            offset = 0;
            rowCount = 1;
            totalLoanAmount = 0;
            totalLoanBalance = 0;
            totalAmountPaid = 0;
            allRecordsLoaded = false;
            $('#loan-repayment-table tbody').empty();
            $('#loading-status').text('Loading...').addClass('loading-status').show();
        }

        $('#searchForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Search form submitted');
            resetSearchState();
            const searchParams = $('#searchForm').serialize();
            const newUrl = "{{ route('reports.loans.repayments') }}" + '?' + searchParams;
            history.pushState(null, '', newUrl);
            loadRepaymentsFromUrl();
        });

        function loadRepaymentsFromUrl() {
            const params = new URLSearchParams(window.location.search);
            fetchRepayments(
                params.get('startPeriod') || '{{ date('Ym', strtotime('-3 months')) }}', 
                params.get('endPeriod') || '{{ date('Ym') }}', 
                params.get('searchName') || '', 
                params.get('searchCompany') || '', 
                params.get('searchLoanType') || ''
            );
        }

        // Initial load
        loadRepaymentsFromUrl();

        // Handle back/forward navigation
        window.onpopstate = function() {
            resetSearchState();
            loadRepaymentsFromUrl();
        };
    });
</script>
@endsection
