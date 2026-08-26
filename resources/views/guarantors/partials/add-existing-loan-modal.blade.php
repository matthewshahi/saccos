{{-- 
|--------------------------------------------------------------------------
| Add Guarantor To Existing Loan Modal
|--------------------------------------------------------------------------
|
| File:
| resources/views/guarantors/partials/add-existing-loan-modal.blade.php
|
| Used by:
| resources/views/members/status.blade.php
|
| Expected routes:
|
| existing.loan.guarantors.context
| existing.loan.guarantors.search
| existing.loan.guarantors.store
|
|--------------------------------------------------------------------------
--}}

@php
    /*
     * Route templates.
     *
     * JavaScript replaces __LOAN__ with the selected loan_id.
     *
     * We deliberately do NOT trust any borrower/member/financial values
     * from the browser. The controller resolves all authoritative values
     * again from the database.
     */
    $existingLoanGuarantorContextUrl = route(
        'existing.loan.guarantors.context',
        ['loan' => '__LOAN__']
    );

    $existingLoanGuarantorSearchUrl = route(
        'existing.loan.guarantors.search',
        ['loan' => '__LOAN__']
    );

    $existingLoanGuarantorStoreUrl = route(
        'existing.loan.guarantors.store',
        ['loan' => '__LOAN__']
    );
@endphp


<style>
    /*
    |--------------------------------------------------------------------------
    | Existing Loan Guarantor Modal
    |--------------------------------------------------------------------------
    |
    | Everything is scoped to #addExistingLoanGuarantorModal so that this
    | partial cannot interfere with the rest of the SACCO interface.
    |
    */

    #addExistingLoanGuarantorModal {
        --elg-border: #e3e6ea;
        --elg-muted: #6c757d;
        --elg-bg: #f7f8fa;
        --elg-purple: #663399;
        --elg-dark: #252525;
    }

    #addExistingLoanGuarantorModal .modal-content {
        border: 0;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 15px 45px rgba(0, 0, 0, .18);
    }

    #addExistingLoanGuarantorModal .modal-header {
        padding: 16px 20px;
        background: #fff;
        border-bottom: 2px solid var(--elg-purple);
    }

    #addExistingLoanGuarantorModal .modal-title {
        margin: 0;
        color: var(--elg-dark);
        font-size: 17px;
        font-weight: 800;
    }

    #addExistingLoanGuarantorModal .modal-subtitle {
        margin-top: 3px;
        color: var(--elg-muted);
        font-size: 12px;
    }

    #addExistingLoanGuarantorModal .modal-body {
        padding: 20px;
        background: #fff;
    }

    #addExistingLoanGuarantorModal .modal-footer {
        padding: 12px 20px;
        background: #fafafa;
        border-top: 1px solid var(--elg-border);
    }


    /*
    |--------------------------------------------------------------------------
    | Section headings
    |--------------------------------------------------------------------------
    */

    #addExistingLoanGuarantorModal .elg-section {
        margin-bottom: 22px;
    }

    #addExistingLoanGuarantorModal .elg-section:last-child {
        margin-bottom: 0;
    }

    #addExistingLoanGuarantorModal .elg-section-title {
        margin: 0 0 10px;
        color: #343a40;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }


    /*
    |--------------------------------------------------------------------------
    | Loan summary
    |--------------------------------------------------------------------------
    */

    #addExistingLoanGuarantorModal .elg-loan-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        border: 1px solid var(--elg-border);
        border-radius: 5px;
        overflow: hidden;
        background: #fff;
    }

    #addExistingLoanGuarantorModal .elg-summary-item {
        min-width: 0;
        padding: 12px 14px;
        border-right: 1px solid var(--elg-border);
        border-bottom: 1px solid var(--elg-border);
    }

    #addExistingLoanGuarantorModal .elg-summary-item:nth-child(3n) {
        border-right: 0;
    }

    #addExistingLoanGuarantorModal .elg-summary-item:nth-last-child(-n+3) {
        border-bottom: 0;
    }

    #addExistingLoanGuarantorModal .elg-summary-label {
        margin-bottom: 3px;
        color: var(--elg-muted);
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    #addExistingLoanGuarantorModal .elg-summary-value {
        overflow-wrap: anywhere;
        color: var(--elg-dark);
        font-size: 13px;
        font-weight: 800;
        line-height: 1.35;
    }

    #addExistingLoanGuarantorModal .elg-summary-value.elg-money {
        font-variant-numeric: tabular-nums;
    }


    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    #addExistingLoanGuarantorModal .elg-search-wrap {
        position: relative;
    }

    #addExistingLoanGuarantorModal .elg-search-input {
        min-height: 44px;
        padding-right: 42px;
    }

    #addExistingLoanGuarantorModal .elg-search-spinner {
        position: absolute;
        top: 50%;
        right: 13px;
        width: 18px;
        height: 18px;
        margin-top: -9px;
    }

    #addExistingLoanGuarantorModal .elg-help {
        margin-top: 5px;
        color: var(--elg-muted);
        font-size: 11px;
        line-height: 1.4;
    }


    /*
    |--------------------------------------------------------------------------
    | Search results
    |--------------------------------------------------------------------------
    */

    #addExistingLoanGuarantorModal .elg-results {
        max-height: 320px;
        margin-top: 10px;
        overflow-y: auto;
        border: 1px solid var(--elg-border);
        border-radius: 5px;
        background: #fff;
    }

    #addExistingLoanGuarantorModal .elg-result {
        display: block;
        width: 100%;
        padding: 12px 14px;
        color: inherit;
        text-align: left;
        background: #fff;
        border: 0;
        border-bottom: 1px solid #eef0f2;
        cursor: pointer;
        transition:
            background-color .15s ease,
            border-color .15s ease;
    }

    #addExistingLoanGuarantorModal .elg-result:last-child {
        border-bottom: 0;
    }

    #addExistingLoanGuarantorModal .elg-result:hover,
    #addExistingLoanGuarantorModal .elg-result:focus {
        background: #f8f6fb;
        outline: none;
    }

    #addExistingLoanGuarantorModal .elg-result.is-selected {
        background: #f3eef8;
        box-shadow: inset 3px 0 0 var(--elg-purple);
    }

    #addExistingLoanGuarantorModal .elg-result-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
    }

    #addExistingLoanGuarantorModal .elg-result-name {
        color: #252525;
        font-size: 13px;
        font-weight: 800;
    }

    #addExistingLoanGuarantorModal .elg-result-identity {
        margin-top: 2px;
        color: var(--elg-muted);
        font-size: 11px;
    }

    #addExistingLoanGuarantorModal .elg-result-capacity {
        flex: 0 0 auto;
        text-align: right;
    }

    #addExistingLoanGuarantorModal .elg-capacity-label {
        color: var(--elg-muted);
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }

    #addExistingLoanGuarantorModal .elg-capacity-value {
        margin-top: 2px;
        color: #198754;
        font-size: 13px;
        font-weight: 800;
        white-space: nowrap;
    }

    #addExistingLoanGuarantorModal .elg-result-details {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-top: 10px;
        padding-top: 9px;
        border-top: 1px solid #f0f0f0;
    }

    #addExistingLoanGuarantorModal .elg-result-detail-label {
        color: var(--elg-muted);
        font-size: 9px;
        text-transform: uppercase;
    }

    #addExistingLoanGuarantorModal .elg-result-detail-value {
        margin-top: 1px;
        color: #343a40;
        font-size: 11px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    #addExistingLoanGuarantorModal .elg-self-badge {
        display: inline-block;
        margin-left: 6px;
        padding: 2px 6px;
        color: var(--elg-purple);
        background: #f0e9f7;
        border-radius: 10px;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        vertical-align: middle;
    }


    /*
    |--------------------------------------------------------------------------
    | Empty / information states
    |--------------------------------------------------------------------------
    */

    #addExistingLoanGuarantorModal .elg-empty {
        padding: 20px;
        color: var(--elg-muted);
        text-align: center;
        font-size: 12px;
    }

    #addExistingLoanGuarantorModal .elg-inline-message {
        padding: 10px 12px;
        border-radius: 4px;
        font-size: 12px;
        line-height: 1.45;
    }


    /*
    |--------------------------------------------------------------------------
    | Selected guarantor
    |--------------------------------------------------------------------------
    */

    #addExistingLoanGuarantorModal .elg-selected {
        padding: 14px;
        border: 1px solid #d9cce5;
        border-radius: 5px;
        background: #faf8fc;
    }

    #addExistingLoanGuarantorModal .elg-selected-header {
        display: flex;
        justify-content: space-between;
        gap: 15px;
        align-items: flex-start;
    }

    #addExistingLoanGuarantorModal .elg-selected-name {
        color: #262626;
        font-size: 14px;
        font-weight: 800;
    }

    #addExistingLoanGuarantorModal .elg-selected-meta {
        margin-top: 3px;
        color: var(--elg-muted);
        font-size: 11px;
    }

    #addExistingLoanGuarantorModal .elg-selected-capacity {
        color: #198754;
        font-size: 15px;
        font-weight: 800;
        text-align: right;
        white-space: nowrap;
    }

    #addExistingLoanGuarantorModal .elg-selected-capacity-label {
        margin-bottom: 1px;
        color: var(--elg-muted);
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }

    #addExistingLoanGuarantorModal .elg-selected-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-top: 13px;
        padding-top: 12px;
        border-top: 1px solid #e8e0ef;
    }

    #addExistingLoanGuarantorModal .elg-selected-grid-label {
        color: var(--elg-muted);
        font-size: 9px;
        text-transform: uppercase;
    }

    #addExistingLoanGuarantorModal .elg-selected-grid-value {
        margin-top: 2px;
        color: #303030;
        font-size: 11px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }


    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    #addExistingLoanGuarantorModal .form-label {
        margin-bottom: 5px;
        color: #343a40;
        font-size: 12px;
        font-weight: 700;
    }

    #addExistingLoanGuarantorModal .form-control {
        font-size: 13px;
    }

    #addExistingLoanGuarantorModal textarea.form-control {
        min-height: 85px;
        resize: vertical;
    }

    #addExistingLoanGuarantorModal .elg-amount-guidance {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-top: 5px;
        color: var(--elg-muted);
        font-size: 10px;
    }

    #addExistingLoanGuarantorModal .elg-amount-guidance strong {
        color: #343a40;
    }


    /*
    |--------------------------------------------------------------------------
    | Responsive
    |--------------------------------------------------------------------------
    */

    @media (max-width: 767.98px) {

        #addExistingLoanGuarantorModal .modal-dialog {
            margin: .5rem;
        }

        #addExistingLoanGuarantorModal .modal-body {
            padding: 14px;
        }

        #addExistingLoanGuarantorModal .modal-header,
        #addExistingLoanGuarantorModal .modal-footer {
            padding-left: 14px;
            padding-right: 14px;
        }

        #addExistingLoanGuarantorModal .elg-loan-summary {
            grid-template-columns: 1fr 1fr;
        }

        #addExistingLoanGuarantorModal .elg-summary-item,
        #addExistingLoanGuarantorModal .elg-summary-item:nth-child(3n) {
            border-right: 1px solid var(--elg-border);
            border-bottom: 1px solid var(--elg-border);
        }

        #addExistingLoanGuarantorModal .elg-summary-item:nth-child(2n) {
            border-right: 0;
        }

        #addExistingLoanGuarantorModal .elg-result-details,
        #addExistingLoanGuarantorModal .elg-selected-grid {
            grid-template-columns: 1fr 1fr;
        }
    }


    @media (max-width: 480px) {

        #addExistingLoanGuarantorModal .modal-dialog {
            margin: 0;
            min-height: 100%;
        }

        #addExistingLoanGuarantorModal .modal-content {
            min-height: 100vh;
            border-radius: 0;
        }

        #addExistingLoanGuarantorModal .elg-loan-summary {
            grid-template-columns: 1fr;
        }

        #addExistingLoanGuarantorModal .elg-summary-item,
        #addExistingLoanGuarantorModal .elg-summary-item:nth-child(2n),
        #addExistingLoanGuarantorModal .elg-summary-item:nth-child(3n) {
            border-right: 0;
        }

        #addExistingLoanGuarantorModal .elg-result-top,
        #addExistingLoanGuarantorModal .elg-selected-header {
            display: block;
        }

        #addExistingLoanGuarantorModal .elg-result-capacity,
        #addExistingLoanGuarantorModal .elg-selected-capacity {
            margin-top: 8px;
            text-align: left;
        }

        #addExistingLoanGuarantorModal .elg-result-details,
        #addExistingLoanGuarantorModal .elg-selected-grid {
            grid-template-columns: 1fr 1fr;
        }

        #addExistingLoanGuarantorModal .modal-footer {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        #addExistingLoanGuarantorModal .modal-footer .btn {
            width: 100%;
            margin: 0;
        }

        #addExistingLoanGuarantorModal
        .modal-footer
        #elgRefreshStatement {
            grid-column: 1 / -1;
        }
    }
</style>


<div
    class="modal fade"
    id="addExistingLoanGuarantorModal"
    tabindex="-1"
    aria-labelledby="addExistingLoanGuarantorModalLabel"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"
    >
        <div class="modal-content">

            {{-- HEADER --}}
            <div class="modal-header">

                <div>
                    <h5
                        class="modal-title"
                        id="addExistingLoanGuarantorModalLabel"
                    >
                        Add Guarantor
                    </h5>

                    <div class="modal-subtitle">
                        Attach an eligible SACCO member to this existing loan.
                    </div>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            {{-- BODY --}}
            <div class="modal-body">

                {{-- GENERAL ALERT --}}
                <div
                    id="elgAlert"
                    class="alert d-none"
                    role="alert"
                ></div>


                {{-- LOADING --}}
                <div
                    id="elgContextLoading"
                    class="text-center py-5"
                >
                    <div
                        class="spinner-border spinner-border-sm"
                        role="status"
                        aria-hidden="true"
                    ></div>

                    <div class="mt-2 small text-muted">
                        Loading loan guarantee position...
                    </div>
                </div>


                {{-- MAIN CONTENT --}}
                <div
                    id="elgMainContent"
                    class="d-none"
                >

                    {{-- LOAN POSITION --}}
                    <section class="elg-section">

                        <div class="elg-section-title">
                            Loan & Guarantee Position
                        </div>

                        <div class="elg-loan-summary">

                            <div class="elg-summary-item">
                                <div class="elg-summary-label">
                                    Borrower
                                </div>

                                <div
                                    class="elg-summary-value"
                                    id="elgBorrower"
                                >
                                    —
                                </div>
                            </div>


                            <div class="elg-summary-item">
                                <div class="elg-summary-label">
                                    Loan
                                </div>

                                <div
                                    class="elg-summary-value"
                                    id="elgLoan"
                                >
                                    —
                                </div>
                            </div>


                            <div class="elg-summary-item">
                                <div class="elg-summary-label">
                                    Outstanding Principal
                                </div>

                                <div
                                    class="elg-summary-value elg-money"
                                    id="elgLoanBalance"
                                >
                                    —
                                </div>
                            </div>


                            <div class="elg-summary-item">
                                <div class="elg-summary-label">
                                    Guarantee Required
                                </div>

                                <div
                                    class="elg-summary-value elg-money"
                                    id="elgGuaranteeRequired"
                                >
                                    —
                                </div>
                            </div>


                            <div class="elg-summary-item">
                                <div class="elg-summary-label">
                                    Currently Guaranteed
                                </div>

                                <div
                                    class="elg-summary-value elg-money"
                                    id="elgCurrentlyGuaranteed"
                                >
                                    —
                                </div>
                            </div>


                            <div class="elg-summary-item">
                                <div class="elg-summary-label">
                                    Additional Guarantee Allowed
                                </div>

                                <div
                                    class="elg-summary-value elg-money"
                                    id="elgGuaranteeRemaining"
                                >
                                    —
                                </div>
                            </div>

                        </div>

                        <div
                            id="elgLoanRuleMessage"
                            class="elg-inline-message alert-warning mt-2 d-none"
                        ></div>

                    </section>


                    {{-- SEARCH --}}
                    <section
                        class="elg-section"
                        id="elgSearchSection"
                    >

                        <div class="elg-section-title">
                            Search Eligible Member
                        </div>

                        <div class="elg-search-wrap">

                            <input
                                type="search"
                                class="form-control elg-search-input"
                                id="elgMemberSearch"
                                placeholder="Search by member name, SACCO No., National ID, phone or email"
                                autocomplete="off"
                                spellcheck="false"
                                aria-label="Search member"
                            >

                            <div
                                id="elgSearchSpinner"
                                class="spinner-border spinner-border-sm elg-search-spinner d-none"
                                role="status"
                                aria-hidden="true"
                            ></div>

                        </div>

                        <div class="elg-help">
                            Type at least 2 characters. Members already attached
                            to this loan or with no available guarantee capacity
                            will not be offered.
                        </div>


                        <div
                            id="elgSearchResults"
                            class="elg-results d-none"
                            role="listbox"
                            aria-label="Eligible guarantors"
                        ></div>

                    </section>


                    {{-- SELECTED GUARANTOR --}}
                    <section
                        class="elg-section d-none"
                        id="elgSelectedSection"
                    >

                        <div class="elg-section-title">
                            Selected Guarantor
                        </div>

                        <div class="elg-selected">

                            <div class="elg-selected-header">

                                <div>

                                    <div
                                        class="elg-selected-name"
                                        id="elgSelectedName"
                                    >
                                        —
                                    </div>

                                    <div
                                        class="elg-selected-meta"
                                        id="elgSelectedMeta"
                                    >
                                        —
                                    </div>

                                </div>


                                <div class="elg-selected-capacity">

                                    <div class="elg-selected-capacity-label">
                                        Maximum For This Loan
                                    </div>

                                    <div id="elgSelectedMaximum">
                                        KES 0.00
                                    </div>

                                </div>

                            </div>


                            <div class="elg-selected-grid">

                                <div>
                                    <div class="elg-selected-grid-label">
                                        Savings
                                    </div>

                                    <div
                                        class="elg-selected-grid-value"
                                        id="elgSelectedSavings"
                                    >
                                        KES 0.00
                                    </div>
                                </div>


                                <div>
                                    <div class="elg-selected-grid-label">
                                        Tied — Others
                                    </div>

                                    <div
                                        class="elg-selected-grid-value"
                                        id="elgSelectedTiedOthers"
                                    >
                                        KES 0.00
                                    </div>
                                </div>


                                <div>
                                    <div class="elg-selected-grid-label">
                                        Tied — Self
                                    </div>

                                    <div
                                        class="elg-selected-grid-value"
                                        id="elgSelectedTiedSelf"
                                    >
                                        KES 0.00
                                    </div>
                                </div>


                                <div>
                                    <div class="elg-selected-grid-label">
                                        Pending Guarantees
                                    </div>

                                    <div
                                        class="elg-selected-grid-value"
                                        id="elgSelectedPending"
                                    >
                                        KES 0.00
                                    </div>
                                </div>


                                <div>
                                    <div class="elg-selected-grid-label">
                                        Free Guarantee Capacity
                                    </div>

                                    <div
                                        class="elg-selected-grid-value"
                                        id="elgSelectedCapacity"
                                    >
                                        KES 0.00
                                    </div>
                                </div>

                            </div>

                        </div>

                    </section>


                    {{-- ADD GUARANTOR FORM --}}
                    <form
                        id="elgForm"
                        novalidate
                    >

                        <input
                            type="hidden"
                            id="elgGuarantorMemberId"
                            name="guarantor_member_id"
                            value=""
                        >


                        <section class="elg-section">

                            <div class="elg-section-title">
                                Guarantee Details
                            </div>


                            <div class="row g-3">

                                <div class="col-md-5">

                                    <label
                                        for="elgAmount"
                                        class="form-label"
                                    >
                                        Amount to Guarantee
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            KES
                                        </span>

                                        <input
                                            type="number"
                                            class="form-control"
                                            id="elgAmount"
                                            name="amount"
                                            min="0.01"
                                            step="0.01"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            disabled
                                            required
                                        >

                                    </div>


                                    <div class="elg-amount-guidance">

                                        <span>
                                            Available:
                                            <strong id="elgAmountCapacity">
                                                KES 0.00
                                            </strong>
                                        </span>

                                        <span>
                                            Loan needs:
                                            <strong id="elgAmountRemaining">
                                                KES 0.00
                                            </strong>
                                        </span>

                                    </div>


                                    <div
                                        id="elgAmountError"
                                        class="invalid-feedback d-block"
                                    ></div>

                                </div>


                                <div class="col-md-7">

                                    <label
                                        for="elgReason"
                                        class="form-label"
                                    >
                                        Reason / Remarks
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="elgReason"
                                        name="reason"
                                        maxlength="500"
                                        placeholder="Why is this guarantor being added to the existing loan?"
                                        disabled
                                        required
                                    ></textarea>

                                    <div class="elg-help">
                                        This description becomes part of the
                                        permanent guarantor record.
                                    </div>

                                </div>

                            </div>

                        </section>

                    </form>


                    {{-- SUCCESS STATE --}}
                    <div
                        id="elgSuccessState"
                        class="d-none"
                    >
                        <div class="alert alert-success mb-0">

                            <strong>
                                Guarantor added successfully.
                            </strong>

                            <div
                                class="mt-1"
                                id="elgSuccessMessage"
                            >
                                The guarantor has been attached to the loan
                                and the member's savings exposure has been
                                updated.
                            </div>

                        </div>
                    </div>

                </div>

            </div>


            {{-- FOOTER --}}
            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    data-bs-dismiss="modal"
                    id="elgCancelButton"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    form="elgForm"
                    class="btn btn-primary"
                    id="elgSubmitButton"
                    disabled
                >
                    <span
                        id="elgSubmitSpinner"
                        class="spinner-border spinner-border-sm me-1 d-none"
                        role="status"
                        aria-hidden="true"
                    ></span>

                    <span id="elgSubmitText">
                        Add Guarantor
                    </span>
                </button>


                <button
                    type="button"
                    class="btn btn-primary d-none"
                    id="elgRefreshStatement"
                >
                    Refresh Statement
                </button>

            </div>

        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | Route templates
    |--------------------------------------------------------------------------
    */

    const contextUrlTemplate =
        @json($existingLoanGuarantorContextUrl);

    const searchUrlTemplate =
        @json($existingLoanGuarantorSearchUrl);

    const storeUrlTemplate =
        @json($existingLoanGuarantorStoreUrl);


    /*
    |--------------------------------------------------------------------------
    | Elements
    |--------------------------------------------------------------------------
    */

    const modalElement =
        document.getElementById(
            'addExistingLoanGuarantorModal'
        );

    if (!modalElement) {
        return;
    }

    const alertBox =
        document.getElementById('elgAlert');

    const contextLoading =
        document.getElementById('elgContextLoading');

    const mainContent =
        document.getElementById('elgMainContent');

    const searchSection =
        document.getElementById('elgSearchSection');

    const searchInput =
        document.getElementById('elgMemberSearch');

    const searchSpinner =
        document.getElementById('elgSearchSpinner');

    const searchResults =
        document.getElementById('elgSearchResults');

    const selectedSection =
        document.getElementById('elgSelectedSection');

    const form =
        document.getElementById('elgForm');

    const guarantorMemberIdInput =
        document.getElementById(
            'elgGuarantorMemberId'
        );

    const amountInput =
        document.getElementById('elgAmount');

    const amountError =
        document.getElementById('elgAmountError');

    const reasonInput =
        document.getElementById('elgReason');

    const submitButton =
        document.getElementById('elgSubmitButton');

    const submitSpinner =
        document.getElementById('elgSubmitSpinner');

    const submitText =
        document.getElementById('elgSubmitText');

    const cancelButton =
        document.getElementById('elgCancelButton');

    const refreshButton =
        document.getElementById(
            'elgRefreshStatement'
        );

    const successState =
        document.getElementById('elgSuccessState');

    const successMessage =
        document.getElementById('elgSuccessMessage');


    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    const state = {
        loanId: null,
        context: null,
        selectedMember: null,
        searchTimer: null,
        searchController: null,
        saving: false
    };


    /*
    |--------------------------------------------------------------------------
    | Bootstrap modal
    |--------------------------------------------------------------------------
    */

    let modalInstance = null;

    const getModalInstance = function () {

        if (
            typeof window.bootstrap === 'undefined'
            ||
            typeof window.bootstrap.Modal === 'undefined'
        ) {
            throw new Error(
                'Bootstrap modal support is not available.'
            );
        }

        if (!modalInstance) {

            if (
                typeof window.bootstrap.Modal
                    .getOrCreateInstance === 'function'
            ) {
                modalInstance =
                    window.bootstrap.Modal
                        .getOrCreateInstance(
                            modalElement
                        );
            } else {
                modalInstance =
                    new window.bootstrap.Modal(
                        modalElement
                    );
            }
        }

        return modalInstance;
    };


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    const money = function (value) {

        const number =
            Number(value || 0);

        return 'KES ' +
            new Intl.NumberFormat(
                'en-KE',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            ).format(number);
    };


    const normaliseMoney = function (value) {

        const parsed =
            Number(value);

        if (!Number.isFinite(parsed)) {
            return 0;
        }

        return Math.round(
            (parsed + Number.EPSILON) * 100
        ) / 100;
    };


    const routeForLoan = function (
        template,
        loanId
    ) {
        return template.replace(
            '__LOAN__',
            encodeURIComponent(
                String(loanId)
            )
        );
    };


    const getCsrfToken = function () {

        const meta =
            document.querySelector(
                'meta[name="csrf-token"]'
            );

        return meta
            ? meta.getAttribute('content')
            : '';
    };


    const showAlert = function (
        type,
        message
    ) {

        alertBox.className =
            'alert alert-' + type;

        alertBox.textContent =
            message;

        alertBox.classList.remove('d-none');
    };


    const hideAlert = function () {

        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    };


    const clearSearchResults = function () {

        searchResults.innerHTML = '';
        searchResults.classList.add('d-none');
    };


    const showSearchMessage = function (
        message
    ) {

        clearSearchResults();

        const wrapper =
            document.createElement('div');

        wrapper.className =
            'elg-empty';

        wrapper.textContent =
            message;

        searchResults.appendChild(wrapper);
        searchResults.classList.remove('d-none');
    };


    const setText = function (
        id,
        value
    ) {

        const element =
            document.getElementById(id);

        if (element) {
            element.textContent =
                value;
        }
    };


    /*
    |--------------------------------------------------------------------------
    | Error response helper
    |--------------------------------------------------------------------------
    */

    const readErrorResponse = async function (
        response
    ) {

        let data = null;

        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }

        if (
            data
            &&
            data.message
        ) {
            return data.message;
        }

        if (response.status === 403) {
            return 'You do not have permission to perform this operation.';
        }

        if (response.status === 419) {
            return 'Your session has expired. Refresh the page and try again.';
        }

        if (response.status === 404) {
            return 'The selected loan could not be found.';
        }

        if (response.status === 429) {
            return 'Too many requests were made. Please try again shortly.';
        }

        return 'The request could not be completed.';
    };


    /*
    |--------------------------------------------------------------------------
    | Reset modal
    |--------------------------------------------------------------------------
    */

    const resetModal = function () {

        if (state.searchTimer) {
            clearTimeout(
                state.searchTimer
            );
        }

        if (state.searchController) {
            state.searchController.abort();
        }

        state.context = null;
        state.selectedMember = null;
        state.searchController = null;
        state.saving = false;

        hideAlert();

        contextLoading.classList.remove(
            'd-none'
        );

        mainContent.classList.add(
            'd-none'
        );

        successState.classList.add(
            'd-none'
        );

        searchSection.classList.remove(
            'd-none'
        );

        selectedSection.classList.add(
            'd-none'
        );

        searchInput.value = '';
        searchInput.disabled = true;

        searchSpinner.classList.add(
            'd-none'
        );

        clearSearchResults();

        guarantorMemberIdInput.value = '';

        amountInput.value = '';
        amountInput.disabled = true;
        amountInput.removeAttribute('max');

        amountError.textContent = '';

        reasonInput.value = '';
        reasonInput.disabled = true;

        submitButton.disabled = true;
        submitButton.classList.remove(
            'd-none'
        );

        submitSpinner.classList.add(
            'd-none'
        );

        submitText.textContent =
            'Add Guarantor';

        cancelButton.textContent =
            'Cancel';

        refreshButton.classList.add(
            'd-none'
        );

        setText(
            'elgBorrower',
            '—'
        );

        setText(
            'elgLoan',
            '—'
        );

        setText(
            'elgLoanBalance',
            '—'
        );

        setText(
            'elgGuaranteeRequired',
            '—'
        );

        setText(
            'elgCurrentlyGuaranteed',
            '—'
        );

        setText(
            'elgGuaranteeRemaining',
            '—'
        );

        setText(
            'elgAmountCapacity',
            'KES 0.00'
        );

        setText(
            'elgAmountRemaining',
            'KES 0.00'
        );

        const ruleMessage =
            document.getElementById(
                'elgLoanRuleMessage'
            );

        ruleMessage.textContent = '';
        ruleMessage.classList.add(
            'd-none'
        );
    };


    /*
    |--------------------------------------------------------------------------
    | Render loan context
    |--------------------------------------------------------------------------
    */

    const renderContext = function (
        loan
    ) {

        state.context = loan;

        setText(
            'elgBorrower',
            String(
                loan.borrower_name || ''
            )
            +
            (
                loan.borrower_sacco_id
                    ? ' — ' +
                      loan.borrower_sacco_id
                    : ''
            )
        );


        setText(
            'elgLoan',
            String(
                loan.loan_type_name
                || 'Loan'
            )
            +
            ' — #' +
            String(
                loan.loan_id
            )
        );


        setText(
            'elgLoanBalance',
            money(
                loan.loan_balance
            )
        );


        setText(
            'elgGuaranteeRequired',
            money(
                loan.guarantee_required
            )
        );


        setText(
            'elgCurrentlyGuaranteed',
            money(
                loan.currently_guaranteed
            )
        );


        setText(
            'elgGuaranteeRemaining',
            money(
                loan.guarantee_remaining
            )
        );


        setText(
            'elgAmountRemaining',
            money(
                loan.guarantee_remaining
            )
        );


        contextLoading.classList.add(
            'd-none'
        );

        mainContent.classList.remove(
            'd-none'
        );


        const ruleMessage =
            document.getElementById(
                'elgLoanRuleMessage'
            );


        if (!loan.can_add_guarantor) {

            searchInput.disabled = true;
            submitButton.disabled = true;

            ruleMessage.textContent =
                loan.blocking_message
                ||
                'No additional guarantor can be added to this loan.';

            ruleMessage.classList.remove(
                'd-none'
            );

            searchSection.classList.add(
                'd-none'
            );

            return;
        }


        searchSection.classList.remove(
            'd-none'
        );

        searchInput.disabled = false;

        /*
         * We do not automatically focus on very small mobile screens,
         * because opening the software keyboard immediately can obscure
         * the loan context.
         */
        if (
            window.innerWidth >= 768
        ) {
            setTimeout(
                function () {
                    searchInput.focus();
                },
                100
            );
        }
    };


    /*
    |--------------------------------------------------------------------------
    | Load loan context
    |--------------------------------------------------------------------------
    */

    const loadContext = async function () {

        if (!state.loanId) {
            showAlert(
                'danger',
                'No loan was selected.'
            );

            contextLoading.classList.add(
                'd-none'
            );

            return;
        }


        const url =
            routeForLoan(
                contextUrlTemplate,
                state.loanId
            );


        try {

            const response =
                await fetch(
                    url,
                    {
                        method: 'GET',

                        headers: {
                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest'
                        },

                        credentials:
                            'same-origin'
                    }
                );


            if (!response.ok) {

                const message =
                    await readErrorResponse(
                        response
                    );

                throw new Error(
                    message
                );
            }


            const data =
                await response.json();


            if (
                !data.success
                ||
                !data.loan
            ) {
                throw new Error(
                    data.message
                    ||
                    'Loan information could not be loaded.'
                );
            }


            renderContext(
                data.loan
            );


        } catch (error) {

            contextLoading.classList.add(
                'd-none'
            );

            mainContent.classList.remove(
                'd-none'
            );

            searchSection.classList.add(
                'd-none'
            );

            showAlert(
                'danger',
                error.message
                ||
                'Loan information could not be loaded.'
            );
        }
    };


    /*
    |--------------------------------------------------------------------------
    | Select search result
    |--------------------------------------------------------------------------
    */

    const selectMember = function (
        member,
        resultButton
    ) {

        state.selectedMember =
            member;

        guarantorMemberIdInput.value =
            member.member_id;


        /*
         * Mark selected search result.
         */
        searchResults
            .querySelectorAll(
                '.elg-result'
            )
            .forEach(
                function (button) {

                    button.classList.toggle(
                        'is-selected',
                        button === resultButton
                    );
                }
            );


        let selectedName =
            member.member_name
            || 'Member';


        if (member.is_self_guarantee) {
            selectedName +=
                ' — Self Guarantee';
        }


        setText(
            'elgSelectedName',
            selectedName
        );


        const metaParts = [];

        if (member.member_sacco_id) {
            metaParts.push(
                'SACCO No. ' +
                member.member_sacco_id
            );
        }

        if (member.member_national_id) {
            metaParts.push(
                'ID ' +
                member.member_national_id
            );
        }

        if (member.member_phone_no) {
            metaParts.push(
                member.member_phone_no
            );
        }


        setText(
            'elgSelectedMeta',
            metaParts.join(' • ')
        );


        setText(
            'elgSelectedMaximum',
            money(
                member.maximum_for_this_loan
            )
        );


        setText(
            'elgSelectedSavings',
            money(
                member.total_savings
            )
        );


        setText(
            'elgSelectedTiedOthers',
            money(
                member.tied_to_others
            )
        );


        setText(
            'elgSelectedTiedSelf',
            money(
                member.tied_to_self
            )
        );


        setText(
            'elgSelectedPending',
            money(
                member.pending_guarantees
            )
        );


        setText(
            'elgSelectedCapacity',
            money(
                member.available_capacity
            )
        );


        setText(
            'elgAmountCapacity',
            money(
                member.maximum_for_this_loan
            )
        );


        amountInput.value = '';
        amountInput.disabled = false;

        amountInput.max =
            normaliseMoney(
                member.maximum_for_this_loan
            ).toFixed(2);


        reasonInput.disabled = false;

        selectedSection.classList.remove(
            'd-none'
        );


        amountError.textContent = '';

        validateForm();


        /*
         * Scroll selected area into view on mobile.
         */
        if (
            window.innerWidth < 768
        ) {
            selectedSection.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        } else {
            amountInput.focus();
        }
    };


    /*
    |--------------------------------------------------------------------------
    | Render search results
    |--------------------------------------------------------------------------
    */

    const renderSearchResults = function (
        members
    ) {

        clearSearchResults();


        if (
            !Array.isArray(members)
            ||
            members.length === 0
        ) {
            showSearchMessage(
                'No eligible member with available guarantee capacity was found.'
            );

            return;
        }


        members.forEach(
            function (member) {

                const button =
                    document.createElement(
                        'button'
                    );

                button.type =
                    'button';

                button.className =
                    'elg-result';

                button.setAttribute(
                    'role',
                    'option'
                );


                /*
                 * Top row.
                 */
                const top =
                    document.createElement(
                        'div'
                    );

                top.className =
                    'elg-result-top';


                const identity =
                    document.createElement(
                        'div'
                    );


                const name =
                    document.createElement(
                        'div'
                    );

                name.className =
                    'elg-result-name';

                name.textContent =
                    member.member_name
                    || 'Member';


                if (
                    member.is_self_guarantee
                ) {

                    const badge =
                        document.createElement(
                            'span'
                        );

                    badge.className =
                        'elg-self-badge';

                    badge.textContent =
                        'Self';

                    name.appendChild(
                        badge
                    );
                }


                const identityLine =
                    document.createElement(
                        'div'
                    );

                identityLine.className =
                    'elg-result-identity';


                const identityParts = [];

                if (
                    member.member_sacco_id
                ) {
                    identityParts.push(
                        'SACCO No. ' +
                        member.member_sacco_id
                    );
                }

                if (
                    member.member_national_id
                ) {
                    identityParts.push(
                        'ID ' +
                        member.member_national_id
                    );
                }

                if (
                    member.member_phone_no
                ) {
                    identityParts.push(
                        member.member_phone_no
                    );
                }


                identityLine.textContent =
                    identityParts.join(
                        ' • '
                    );


                identity.appendChild(
                    name
                );

                identity.appendChild(
                    identityLine
                );


                /*
                 * Available amount.
                 */
                const capacity =
                    document.createElement(
                        'div'
                    );

                capacity.className =
                    'elg-result-capacity';


                const capacityLabel =
                    document.createElement(
                        'div'
                    );

                capacityLabel.className =
                    'elg-capacity-label';

                capacityLabel.textContent =
                    'Maximum Available';


                const capacityValue =
                    document.createElement(
                        'div'
                    );

                capacityValue.className =
                    'elg-capacity-value';

                capacityValue.textContent =
                    money(
                        member.maximum_for_this_loan
                    );


                capacity.appendChild(
                    capacityLabel
                );

                capacity.appendChild(
                    capacityValue
                );


                top.appendChild(
                    identity
                );

                top.appendChild(
                    capacity
                );


                /*
                 * Financial detail row.
                 */
                const details =
                    document.createElement(
                        'div'
                    );

                details.className =
                    'elg-result-details';


                const detailValues = [
                    {
                        label:
                            'Savings',

                        value:
                            member.total_savings
                    },
                    {
                        label:
                            'Tied Others',

                        value:
                            member.tied_to_others
                    },
                    {
                        label:
                            'Tied Self',

                        value:
                            member.tied_to_self
                    },
                    {
                        label:
                            'Pending',

                        value:
                            member.pending_guarantees
                    }
                ];


                detailValues.forEach(
                    function (detail) {

                        const wrapper =
                            document.createElement(
                                'div'
                            );

                        const label =
                            document.createElement(
                                'div'
                            );

                        label.className =
                            'elg-result-detail-label';

                        label.textContent =
                            detail.label;


                        const value =
                            document.createElement(
                                'div'
                            );

                        value.className =
                            'elg-result-detail-value';

                        value.textContent =
                            money(
                                detail.value
                            );


                        wrapper.appendChild(
                            label
                        );

                        wrapper.appendChild(
                            value
                        );

                        details.appendChild(
                            wrapper
                        );
                    }
                );


                button.appendChild(
                    top
                );

                button.appendChild(
                    details
                );


                button.addEventListener(
                    'click',
                    function () {

                        selectMember(
                            member,
                            button
                        );
                    }
                );


                searchResults.appendChild(
                    button
                );
            }
        );


        searchResults.classList.remove(
            'd-none'
        );
    };


    /*
    |--------------------------------------------------------------------------
    | AJAX member search
    |--------------------------------------------------------------------------
    */

    const searchMembers = async function (
        term
    ) {

        const query =
            String(term || '')
                .trim();


        if (query.length < 2) {
            clearSearchResults();
            return;
        }


        /*
         * Cancel previous search.
         */
        if (state.searchController) {
            state.searchController.abort();
        }


        state.searchController =
            new AbortController();


        searchSpinner.classList.remove(
            'd-none'
        );


        const baseUrl =
            routeForLoan(
                searchUrlTemplate,
                state.loanId
            );


        const url =
            baseUrl
            +
            '?q='
            +
            encodeURIComponent(
                query
            );


        try {

            const response =
                await fetch(
                    url,
                    {
                        method: 'GET',

                        headers: {
                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest'
                        },

                        credentials:
                            'same-origin',

                        signal:
                            state
                                .searchController
                                .signal
                    }
                );


            if (!response.ok) {

                const message =
                    await readErrorResponse(
                        response
                    );

                throw new Error(
                    message
                );
            }


            const data =
                await response.json();


            if (!data.success) {

                throw new Error(
                    data.message
                    ||
                    'Member search failed.'
                );
            }


            renderSearchResults(
                data.results
                || []
            );


        } catch (error) {

            if (
                error.name ===
                'AbortError'
            ) {
                return;
            }


            showSearchMessage(
                error.message
                ||
                'Member search could not be completed.'
            );

        } finally {

            searchSpinner.classList.add(
                'd-none'
            );
        }
    };


    /*
    |--------------------------------------------------------------------------
    | Form validation
    |--------------------------------------------------------------------------
    */

    const validateForm = function () {

        amountError.textContent = '';

        if (
            !state.context
            ||
            !state.context.can_add_guarantor
            ||
            !state.selectedMember
            ||
            state.saving
        ) {
            submitButton.disabled = true;
            return false;
        }


        const amount =
            normaliseMoney(
                amountInput.value
            );


        const memberMaximum =
            normaliseMoney(
                state
                    .selectedMember
                    .maximum_for_this_loan
            );


        const loanRemaining =
            normaliseMoney(
                state
                    .context
                    .guarantee_remaining
            );


        const maximumAllowed =
            Math.min(
                memberMaximum,
                loanRemaining
            );


        if (amount <= 0) {

            if (
                amountInput.value !== ''
            ) {
                amountError.textContent =
                    'Enter a guarantee amount greater than zero.';
            }

            submitButton.disabled = true;
            return false;
        }


        if (
            amount
            >
            maximumAllowed + 0.01
        ) {

            amountError.textContent =
                'Maximum allowed for this guarantor and loan is '
                +
                money(
                    maximumAllowed
                )
                +
                '.';

            submitButton.disabled = true;
            return false;
        }


        const reason =
            reasonInput.value
                .trim();


        if (reason.length < 3) {
            submitButton.disabled = true;
            return false;
        }


        submitButton.disabled = false;

        return true;
    };


    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    const saveGuarantor = async function () {

        if (
            !validateForm()
            ||
            state.saving
        ) {
            return;
        }


        const amount =
            normaliseMoney(
                amountInput.value
            );


        const member =
            state.selectedMember;


        /*
         * Final human confirmation before tying savings.
         */
        const confirmed =
            window.confirm(
                'Add '
                +
                member.member_name
                +
                ' as guarantor for Loan #'
                +
                state.loanId
                +
                ' for '
                +
                money(amount)
                +
                '?\n\n'
                +
                'This will tie the member\'s savings to this loan.'
            );


        if (!confirmed) {
            return;
        }


        state.saving = true;

        hideAlert();

        submitButton.disabled = true;

        submitSpinner.classList.remove(
            'd-none'
        );

        submitText.textContent =
            'Adding Guarantor...';


        const url =
            routeForLoan(
                storeUrlTemplate,
                state.loanId
            );


        try {

            const response =
                await fetch(
                    url,
                    {
                        method: 'POST',

                        headers: {
                            'Accept':
                                'application/json',

                            'Content-Type':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest',

                            'X-CSRF-TOKEN':
                                getCsrfToken()
                        },

                        credentials:
                            'same-origin',

                        body:
                            JSON.stringify({
                                guarantor_member_id:
                                    Number(
                                        guarantorMemberIdInput
                                            .value
                                    ),

                                amount:
                                    amount,

                                reason:
                                    reasonInput
                                        .value
                                        .trim()
                            })
                    }
                );


            if (!response.ok) {

                let data = null;

                try {
                    data =
                        await response.json();
                } catch (error) {
                    data = null;
                }


                /*
                 * Validation errors.
                 */
                if (
                    response.status === 422
                    &&
                    data
                    &&
                    data.errors
                ) {

                    const messages = [];

                    Object.values(
                        data.errors
                    ).forEach(
                        function (errors) {

                            if (
                                Array.isArray(errors)
                            ) {
                                errors.forEach(
                                    function (message) {
                                        messages.push(
                                            message
                                        );
                                    }
                                );
                            }
                        }
                    );


                    throw new Error(
                        messages.join(' ')
                        ||
                        data.message
                        ||
                        'The submitted details are invalid.'
                    );
                }


                throw new Error(
                    (
                        data
                        &&
                        data.message
                    )
                        ?
                        data.message
                        :
                        (
                            response.status === 403
                                ?
                                'You do not have permission to add guarantors.'
                                :
                            response.status === 419
                                ?
                                'Your session has expired. Refresh the page and try again.'
                                :
                                'The guarantor could not be added.'
                        )
                );
            }


            const data =
                await response.json();


            if (!data.success) {
                throw new Error(
                    data.message
                    ||
                    'The guarantor could not be added.'
                );
            }


            /*
             * Successful financial write.
             */
            form.classList.add(
                'd-none'
            );

            searchSection.classList.add(
                'd-none'
            );

            selectedSection.classList.add(
                'd-none'
            );

            submitButton.classList.add(
                'd-none'
            );

            successState.classList.remove(
                'd-none'
            );

            refreshButton.classList.remove(
                'd-none'
            );

            cancelButton.textContent =
                'Close';


            const result =
                data.data
                || {};


            let message =
                result.guarantor_name
                ?
                result.guarantor_name
                +
                ' has been added for '
                +
                money(
                    result.amount
                    || amount
                )
                +
                '.'
                :
                'The guarantor has been added successfully.';


            if (
                result.guarantee_remaining
                !== undefined
                &&
                result.guarantee_remaining
                !== null
            ) {

                message +=
                    ' Remaining guarantee requirement: '
                    +
                    money(
                        result.guarantee_remaining
                    )
                    +
                    '.';
            }


            successMessage.textContent =
                message;


        } catch (error) {

            state.saving = false;

            submitSpinner.classList.add(
                'd-none'
            );

            submitText.textContent =
                'Add Guarantor';


            showAlert(
                'danger',
                error.message
                ||
                'The guarantor could not be added.'
            );


            /*
             * Revalidate because the server may have rejected a stale
             * amount/capacity.
             */
            validateForm();
        }
    };


    /*
    |--------------------------------------------------------------------------
    | Add Guarantor button
    |--------------------------------------------------------------------------
    |
    | Uses event delegation because there may be multiple loan cards but
    | only one modal.
    |
    */

    document.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.js-add-guarantor'
                );


            if (!button) {
                return;
            }


            event.preventDefault();


            const loanId =
                Number(
                    button.getAttribute(
                        'data-loan-id'
                    )
                );


            if (
                !Number.isInteger(loanId)
                ||
                loanId <= 0
            ) {
                window.alert(
                    'A valid loan was not selected.'
                );

                return;
            }


            state.loanId =
                loanId;


            resetModal();


            try {

                getModalInstance().show();

                loadContext();

            } catch (error) {

                console.error(
                    error
                );

                window.alert(
                    'The guarantor window could not be opened.'
                );
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Search debounce
    |--------------------------------------------------------------------------
    */

    searchInput.addEventListener(
        'input',
        function () {

            const term =
                searchInput.value
                    .trim();


            /*
             * Changing the search invalidates the selected candidate only
             * when a new actual search is about to take place.
             */
            if (state.searchTimer) {
                clearTimeout(
                    state.searchTimer
                );
            }


            if (term.length < 2) {

                clearSearchResults();

                return;
            }


            state.searchTimer =
                setTimeout(
                    function () {
                        searchMembers(
                            term
                        );
                    },
                    350
                );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Input validation
    |--------------------------------------------------------------------------
    */

    amountInput.addEventListener(
        'input',
        validateForm
    );

    amountInput.addEventListener(
        'blur',
        validateForm
    );

    reasonInput.addEventListener(
        'input',
        validateForm
    );


    /*
    |--------------------------------------------------------------------------
    | Submit
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'submit',
        function (event) {

            event.preventDefault();

            saveGuarantor();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Refresh statement after successful write
    |--------------------------------------------------------------------------
    */

    refreshButton.addEventListener(
        'click',
        function () {

            window.location.reload();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Modal cleanup
    |--------------------------------------------------------------------------
    */

    modalElement.addEventListener(
        'hidden.bs.modal',
        function () {

            if (state.searchTimer) {
                clearTimeout(
                    state.searchTimer
                );
            }

            if (state.searchController) {
                state.searchController.abort();
            }

            /*
             * If save succeeded and the user closes instead of pressing
             * Refresh Statement, refresh anyway so financial figures and
             * guarantor tables cannot remain stale.
             */
            if (
                !successState.classList.contains(
                    'd-none'
                )
            ) {
                window.location.reload();
                return;
            }


            state.loanId = null;
            state.context = null;
            state.selectedMember = null;
        }
    );

});
</script>