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
                <div class="card-title mb-3">Loan Repayments Report</div>
                <form id="searchForm" method="GET" action="{{ route('reports.loans.repayments') }}">
                    <div class="form-group">
                        <label for="startPeriod">Start Period (YYYYMM)</label>
                        <input type="text" name="startPeriod" id="startPeriod" class="form-control" value="{{ request('startPeriod', date('Ym')) }}" placeholder="Start Period">
                    </div>
                    <div class="form-group">
                        <label for="endPeriod">End Period (YYYYMM)</label>
                        <input type="text" name="endPeriod" id="endPeriod" class="form-control" value="{{ request('endPeriod', date('Ym')) }}" placeholder="End Period">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
                <button id="downloadExcel" class="btn btn-success mb-3">Download Excel</button>
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

<!-- Include jQuery library -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

<script>
    $(document).ready(function() {
        let offset = 0;
        const limit = 100;
        let isLoading = false;
        let allRecordsLoaded = false;
        let rowCount = 1;
        let totalLoanAmount = 0;
        let totalLoanBalance = 0;
        let totalAmountPaid = 0;

        function fetchRepayments(startPeriod, endPeriod) {
            if (isLoading || allRecordsLoaded) return;
            isLoading = true;

            $.ajax({
                url: "{{ route('reports.loans.repayments.data') }}",
                method: "GET",
                data: { startPeriod: startPeriod, endPeriod: endPeriod, offset: offset, limit: limit },
                success: function(response) {
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
                        fetchRepayments(startPeriod, endPeriod); // Recursively fetch the next batch
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

        $('#searchForm').on('submit', function(e) {
            e.preventDefault();
            offset = 0;
            rowCount = 1;
            totalLoanAmount = 0;
            totalLoanBalance = 0;
            totalAmountPaid = 0;
            allRecordsLoaded = false;
            $('#loan-repayment-table tbody').empty();
            $('#loading-status').text('Loading...').addClass('loading-status');
            fetchRepayments($('#startPeriod').val(), $('#endPeriod').val());
        });

        // Initial load
        fetchRepayments('{{ request('startPeriod', date('Ym')) }}', '{{ request('endPeriod', date('Ym')) }}');

        $('#downloadExcel').on('click', function() {
            $.ajax({
                url: "{{ route('reports.loans.repayments.download') }}",
                method: "GET",
                data: { startPeriod: $('#startPeriod').val(), endPeriod: $('#endPeriod').val() },
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(data) {
                    const url = window.URL.createObjectURL(new Blob([data]));
                    const link = document.createElement('a');
                    link.href = url;
                    link.setAttribute('download', 'LoanRepaymentsReport.xlsx');
                    document.body.appendChild(link);
                    link.click();
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('Failed to download the report. Please try again later.');
                }
            });
        });
    });
</script>
@endsection
