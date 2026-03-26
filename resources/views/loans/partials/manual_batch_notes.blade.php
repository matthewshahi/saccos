<div class="card mt-4 shadow-sm loan-manual-notes">
    <div class="card-header">
        <h4 class="card-title mb-1">How Manual Loan Processing Works</h4>
        <small class="text-muted d-block">
            This section explains the manual loan workflow used by staff, credit committee members, and finance users.
        </small>
    </div>

    <div class="card-body">

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100 bg-light">
                    <div class="fw-bold mb-1">1. Draft Batch</div>
                    <small class="text-muted d-block">
                        A batch is first created by staff and remains editable while transactions are being entered.
                    </small>
                    <div class="mt-2 small">
                        <strong>Status:</strong> batch_approved = N, batch_updated = N
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="border rounded p-3 h-100 bg-light">
                    <div class="fw-bold mb-1">2. Approved Batch</div>
                    <small class="text-muted d-block">
                        Approval is done after checking the batch count and the total requested loan amount.
                    </small>
                    <div class="mt-2 small">
                        <strong>Status:</strong> batch_approved = Y, batch_updated = N
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="border rounded p-3 h-100 bg-light">
                    <div class="fw-bold mb-1">3. Finalised Batch</div>
                    <small class="text-muted d-block">
                        Finalisation transfers batch records into the live loan tables and creates accounting entries.
                    </small>
                    <div class="mt-2 small">
                        <strong>Status:</strong> batch_updated = Y
                    </div>
                </div>
            </div>
        </div>

        <div class="accordion" id="manualLoanNotesAccordion">

            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        A. What this manual loan module is
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#manualLoanNotesAccordion">
                    <div class="accordion-body">
                        <p class="mb-2">
                            This is the <strong>manual staff-driven loan process</strong>, not the self-service member application flow.
                            SACCO staff enter loan batches and transactions on behalf of members.
                        </p>
                        <p class="mb-0">
                            The workflow separates data entry, approval, and final posting so that loans are reviewed before they affect live loan balances and accounting records.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        B. What a loan batch means
                    </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#manualLoanNotesAccordion">
                    <div class="accordion-body">
                        <p class="mb-2">
                            A batch is a controlled group of loan transactions expected to be processed together.
                        </p>
                        <ul class="mb-0">
                            <li><strong>Batch Reference</strong> identifies the group.</li>
                            <li><strong>Batch Amount</strong> is the total expected <strong>requested loan amount</strong> for all transactions in that batch.</li>
                            <li><strong>Batch Total Transactions</strong> is the expected number of member loan lines in that batch.</li>
                            <li><strong>Batch Credit Account</strong> is the disbursement source account, normally bank or M-Pesa.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        C. What each transaction captures
                    </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#manualLoanNotesAccordion">
                    <div class="accordion-body">
                        <p class="mb-2">
                            Each transaction represents one member’s loan inside the batch.
                        </p>
                        <ul class="mb-0">
                            <li>Member</li>
                            <li>Loan type and category</li>
                            <li>Requested loan amount</li>
                            <li>Loan duration</li>
                            <li>Document number and description</li>
                            <li>Optional commission</li>
                            <li>Optional top-up loan</li>
                            <li>Other charges or deductions</li>
                            <li>Guarantors and guaranteed amounts</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="headingFour">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                        D. How charges, deductions, and guarantors behave
                    </button>
                </h2>
                <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#manualLoanNotesAccordion">
                    <div class="accordion-body">
                        <p class="mb-2">
                            The system separates the member’s <strong>requested loan</strong> from later financial effects.
                        </p>
                        <ul class="mb-2">
                            <li><strong>ADD_TO_LOAN</strong> charges increase the amount used for repayment and interest calculation.</li>
                            <li><strong>DEDUCT_FROM_DISBURSEMENT</strong> charges reduce the cash the member actually receives.</li>
                            <li><strong>Commission</strong> is stored separately and handled in final accounting.</li>
                            <li><strong>Insurance</strong> is also stored separately and handled in final accounting.</li>
                        </ul>
                        <p class="mb-0">
                            <strong>Guarantors cover the requested loan only.</strong> They do not guarantee commission, insurance, or other added charges unless the business rules are deliberately changed later.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="headingFive">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                        E. What approval checks mean
                    </button>
                </h2>
                <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#manualLoanNotesAccordion">
                    <div class="accordion-body">
                        <p class="mb-2">
                            Approval is the credit committee control point. At this stage, the system checks the batch against what was declared at batch level.
                        </p>
                        <ul class="mb-2">
                            <li>The number of active transactions must match <strong>Batch Total Transactions</strong>.</li>
                            <li>The sum of <strong>requested loan amounts</strong> must match <strong>Batch Amount</strong>.</li>
                        </ul>
                        <p class="mb-0">
                            These approval checks are based on the core requested loan values, not on net disbursement, charges, insurance, or total payable.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="headingSix">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                        F. What happens after approval
                    </button>
                </h2>
                <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#manualLoanNotesAccordion">
                    <div class="accordion-body">
                        <p class="mb-2">
                            Once a batch is approved, it should no longer be altered during normal operations.
                        </p>
                        <p class="mb-0">
                            From that point, the next expected action is <strong>finalisation</strong>. Finalisation is the stage that pushes the approved batch into live operational records.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="headingSeven">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven">
                        G. What finalisation does
                    </button>
                </h2>
                <div id="collapseSeven" class="accordion-collapse collapse" aria-labelledby="headingSeven" data-bs-parent="#manualLoanNotesAccordion">
                    <div class="accordion-body">
                        <p class="mb-2">
                            Finalisation moves the batch from temporary processing tables into live working tables.
                        </p>
                        <ul class="mb-0">
                            <li>Creates the live record in <strong>sacco_loans</strong></li>
                            <li>Transfers guarantors into the live guarantor table</li>
                            <li>Transfers loan deductions/additions into the live loan deductions table</li>
                            <li>Updates member loan balances</li>
                            <li>Handles top-up loan clearing where applicable</li>
                            <li>Writes accounting entries into <strong>sacco_accounts_trans</strong></li>
                            <li>Marks the batch as finalised so it cannot be edited again</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="headingEight">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight" aria-expanded="false" aria-controls="collapseEight">
                        H. How the ledger side works
                    </button>
                </h2>
                <div id="collapseEight" class="accordion-collapse collapse" aria-labelledby="headingEight" data-bs-parent="#manualLoanNotesAccordion">
                    <div class="accordion-body">
                        <p class="mb-2">
                            During finalisation, the module posts accounting entries through <strong>sacco_accounts_trans</strong>.
                        </p>
                        <ul class="mb-2">
                            <li>The <strong>loan account</strong> is debited for the live loan amount being created.</li>
                            <li>The <strong>bank / source account</strong> is credited for the actual value going out.</li>
                            <li><strong>Commission</strong> is credited to the commission income account when applicable.</li>
                            <li><strong>Insurance</strong> is credited to the insurance account when applicable.</li>
                            <li>Any extra deduction/addition accounts must be configured before finalisation where required by setup.</li>
                        </ul>
                        <p class="mb-0">
                            To keep records clean, entries with zero value should not be posted.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="headingNine">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNine" aria-expanded="false" aria-controls="collapseNine">
                        I. Important operating notes
                    </button>
                </h2>
                <div id="collapseNine" class="accordion-collapse collapse" aria-labelledby="headingNine" data-bs-parent="#manualLoanNotesAccordion">
                    <div class="accordion-body">
                        <ul class="mb-0">
                            <li>Batch controls are based on <strong>requested loan totals</strong>, not net disbursement totals.</li>
                            <li>Guarantors secure the requested loan amount, not the extra additions.</li>
                            <li>Approval is a review point; finalisation is the actual posting point.</li>
                            <li>Once finalised, the batch should be treated as posted and no longer editable.</li>
                            <li>Missing ledger account setup should stop finalisation until configuration is complete.</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

        <div class="alert alert-info mt-4 mb-0">
            <strong>Operational Summary:</strong>
            Staff prepare the batch, enter member transactions, confirm charges and guarantors, submit for approval, and only after approval should the batch be finalised into live loans and accounting records.
        </div>
    </div>
</div>

<style>
    .loan-manual-notes .card-title {
        font-size: 1rem;
    }

    .loan-manual-notes .accordion-button {
        font-size: 0.95rem;
        font-weight: 600;
    }

    .loan-manual-notes .accordion-body,
    .loan-manual-notes .accordion-body p,
    .loan-manual-notes .accordion-body li,
    .loan-manual-notes .alert,
    .loan-manual-notes small {
        font-size: 0.9rem;
    }

    .loan-manual-notes ul {
        padding-left: 1.1rem;
    }

    @media (max-width: 767.98px) {
        .loan-manual-notes .accordion-button {
            font-size: 0.9rem;
            padding: 0.85rem 0.9rem;
        }

        .loan-manual-notes .accordion-body,
        .loan-manual-notes .accordion-body p,
        .loan-manual-notes .accordion-body li,
        .loan-manual-notes .alert,
        .loan-manual-notes small {
            font-size: 0.85rem;
        }
    }
</style>