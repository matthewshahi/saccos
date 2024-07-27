@extends('layouts.app')

@section('styles')
<style>
    .align-right {
        text-align: right;
    }
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
@endsection

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>SASRA - Member Share Report</h1>
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
<div class="mb-4">
    <h5>Searchable Fields:</h5>
    <p>You can search by Member Name, Phone Number, National ID, or Sacco ID.</p>
    <form id="search-form" class="form-inline">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search by name, phone, ID or PIN">
            <div class="input-group-append">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </div>
    </form>
</div>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card text-start">
            <div class="card-body">
                <button id="downloadExcel" class="btn btn-success mb-3">Download Excel</button>
                <div id="loans-table-container" style="overflow-x: auto;">
                    <table class="table table-bordered" id="loans-table" style="white-space: nowrap;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member Name</th>
                                <th>Sacco ID</th>
                                <th>National ID</th>
                                <th>Phone No</th>
                                <th>Position</th>
                                <th>Loan Type</th>
                                <th class="align-right">Loan Amount</th>
                                <th class="align-right">Loan Paid</th>
                                <th class="align-right">Outstanding Balance</th>
                                <th>Loan Taken Period</th>
                                <th>Last Payment Period</th>
                                <th>Loan Category</th>
                            </tr>
                        </thead>
                        <tbody id="loans-table-body">
                            <!-- Loans will be loaded here via AJAX -->
                        </tbody>
                    </table>
                </div>
                <div id="loading-status" class="text-center">Loading...</div>
            </div>
        </div>
    </div>
</div>

<div class="mb-4">
    <h5>Loan Categories:</h5>
    <ul>
        <li><span class="badge bg-success">Current:</span> Paid in the last 2 months</li>
        <li><span class="badge bg-warning">Watch:</span> Paid between 2 and 4 months ago</li>
        <li><span class="badge bg-secondary">Substandard:</span> Paid between 4 and 6 months ago</li>
        <li><span class="badge bg-danger">Doubtful:</span> Paid between 6 and 9 months ago</li>
        <li><span class="badge bg-dark">Loss:</span> Paid more than 9 months ago or never paid</li>
    </ul>
</div>



<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let offset = 0;
    const limit = 10;
    let isLoading = false;
    let allRecordsLoaded = false;
    const version = "{{ $version }}";

    function loadLoans() {
        if (isLoading || allRecordsLoaded) return;
        isLoading = true;

        const search = document.querySelector('input[name="search"]').value;
        const params = new URLSearchParams({ offset, limit, search, version });

        fetch("{{ route('reports.sasra.loanperformance') }}?" + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.length < limit) {
                allRecordsLoaded = true;
                document.getElementById('loading-status').innerText = 'All records loaded';
            }

            const tbody = document.getElementById('loans-table-body');
            data.forEach((loan, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${offset + index + 1}</td>
                    <td>${loan.member_name}</td>
                    <td>${loan.member_sacco_id}</td>
                    <td>${loan.member_national_id}</td>
                    <td>${loan.member_phone_no}</td>
                    <td>${loan.position}</td>
                    <td>${loan.loan_type_name}</td>
                    <td class="align-right">${parseFloat(loan.loan_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td class="align-right">${parseFloat(loan.loan_loan_paid).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td class="align-right">${parseFloat(loan.loan_amount - loan.loan_loan_paid).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td>${loan.loan_taken_period}</td>
                    <td>${loan.loan_payments_period ?? 'Never Paid'}</td>
                    <td>
                        <span class="badge bg-${getLoanCategoryClass(loan.loan_category)}">${loan.loan_category}</span>
                    </td>
                `;
                tbody.appendChild(row);
            });

            offset += limit;
            isLoading = false;
        })
        .catch(error => {
            console.error('Error loading loans:', error);
            isLoading = false;
        });
    }

    function getLoanCategoryClass(category) {
        switch (category) {
            case 'Current': return 'success';
            case 'Watch': return 'warning';
            case 'Substandard': return 'secondary';
            case 'Doubtful': return 'danger';
            case 'Loss': return 'dark';
            default: return 'light';
        }
    }

    // Load initial loans
    loadLoans();

    // Load more loans on scroll
    window.addEventListener('scroll', () => {
        if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 100) {
            loadLoans();
        }
    });

    // Reload loans on search form submit
    document.getElementById('search-form').addEventListener('submit', function (event) {
        event.preventDefault();
        offset = 0;
        allRecordsLoaded = false;
        document.getElementById('loans-table-body').innerHTML = '';
        loadLoans();
    });

    // Download Excel
    $('#downloadExcel').click(function() {
        let wb = XLSX.utils.table_to_book(document.getElementById('loans-table'), { sheet: "Sheet JS" });
        XLSX.writeFile(wb, 'OutstandingLoansReport.xlsx');
    });
});
</script>
@endsection
