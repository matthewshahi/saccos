@foreach($data['operators'] as $op)
<div class="modal fade" id="operatorPaymentModal{{ $op->operator_id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">
                <h5 class="modal-title">
                    Operator Payment Codes — {{ $op->full_name }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <p class="mb-3">
                    These are the MPESA Paybill <strong>Account Codes</strong> this operator must use
                    when paying on behalf of <strong>{{ $data['member']->member_name }}</strong>.
                    <br>These codes are screenshot-friendly for drivers and external operators.
                </p>

                <div class="row">

                    <!-- LEFT COLUMN: SAVINGS + CAPITAL -->
                    <div class="col-md-6 mb-3">
                        <div class="card text-start h-100">
                            <div class="card-body">

                                <h5 class="card-title mb-3">Deposits: Savings & Capital</h5>

                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Code</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            <!-- OPSH-{opid} -->
                                            <tr>
                                                <td>1</td>
                                                <td>
                                                    <span class="badge bg-dark px-3 py-2">
                                                        OPSH-{{ $op->operator_id }}
                                                    </span>
                                                </td>
                                                <td>Savings Deposit</td>
                                            </tr>

                                            <!-- OPCA-{opid} -->
                                            <tr>
                                                <td>2</td>
                                                <td>
                                                    <span class="badge bg-dark px-3 py-2">
                                                        OPCA-{{ $op->operator_id }}
                                                    </span>
                                                </td>
                                                <td>Capital Shares Deposit</td>
                                            </tr>

                                            <!-- Optional registration fee -->
                                            <tr>
                                                <td>3</td>
                                                <td>
                                                    <span class="badge bg-secondary px-3 py-2">
                                                        OPRF-{{ $op->operator_id }}
                                                    </span>
                                                </td>
                                                <td>Registration Fee</td>
                                            </tr>

                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: LOANS + OTHER FOSA -->
                    <div class="col-md-6 mb-3">
                        <div class="card text-start h-100">
                            <div class="card-body">

                                <h5 class="card-title mb-3">Loan Payments & FOSA</h5>

                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Code</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @php $row = 1; @endphp

                                            <!-- LOANS: OPLN-{opid}-{loan_id} -->
                                            @foreach ($data['pendingLoans'] as $loan)
                                            <tr>
                                                <td>{{ $row++ }}</td>
                                                <td>
                                                    <span class="badge bg-warning px-3 py-2">
                                                        OPLN-{{ $op->operator_id }}-{{ $loan->loan_id }}
                                                    </span>
                                                </td>
                                                <td>Loan Repayment ({{ $loan->loan_type_name }})</td>
                                            </tr>
                                            @endforeach

                                            <!-- OTHER FOSA TYPES from type_prefix -->
                                            @foreach ($data['paymentOptions'] as $option)
                                            <tr>
                                                <td>{{ $row++ }}</td>
                                                <td>
                                                    <span class="badge bg-secondary px-3 py-2">
                                                        OP{{ $option->type_prefix }}-{{ $op->operator_id }}
                                                    </span>
                                                </td>
                                                <td>{{ $option->type_name }}</td>
                                            </tr>
                                            @endforeach

                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>

                <p class="small text-muted mt-3">
                    Operators should enter these codes EXACTLY as shown above 
                    in the MPESA Paybill “Account Number” field.
                </p>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>
@endforeach
