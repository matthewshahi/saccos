<div class="row">
    <!-- Savings & Capital -->
    <div class="col-md-6 mb-3">
        <div class="card text-start h-100">
            <div class="card-body">
                <h4 class="card-title mb-3">Make Deposits: Savings & Capital</h4>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td><span class="badge bg-dark">SH</span></td>
                                <td>Savings</td>
                                <td>
                                    <a href="{{ url('/mobile/stkpush/SH' . $data['member']->member_id) }}"
                                       class="btn btn-sm btn-primary">Pay</a>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td><span class="badge bg-dark">CA</span></td>
                                <td>Capital Shares</td>
                                <td>
                                    <a href="{{ url('/mobile/stkpush/CA' . $data['member']->member_id) }}"
                                       class="btn btn-sm btn-primary">Pay</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Loans & Other FOSA -->
    <div class="col-md-6 mb-3">
        <div class="card text-start h-100">
            <div class="card-body">
                <h4 class="card-title mb-3">Repay Loans & Contribute to FOSA Accounts</h4>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $row = 1; @endphp

                            <!-- Pending Loans -->
                            @foreach ($data['pendingLoans'] as $index => $loan)
                                <tr>
                                    <td>{{ $row++ }}</td>
                                    <td><span class="badge bg-warning">LN{{ $loan->loan_id }}</span></td>
                                    <td>{{ $loan->loan_type_name ?? 'Loan' }}</td>
                                    <td>
                                        <a href="{{ url('/mobile/stkpush/LN'.$loan->loan_id) }}"
                                           class="btn btn-sm btn-danger">
                                           LN{{ $loan->loan_id }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach

                            <!-- Dynamic FOSA Types -->
                            @foreach ($data['paymentOptions'] as $option)
                                <tr>
                                    <td>{{ $row++ }}</td>
                                    <td><span class="badge bg-secondary">{{ $option->type_prefix }}</span></td>
                                    <td>{{ $option->type_name }}</td>
                                    <td>
                                        <a href="{{ url('/mobile/stkpush/' . $option->type_prefix . $data['member']->member_id) }}"
                                           class="btn btn-sm btn-success">
                                           {{ $option->type_prefix . $data['member']->member_id }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach

                            @if ($row === 1)
                                <tr>
                                    <td colspan="4" class="text-muted">No pending loans or FOSA types available.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<p class="text-muted small mt-3">
Click on the <b>Code</b> or <b>Pay</b> button to proceed. You will be redirected to a page where you only need to enter your phone number and the payment amount.
</p>