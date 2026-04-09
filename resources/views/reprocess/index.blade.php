@extends('layouts.app')

@section('content')
    <div class="breadcrumb">
        <h1>Loan Reprocess</h1>
        <ul>
            <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li>Loans</li>
            <li>Reprocess</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    <div class="row">
        <div class="col-md-12">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
        </div>

        <div class="col-md-12">
            <div class="card mb-4 border-warning">
                <div class="card-body">
                    <div class="card-title mb-3 text-warning">Important Operational Warning</div>

                    <div class="alert alert-warning mb-3" role="alert">
                        <strong>Warning:</strong> This process may temporarily disrupt normal operations while totals,
                        tied shares, and loan balances are being recalculated.
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label>Best time to run</label>
                            <input class="form-control" type="text" value="Night / after business hours" readonly>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Recommended usage</label>
                            <input class="form-control" type="text" value="Only when corrective reprocessing is necessary" readonly>
                        </div>

                        <div class="col-md-12">
                            <div class="alert alert-danger mb-0" role="alert">
                                It is strongly recommended that you run these actions at night or during low usage periods.
                                Avoid running them during active member transactions unless absolutely necessary.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Reprocess Actions</div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card mb-4 border-warning">
                                <div class="card-body">
                                    <div class="card-title mb-3">Reset Guarantors</div>

                                    <p class="text-muted mb-3">
                                        Recomputes guarantor freed amounts, resets tied shares to zero,
                                        then rebuilds guarantor-related tied shares.
                                    </p>

                                    <form method="POST"
                                          action="{{ route('loans.reprocess.reset-guarantors') }}"
                                          onsubmit="return confirm('This process may disrupt operations and is best run at night. Continue with Reset Guarantors?');">
                                        @csrf

                                        <div class="form-group mb-3">
                                            <label class="checkbox checkbox-warning">
                                                <input type="checkbox" name="confirm_reset_guarantors" required>
                                                <span>I understand this process may disrupt operations and is better done at night.</span>
                                                <span class="checkmark"></span>
                                            </label>
                                        </div>

                                        <button class="btn btn-warning w-100" type="submit">
                                            Queue Reset Guarantors
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card mb-4 border-primary">
                                <div class="card-body">
                                    <div class="card-title mb-3">Update Member Loan Balances</div>

                                    <p class="text-muted mb-3">
                                        Resets all member loan totals and rebuilds them from current loan balances
                                        in the loans table.
                                    </p>

                                    <form method="POST"
                                          action="{{ route('loans.reprocess.update-member-loan-balances') }}"
                                          onsubmit="return confirm('This process may disrupt operations and is best run at night. Continue with Update Member Loan Balances?');">
                                        @csrf

                                        <div class="form-group mb-3">
                                            <label class="checkbox checkbox-primary">
                                                <input type="checkbox" name="confirm_update_member_loans" required>
                                                <span>I understand this process may disrupt operations and is better done at night.</span>
                                                <span class="checkmark"></span>
                                            </label>
                                        </div>

                                        <button class="btn btn-primary w-100" type="submit">
                                            Queue Member Loan Balances
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card mb-4 border-danger">
                                <div class="card-body">
                                    <div class="card-title mb-3 text-danger">Run Both Processes</div>

                                    <p class="text-muted mb-3">
                                        Queues both reprocessing jobs. This is the most disruptive option and should
                                        preferably be run during off-peak hours.
                                    </p>

                                    <form method="POST"
                                          action="{{ route('loans.reprocess.run-all') }}"
                                          onsubmit="return confirm('This will queue both reprocessing jobs and may disrupt operations. It is best done at night. Continue?');">
                                        @csrf

                                        <div class="form-group mb-3">
                                            <label class="checkbox checkbox-danger">
                                                <input type="checkbox" name="confirm_run_all" required>
                                                <span>I understand this process may disrupt operations and is better done at night.</span>
                                                <span class="checkmark"></span>
                                            </label>
                                        </div>

                                        <button class="btn btn-danger w-100" type="submit">
                                            Queue Both Jobs
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center border-0">
                    <h3 class="w-50 float-start card-title m-0">Process Guidance</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">Action</th>
                                    <th>Description</th>
                                    <th style="width: 25%;">Recommended Timing</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Reset Guarantors</td>
                                    <td>Rebuilds guarantor exposure and tied shares after loan repayment changes or inconsistencies.</td>
                                    <td><span class="badge bg-warning">Night</span></td>
                                </tr>
                                <tr>
                                    <td>Update Member Loan Balances</td>
                                    <td>Rebuilds member loan totals from loan records and repayments.</td>
                                    <td><span class="badge bg-primary">Night</span></td>
                                </tr>
                                <tr>
                                    <td>Run Both</td>
                                    <td>Runs full loan-related reprocessing. Use only when you want a broader corrective refresh.</td>
                                    <td><span class="badge bg-danger">Strictly Off-Peak</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-secondary mt-3 mb-0" role="alert">
                        These actions are queued background jobs when your queue is configured correctly.
                        If your queue connection is set to <strong>sync</strong>, they will run immediately in the request.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection