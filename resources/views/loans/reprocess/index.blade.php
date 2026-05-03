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
                    <div class="card-title mb-3 text-warning">Important Notice</div>

                    <div class="alert alert-warning mb-3" role="alert">
                        These actions recalculate loan payments, guarantor balances, and member loan totals.
                        Run them only when you need to correct or refresh loan records.
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label>Recommended time</label>
                            <input class="form-control" type="text" value="After business hours" readonly>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Recommended use</label>
                            <input class="form-control" type="text" value="Only when a loan correction is required" readonly>
                        </div>

                        <div class="col-md-12">
                            <div class="alert alert-danger mb-0" role="alert">
                                Avoid running these actions while users are actively posting loan payments or making loan changes.
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
                        <div class="col-lg-3 col-md-6">
                            <div class="card mb-4 border-info">
                                <div class="card-body">
                                    <div class="card-title mb-3 text-info">Refresh Loan Payments</div>

                                    <p class="text-muted mb-3">
                                        Recalculates the amount paid on each loan using recorded loan payments.
                                    </p>

                                    <form method="POST"
                                          action="{{ route('loans.reprocess.recalculate-loan-paid') }}"
                                          onsubmit="return confirm('Continue with Refresh Loan Payments?');">
                                        @csrf

                                        <div class="form-group mb-3">
                                            <label class="checkbox checkbox-info">
                                                <input type="checkbox" name="confirm_recalculate_loan_paid" required>
                                                <span>I confirm that I want to continue.</span>
                                                <span class="checkmark"></span>
                                            </label>
                                        </div>

                                        <button class="btn btn-info w-100" type="submit">
                                            Start Refresh
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="card mb-4 border-warning">
                                <div class="card-body">
                                    <div class="card-title mb-3 text-warning">Refresh Guarantors</div>

                                    <p class="text-muted mb-3">
                                        Recalculates freed guarantor amounts and rebuilds tied shares.
                                    </p>

                                    <form method="POST"
                                          action="{{ route('loans.reprocess.reset-guarantors') }}"
                                          onsubmit="return confirm('Continue with Refresh Guarantors?');">
                                        @csrf

                                        <div class="form-group mb-3">
                                            <label class="checkbox checkbox-warning">
                                                <input type="checkbox" name="confirm_reset_guarantors" required>
                                                <span>I confirm that I want to continue.</span>
                                                <span class="checkmark"></span>
                                            </label>
                                        </div>

                                        <button class="btn btn-warning w-100" type="submit">
                                            Start Refresh
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="card mb-4 border-primary">
                                <div class="card-body">
                                    <div class="card-title mb-3 text-primary">Refresh Member Loans</div>

                                    <p class="text-muted mb-3">
                                        Recalculates each member’s total outstanding loan balance.
                                    </p>

                                    <form method="POST"
                                          action="{{ route('loans.reprocess.update-member-loan-balances') }}"
                                          onsubmit="return confirm('Continue with Refresh Member Loans?');">
                                        @csrf

                                        <div class="form-group mb-3">
                                            <label class="checkbox checkbox-primary">
                                                <input type="checkbox" name="confirm_update_member_loans" required>
                                                <span>I confirm that I want to continue.</span>
                                                <span class="checkmark"></span>
                                            </label>
                                        </div>

                                        <button class="btn btn-primary w-100" type="submit">
                                            Start Refresh
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="card mb-4 border-danger">
                                <div class="card-body">
                                    <div class="card-title mb-3 text-danger">Full Loan Refresh</div>

                                    <p class="text-muted mb-3">
                                        Runs the full refresh in order: payments, guarantors, then member loan totals.
                                    </p>

                                    <form method="POST"
                                          action="{{ route('loans.reprocess.run-all') }}"
                                          onsubmit="return confirm('This will run the full loan refresh. Continue?');">
                                        @csrf

                                        <div class="form-group mb-3">
                                            <label class="checkbox checkbox-danger">
                                                <input type="checkbox" name="confirm_run_all" required>
                                                <span>I confirm that I want to continue.</span>
                                                <span class="checkmark"></span>
                                            </label>
                                        </div>

                                        <button class="btn btn-danger w-100" type="submit">
                                            Start Full Refresh
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
                    <h3 class="w-50 float-start card-title m-0">Process Guide</h3>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Action</th>
                                    <th>Purpose</th>
                                    <th style="width: 25%;">Recommended Timing</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Refresh Loan Payments</td>
                                    <td>Updates each loan with the correct amount already paid.</td>
                                    <td><span class="badge bg-info">Off-Peak</span></td>
                                </tr>
                                <tr>
                                    <td>Refresh Guarantors</td>
                                    <td>Updates guarantor exposure and member tied shares.</td>
                                    <td><span class="badge bg-warning">Off-Peak</span></td>
                                </tr>
                                <tr>
                                    <td>Refresh Member Loans</td>
                                    <td>Updates member loan balances from the loan records.</td>
                                    <td><span class="badge bg-primary">Off-Peak</span></td>
                                </tr>
                                <tr>
                                    <td>Full Loan Refresh</td>
                                    <td>Runs all loan refresh actions in the correct order.</td>
                                    <td><span class="badge bg-danger">After Hours</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-secondary mt-3 mb-0" role="alert">
                        For best results, use <strong>Full Loan Refresh</strong> when you want to correct all loan-related totals at once.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection