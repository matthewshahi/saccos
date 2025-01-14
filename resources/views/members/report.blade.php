 @extends('layouts.app')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<div class="row">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center border-0">
                <h3 class="w-50 float-start card-title m-0">MEMBER REPORT</h3>
            </div>
            <div class="card-body">
                <form id="filter-form" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="search">SEARCH (NAME, PHONE, MEMBER ID):</label>
                            <input type="text" id="search" name="search" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="period">PERIOD (YYYYMM):</label>
                            <input type="text" id="period" name="period" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="member_active" name="member_active" value="Y" checked>
                                <label class="form-check-label" for="member_active">ACTIVE MEMBERS ONLY</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">FETCH REPORT</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table text-center table-bordered" id="report-table">
                        <thead>
                            <tr id="report-headers">
                                <th>#</th>
                                <th>MEMBER ID</th>
                                <th>NAME</th>
                                <th>PHONE</th>
                                <th>KRA PIN</th>
                                <th>ACTIVE</th>
                                <th>TOTAL SHARES</th>
                                <th>TOTAL CAPITAL</th>
                                <th>TOTAL FOSA</th>
                                <!-- Loan types will be appended dynamically -->
                                <th>TOTAL LOANS</th>
                            </tr>
                        </thead>
                        <tbody id="report-body"></tbody>
                        <tfoot id="report-totals"></tfoot>
                    </table>
                </div>
                <div id="loading-indicator" class="text-center mt-3" style="display: none;">
                    <span id="loading-text">Loading more records...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let loading = false;
let hasMoreData = true;
let serialNumber = 1;
let cumulativeTotals = {
    total_shares: 0,
    total_capital: 0,
    total_fosa: 0,
    total_loans: 0,
    loan_type_totals: {}, // Store totals for each loan type
};

const resetCumulativeTotals = () => {
    cumulativeTotals = {
        total_shares: 0,
        total_capital: 0,
        total_fosa: 0,
        total_loans: 0,
        loan_type_totals: {},
    };
};

const updateCumulativeTotals = (totals, loanTypes) => {
    cumulativeTotals.total_shares += totals.total_shares;
    cumulativeTotals.total_capital += totals.total_capital;
    cumulativeTotals.total_fosa += totals.total_fosa;
    cumulativeTotals.total_loans += totals.total_loans;

    Object.keys(loanTypes).forEach(loanType => {
        if (!cumulativeTotals.loan_type_totals[loanTypes[loanType]]) {
            cumulativeTotals.loan_type_totals[loanTypes[loanType]] = 0;
        }
        cumulativeTotals.loan_type_totals[loanTypes[loanType]] += totals.loan_type_totals[loanTypes[loanType]] || 0;
    });
};

const renderTotalsRow = (loanTypes) => {
    const tfoot = document.getElementById('report-totals');
    tfoot.innerHTML = `
        <tr>
            <td colspan="6" style="text-align: right;"><strong>TOTALS</strong></td>
            <td style="text-align: right;">${Number(cumulativeTotals.total_shares).toLocaleString()}</td>
            <td style="text-align: right;">${Number(cumulativeTotals.total_capital).toLocaleString()}</td>
            <td style="text-align: right;">${Number(cumulativeTotals.total_fosa).toLocaleString()}</td>
            ${Object.values(loanTypes).map(loanTypeName => `
                <td style="text-align: right;">${Number(cumulativeTotals.loan_type_totals[loanTypeName] || 0).toLocaleString()}</td>
            `).join('')}
            <td style="text-align: right;">${Number(cumulativeTotals.total_loans).toLocaleString()}</td>
        </tr>
    `;
};

const initializeDataTable = () => {
    if (!$.fn.DataTable.isDataTable('#report-table')) {
        $('#report-table').DataTable({
            dom: 'Bfrtip', // Allows the buttons to be displayed
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            paging: false,
            searching: false,
            ordering: true,
            info: true,
            autoWidth: false,
        });
    }
};

 

const fetchData = (page = 1) => {
    const search = document.getElementById('search').value;
    const period = document.getElementById('period').value;
    const memberActive = document.getElementById('member_active').checked ? 'Y' : 'N';

    if (loading || !hasMoreData) return;
    loading = true;

    document.getElementById('loading-indicator').style.display = 'block';

    const fullUrl = "{{ url('/members/report/data') }}";

    axios.post(fullUrl, { search, period, page, member_active: memberActive })
        .then(response => {
            const { loanTypes, data, totals, hasMoreData: moreDataAvailable } = response.data;

            const tbody = document.getElementById('report-body');
            const headersRow = document.getElementById('report-headers');

            // Dynamically add loan type headers
            if (!headersRow.querySelectorAll('th.loan-type').length) {
                Object.values(loanTypes).forEach(loanTypeName => {
                    const th = document.createElement('th');
                    th.classList.add('loan-type');
                    th.innerText = loanTypeName.toUpperCase();
                    th.style.textAlign = 'right';
                    headersRow.insertBefore(th, headersRow.lastElementChild);
                });
            }

            // Populate the table rows
            data.forEach(member => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${serialNumber++}</td>
                    <td style="text-align: left; white-space: nowrap;">${member.member_id}</td>
                    <td style="text-align: left; white-space: nowrap;">${member.name}</td>
                    <td style="text-align: left; white-space: nowrap;">${member.phone}</td>
                    <td style="text-align: left;">${member.kra_pin || '-'}</td>
                    <td style="text-align: center;">${member.active}</td>
                    <td style="text-align: right;">${Number(member.total_shares).toLocaleString()}</td>
                    <td style="text-align: right;">${Number(member.total_capital).toLocaleString()}</td>
                    <td style="text-align: right;">${Number(member.total_fosa).toLocaleString()}</td>
                `;
                Object.values(loanTypes).forEach(loanTypeName => {
                    const td = document.createElement('td');
                    td.style.textAlign = 'right';
                    td.innerText = Number(member[loanTypeName] || 0).toLocaleString();
                    row.appendChild(td);
                });
                row.innerHTML += `<td style="text-align: right;">${Number(member.total_loans).toLocaleString()}</td>`;
                tbody.appendChild(row);
            });

            // Update cumulative totals and render footer
            updateCumulativeTotals(totals, loanTypes);
            renderTotalsRow(loanTypes);

            hasMoreData = moreDataAvailable;
            loading = false;

            if (!hasMoreData) {
                document.getElementById('loading-text').innerText = "All records loaded.";
                document.getElementById('loading-indicator').style.display = 'none';

                // Initialize DataTables after all data is loaded
                initializeDataTable();
            }
        })
        .catch(error => {
            console.error(error);
            loading = false;
            document.getElementById('loading-indicator').style.display = 'none';
        });
};

// Event listener for filter form submission
document.getElementById('filter-form').addEventListener('submit', function (e) {
    e.preventDefault();
    currentPage = 1;
    hasMoreData = true;
    serialNumber = 1;
    resetCumulativeTotals();
    document.getElementById('report-body').innerHTML = '';
    document.getElementById('report-totals').innerHTML = '';
    document.getElementById('loading-text').innerText = "Loading more records...";
    fetchData(currentPage);
});

// Infinite scrolling
window.addEventListener('scroll', () => {
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 100 && !loading) {
        currentPage++;
        fetchData(currentPage);
    }
});

// Initial data fetch
fetchData(currentPage);
</script>


@endsection