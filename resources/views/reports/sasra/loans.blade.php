@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>{{ $reportType }} Loans Report</h1>
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
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Filter Loans</div>
                <form action="{{ route('reports.sasra.loans', ['status' => $status, 'active' => $active]) }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="start_period">Start Period</label>
                            <input type="text" class="form-control" id="start_period" name="start_period" value="{{ $startPeriod }}" placeholder="YYYYmm">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="end_period">End Period</label>
                            <input type="text" class="form-control" id="end_period" name="end_period" value="{{ $endPeriod }}" placeholder="YYYYmm">
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
                <button id="downloadExcel" class="btn btn-success mb-3">Download Excel</button>
                <div id="loans-table-container" style="overflow-x: auto;">
                    <table class="table table-bordered" id="loans-table" style="white-space: nowrap;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Loan No.</th>
                                <th>Mem. No.</th>
                                <th>Mem. Name</th>
                                <th>ID</th>
                                <th>DOB</th>
                                <th>PIN</th>
                                <th>Gender</th>
                                <th>Company Name</th>
                                <th>Phone</th>
                                <th>Loan Type</th>
                                <th>Amort. Type</th>
                                <th>Installments</th>
                                <th>Loan Rate %</th>
                                <th>Loan Frequency</th>
                                <th>Date Disbursed</th>
                                <th>Loan Amount</th>
                                <th>Maturity</th>
                                <th>Balance</th>
                                <th>Arrears (Months)</th>
                                <th>Classification</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
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
<script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

<script>
    $(document).ready(function() {
        let offset = 0;
        const limit = 5;
        let isLoading = false;
        let allRecordsLoaded = false;

        function fetchLoans() {
            if (isLoading || allRecordsLoaded) return;
            isLoading = true;

            $.ajax({
                url: "{{ route('reports.sasra.loans.data') }}",
                method: "GET",
                data: { 
                    offset: offset, 
                    limit: limit, 
                    status: "{{ $status }}", 
                    active: "{{ $active }}", 
                    start_period: "{{ $startPeriod }}",
                    end_period: "{{ $endPeriod }}"
                },
                success: function(response) {
                    const loans = response.loans;

                    let tableRows = '';
                    loans.forEach((loan, index) => {
                        tableRows += `
                            <tr>
                                <td>${offset + index + 1}</td>
                                <td>${loan.loan_id}</td>
                                <td>${loan.member_sacco_id}</td>
                                <td>${loan.member_name}</td>
                                <td>${loan.member_national_id}</td>
                                <td>${loan.member_dob}</td>
                                <td>${loan.member_kra_pin}</td>
                                <td>${loan.member_gender}</td>
                                <td>${loan.company_name}</td>
                                <td>${loan.member_phone_no}</td>
                                <td>${loan.loan_type_name} (${loan.loan_id})</td>
                                <td>${loan.loan_type_interest_type}</td>
                                <td>${loan.loan_payment_period}</td>
                                <td>${loan.loan_type_interest}</td>
                                <td>1</td>
                                <td>${loan.loan_on}</td>
                                <td style="text-align:right">${parseFloat(loan.loan_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td>${loan.effective_date}</td>
                                <td style="text-align:right">${parseFloat(loan.balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td>${loan.arrears_months}</td>
                                <td>${loan.loan_category_name}</td>
                            </tr>
                        `;
                    });

                    $('#loans-table tbody').append(tableRows);

                    if (loans.length < limit) {
                        allRecordsLoaded = true;
                        $('#loading-status').text('All records loaded').removeClass('loading-status');
                    } else {
                        offset += limit;
                        isLoading = false;
                        $('#loading-status').text('Loading...').addClass('loading-status');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    $('#loading-status').text('Failed to fetch loan data. Please try again later.');
                    isLoading = false;
                }
            });
        }

        // Initial load
        fetchLoans();

        // Regularly fetch more records
        setInterval(function() {
            fetchLoans();
        }, 5000);

        // Download Excel
        $('#downloadExcel').click(function() {
            let wb = XLSX.utils.table_to_book(document.getElementById('loans-table'), { sheet: "Sheet JS" });
            XLSX.writeFile(wb, 'SasraLoansReport.xlsx');
        });
    });
</script>
@endsection
