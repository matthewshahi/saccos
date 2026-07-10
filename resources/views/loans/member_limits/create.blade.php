@extends('layouts.app')

@section('content')

<div class="main-content pt-4">

    {{-- ============================================================
         Page Heading
    ============================================================ --}}
    <div class="breadcrumb d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

        <div>
            <h1 class="mb-1">
                Add Individual Loan Limit
            </h1>

            <ul class="mb-0">
                <li>
                    <a href="{{ url('/dashboard') }}">
                        Dashboard
                    </a>
                </li>

                <li>
                    <a href="{{ route('loans.member_limits.index') }}">
                        Individual Loan Limits
                    </a>
                </li>

                <li>
                    Add Limit
                </li>
            </ul>
        </div>

        <div>
            <a
                href="{{ route('loans.member_limits.index') }}"
                class="btn btn-outline-secondary btn-rounded"
            >
                <i class="i-Arrow-Back-3 me-1"></i>
                Back to List
            </a>
        </div>

    </div>

    <div class="separator-breadcrumb border-top mb-4"></div>

    {{-- ============================================================
         Validation Errors
    ============================================================ --}}
    @if ($errors->any())
        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >
            <strong>
                The individual loan limit could not be saved.
            </strong>

            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>
        </div>
    @endif

    <div class="row">

        {{-- ========================================================
             Main Form
        ======================================================== --}}
        <div class="col-lg-8">

            <div class="card mb-4">
                <div class="card-body">

                    <div class="card-title mb-1">
                        Member Loan Limit Details
                    </div>

                    <p class="text-muted mb-4">
                        Search for the member, select a loan type and enter
                        the lower maximum amount that will apply specifically
                        to that member.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('loans.member_limits.store') }}"
                        id="memberLoanLimitForm"
                        novalidate
                    >
                        @csrf

                        <div class="row">

                            {{-- =================================================
                                 Smart Member Search
                            ================================================= --}}
                            <div class="col-md-12 form-group mb-4">

                                <label
                                    for="memberSearchInput"
                                    class="form-label fw-semibold"
                                >
                                    Member
                                    <span class="text-danger">*</span>
                                </label>

                                @php
                                    $selectedMemberLabel = '';

                                    if (!empty($selectedMember)) {
                                        $selectedMemberLabel =
                                            $selectedMember->member_name
                                            . (
                                                !empty($selectedMember->member_sacco_id)
                                                    ? ' — ' . $selectedMember->member_sacco_id
                                                    : ''
                                            );
                                    }

                                    $memberDisplayValue = old(
                                        'member_search_display',
                                        $selectedMemberLabel
                                    );

                                    $selectedMemberId = old(
                                        'member_loan_limit_member_id',
                                        $selectedMember->member_id ?? ''
                                    );
                                @endphp

                                <div class="member-search-wrapper">

                                    {{-- Visible search input --}}
                                    <div class="input-group">

                                        <span class="input-group-text">
                                            <i class="i-Search-People"></i>
                                        </span>

                                        <input
                                            type="text"
                                            id="memberSearchInput"
                                            class="form-control @error('member_loan_limit_member_id') is-invalid @enderror"
                                            value="{{ $memberDisplayValue }}"
                                            placeholder="Search by name, SACCO number, ID, phone or email"
                                            autocomplete="off"
                                            spellcheck="false"
                                            aria-autocomplete="list"
                                            aria-controls="memberSearchResults"
                                            aria-expanded="false"
                                        >

                                        <button
                                            type="button"
                                            id="clearMemberButton"
                                            class="btn btn-outline-secondary {{ $selectedMemberId ? '' : 'd-none' }}"
                                            title="Clear selected member"
                                            aria-label="Clear selected member"
                                        >
                                            <i class="i-Close"></i>
                                        </button>

                                    </div>

                                    {{-- Actual submitted member ID --}}
                                    <input
                                        type="hidden"
                                        name="member_loan_limit_member_id"
                                        id="memberLoanLimitMemberId"
                                        value="{{ $selectedMemberId }}"
                                    >

                                    {{-- Preserve visible member text --}}
                                    <input
                                        type="hidden"
                                        name="member_search_display"
                                        id="memberSearchDisplay"
                                        value="{{ $memberDisplayValue }}"
                                    >

                                    {{-- AJAX results --}}
                                    <div
                                        id="memberSearchResults"
                                        class="member-search-results d-none"
                                        role="listbox"
                                        aria-label="Member search results"
                                    ></div>

                                </div>

                                @error('member_loan_limit_member_id')
                                    <div class="text-danger text-small mt-1">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <small class="form-text text-muted">
                                    Type at least two characters, then select the
                                    correct member from the search results.
                                </small>

                            </div>

                            {{-- =================================================
                                 Loan Type
                            ================================================= --}}
                            <div class="col-md-7 form-group mb-4">

                                <label
                                    for="member_loan_limit_loan_type_id"
                                    class="form-label fw-semibold"
                                >
                                    Loan Type
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="member_loan_limit_loan_type_id"
                                    id="member_loan_limit_loan_type_id"
                                    class="form-select @error('member_loan_limit_loan_type_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">
                                        Select loan type
                                    </option>

                                    @foreach ($loanTypes as $loanType)
                                        <option
                                            value="{{ $loanType->loan_type_id }}"
                                            data-name="{{ $loanType->loan_type_name }}"
                                            data-code="{{ $loanType->loan_type_code }}"
                                            data-max="{{ number_format(
                                                (float) $loanType->loan_type_max_amount,
                                                2,
                                                '.',
                                                ''
                                            ) }}"
                                            {{ (string) old(
                                                'member_loan_limit_loan_type_id'
                                            ) === (string) $loanType->loan_type_id
                                                ? 'selected'
                                                : '' }}
                                        >
                                            {{ $loanType->loan_type_name }}

                                            @if (!empty($loanType->loan_type_code))
                                                — {{ $loanType->loan_type_code }}
                                            @endif

                                            — Maximum KES
                                            {{ number_format(
                                                (float) $loanType->loan_type_max_amount,
                                                2
                                            ) }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('member_loan_limit_loan_type_id')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror

                            </div>

                            {{-- =================================================
                                 Standard Loan-Type Maximum
                            ================================================= --}}
                            <div class="col-md-5 form-group mb-4">

                                <label
                                    for="standardMaximumDisplay"
                                    class="form-label fw-semibold"
                                >
                                    Standard Loan-Type Maximum
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        KES
                                    </span>

                                    <input
                                        type="text"
                                        id="standardMaximumDisplay"
                                        class="form-control readonly-amount"
                                        value=""
                                        placeholder="Select loan type"
                                        readonly
                                    >

                                </div>

                            </div>

                            {{-- =================================================
                                 Individual Limit
                            ================================================= --}}
                            <div class="col-md-7 form-group mb-4">

                                <label
                                    for="member_loan_limit_amount"
                                    class="form-label fw-semibold"
                                >
                                    Individual Maximum Limit
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        KES
                                    </span>

                                    <input
                                        type="number"
                                        name="member_loan_limit_amount"
                                        id="member_loan_limit_amount"
                                        class="form-control @error('member_loan_limit_amount') is-invalid @enderror"
                                        value="{{ old('member_loan_limit_amount') }}"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="Enter member's special maximum"
                                        required
                                    >

                                    @error('member_loan_limit_amount')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror

                                </div>

                                <small
                                    id="limitHelp"
                                    class="form-text text-muted"
                                >
                                    The amount must be lower than the selected
                                    loan-type maximum.
                                </small>

                            </div>

                            {{-- =================================================
                                 Reduction
                            ================================================= --}}
                            <div class="col-md-5 form-group mb-4">

                                <label
                                    for="differenceDisplay"
                                    class="form-label fw-semibold"
                                >
                                    Reduction from Standard Limit
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        KES
                                    </span>

                                    <input
                                        type="text"
                                        id="differenceDisplay"
                                        class="form-control readonly-amount"
                                        value=""
                                        placeholder="0.00"
                                        readonly
                                    >

                                </div>

                            </div>

                            {{-- =================================================
                                 Activation Status
                            ================================================= --}}
                            <div class="col-md-12 form-group mb-4">

                                <div class="border rounded p-3 bg-light">

                                    <div class="d-flex justify-content-between align-items-center gap-3">

                                        <div>
                                            <div class="fw-semibold">
                                                Activate this individual limit
                                            </div>

                                            <div class="text-muted text-small">
                                                When inactive, the normal
                                                loan-type maximum will apply
                                                to the member.
                                            </div>
                                        </div>

                                        <div>
                                            <input
                                                type="hidden"
                                                name="member_loan_limit_active"
                                                value="0"
                                            >

                                            <label class="switch switch-primary mb-0">
                                                <input
                                                    type="checkbox"
                                                    name="member_loan_limit_active"
                                                    value="1"
                                                    {{ (int) old(
                                                        'member_loan_limit_active',
                                                        1
                                                    ) === 1
                                                        ? 'checked'
                                                        : '' }}
                                                >

                                                <span class="slider"></span>
                                            </label>
                                        </div>

                                    </div>

                                </div>

                                @error('member_loan_limit_active')
                                    <div class="text-danger text-small mt-1">
                                        {{ $message }}
                                    </div>
                                @enderror

                            </div>

                            {{-- =================================================
                                 Form Actions
                            ================================================= --}}
                            <div class="col-md-12">

                                <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 border-top pt-4">

                                    <a
                                        href="{{ route('loans.member_limits.index') }}"
                                        class="btn btn-outline-secondary"
                                    >
                                        Cancel
                                    </a>

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                        id="saveButton"
                                    >
                                        <i class="i-Disk me-1"></i>
                                        Save Individual Limit
                                    </button>

                                </div>

                            </div>

                        </div>
                    </form>

                </div>
            </div>

        </div>

        {{-- ========================================================
             Guidance
        ======================================================== --}}
        <div class="col-lg-4">

            <div class="card mb-4">
                <div class="card-body">

                    <h5 class="card-title">
                        How It Works
                    </h5>

                    <div class="limit-guide-item">

                        <div class="guide-number">
                            1
                        </div>

                        <div>
                            <strong>
                                Search and select a member
                            </strong>

                            <p class="text-muted mb-0">
                                Search by name, SACCO number, national ID,
                                phone number or email address.
                            </p>
                        </div>

                    </div>

                    <div class="limit-guide-item">

                        <div class="guide-number">
                            2
                        </div>

                        <div>
                            <strong>
                                Select a loan type
                            </strong>

                            <p class="text-muted mb-0">
                                The standard loan-type maximum will be shown
                                automatically.
                            </p>
                        </div>

                    </div>

                    <div class="limit-guide-item">

                        <div class="guide-number">
                            3
                        </div>

                        <div>
                            <strong>
                                Enter a lower personal limit
                            </strong>

                            <p class="text-muted mb-0">
                                The member-specific amount must be strictly
                                below the loan-type maximum.
                            </p>
                        </div>

                    </div>

                </div>
            </div>

            <div class="card border-warning mb-4">
                <div class="card-body">

                    <div class="d-flex">

                        <i class="i-Warning-Window text-warning text-24 me-3"></i>

                        <div>
                            <h6 class="mb-1">
                                Important
                            </h6>

                            <p class="text-muted mb-0">
                                Deleting or deactivating this record causes
                                the member to revert to the normal maximum
                                configured for the selected loan type.
                            </p>
                        </div>

                    </div>

                </div>
            </div>

            @if (
                $currentPeriod
                && !empty($currentPeriod->period_name)
            )
                <div class="card mb-4">
                    <div class="card-body">

                        <div class="text-muted text-small">
                            Active accounting period
                        </div>

                        <h5 class="mb-0">
                            {{ $currentPeriod->period_name }}
                        </h5>

                    </div>
                </div>
            @endif

        </div>

    </div>

</div>

<style>
    .text-small {
        font-size: 12px;
    }

    .btn-rounded {
        border-radius: 50px;
    }

    .readonly-amount {
        background-color: #747b88 !important;
        color: #ffffff !important;
        font-weight: 700;
    }

    .readonly-amount::placeholder {
        color: rgba(255, 255, 255, 0.75);
    }

    /*
    |--------------------------------------------------------------------------
    | Member Search
    |--------------------------------------------------------------------------
    */
    .member-search-wrapper {
        position: relative;
    }

    .member-search-results {
        position: absolute;
        top: calc(100% + 5px);
        left: 0;
        right: 0;
        z-index: 1080;
        max-height: 330px;
        overflow-y: auto;
        background: #ffffff;
        border: 1px solid #d8dbe0;
        border-radius: 6px;
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.16);
    }

    .member-search-item {
        width: 100%;
        display: block;
        padding: 12px 14px;
        border: 0;
        border-bottom: 1px solid #eeeeee;
        background: #ffffff;
        color: #333333;
        text-align: left;
        cursor: pointer;
    }

    .member-search-item:last-child {
        border-bottom: 0;
    }

    .member-search-item:hover,
    .member-search-item.active {
        background: #f3ecf9;
    }

    .member-search-name {
        display: block;
        font-weight: 700;
        color: #333333;
    }

    .member-search-meta {
        display: block;
        margin-top: 3px;
        color: #6c757d;
        font-size: 12px;
        line-height: 1.5;
    }

    .member-search-message {
        padding: 15px;
        color: #6c757d;
        text-align: center;
    }

    .member-search-selected {
        border-color: #663399 !important;
        box-shadow: 0 0 0 0.12rem rgba(102, 51, 153, 0.12);
    }

    /*
    |--------------------------------------------------------------------------
    | Guidance
    |--------------------------------------------------------------------------
    */
    .limit-guide-item {
        display: flex;
        gap: 12px;
        padding: 15px 0;
        border-bottom: 1px solid #eeeeee;
    }

    .limit-guide-item:last-child {
        border-bottom: 0;
    }

    .guide-number {
        width: 32px;
        height: 32px;
        min-width: 32px;
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 50%;
        background: #663399;
        color: #ffffff;
        font-weight: 700;
    }

    @media (max-width: 767.98px) {
        .breadcrumb .btn {
            width: 100%;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Smart Member Search
    |--------------------------------------------------------------------------
    */
    const memberSearchUrl = @json(
        route('loans.member_limits.members.search')
    );

    const memberSearchInput = document.getElementById(
        'memberSearchInput'
    );

    const memberIdInput = document.getElementById(
        'memberLoanLimitMemberId'
    );

    const memberDisplayInput = document.getElementById(
        'memberSearchDisplay'
    );

    const memberResults = document.getElementById(
        'memberSearchResults'
    );

    const clearMemberButton = document.getElementById(
        'clearMemberButton'
    );

    const memberLoanLimitForm = document.getElementById(
        'memberLoanLimitForm'
    );

    let searchTimer = null;
    let searchController = null;
    let activeResultIndex = -1;
    let currentResults = [];

    function hideMemberResults() {
        memberResults.classList.add('d-none');
        memberResults.innerHTML = '';

        memberSearchInput.setAttribute(
            'aria-expanded',
            'false'
        );

        activeResultIndex = -1;
        currentResults = [];
    }

    function showSearchMessage(message) {
        memberResults.innerHTML = '';

        const messageElement = document.createElement('div');

        messageElement.className = 'member-search-message';
        messageElement.textContent = message;

        memberResults.appendChild(messageElement);
        memberResults.classList.remove('d-none');

        memberSearchInput.setAttribute(
            'aria-expanded',
            'true'
        );
    }

    function buildMemberMeta(member) {
        const details = [];

        if (member.sacco_id) {
            details.push(
                'SACCO No: ' + member.sacco_id
            );
        }

        if (member.national_id) {
            details.push(
                'ID: ' + member.national_id
            );
        }

        if (member.phone) {
            details.push(member.phone);
        }

        if (member.email) {
            details.push(member.email);
        }

        return details.join(' • ');
    }

    function clearSelectedMember(clearVisibleText = true) {
        memberIdInput.value = '';

        if (clearVisibleText) {
            memberSearchInput.value = '';
            memberDisplayInput.value = '';
        }

        memberSearchInput.classList.remove(
            'member-search-selected'
        );

        clearMemberButton.classList.add('d-none');

        memberSearchInput.setCustomValidity('');

        hideMemberResults();
    }

    function selectMember(member) {
        memberIdInput.value = String(member.id);
        memberSearchInput.value = member.label;
        memberDisplayInput.value = member.label;

        memberSearchInput.classList.add(
            'member-search-selected'
        );

        clearMemberButton.classList.remove('d-none');

        memberSearchInput.setCustomValidity('');

        hideMemberResults();
    }

    function renderMemberResults(members) {
        memberResults.innerHTML = '';

        activeResultIndex = -1;
        currentResults = members;

        if (!members.length) {
            showSearchMessage(
                'No matching active member was found.'
            );

            return;
        }

        members.forEach(function (member, index) {
            const resultButton = document.createElement(
                'button'
            );

            resultButton.type = 'button';
            resultButton.className = 'member-search-item';
            resultButton.setAttribute('role', 'option');
            resultButton.dataset.index = String(index);

            const memberName = document.createElement(
                'span'
            );

            memberName.className = 'member-search-name';
            memberName.textContent = member.name;

            const memberMeta = document.createElement(
                'span'
            );

            memberMeta.className = 'member-search-meta';

            const metaText = buildMemberMeta(member);

            memberMeta.textContent = metaText ||
                'Active member';

            resultButton.appendChild(memberName);
            resultButton.appendChild(memberMeta);

            resultButton.addEventListener(
                'click',
                function () {
                    selectMember(member);
                }
            );

            memberResults.appendChild(resultButton);
        });

        memberResults.classList.remove('d-none');

        memberSearchInput.setAttribute(
            'aria-expanded',
            'true'
        );
    }

    function updateActiveResult() {
        const resultItems =
            memberResults.querySelectorAll(
                '.member-search-item'
            );

        resultItems.forEach(function (item, index) {
            item.classList.toggle(
                'active',
                index === activeResultIndex
            );
        });

        if (
            activeResultIndex >= 0 &&
            resultItems[activeResultIndex]
        ) {
            resultItems[
                activeResultIndex
            ].scrollIntoView({
                block: 'nearest'
            });
        }
    }

    async function searchMembers(searchText) {
        if (searchController) {
            searchController.abort();
        }

        searchController = new AbortController();

        showSearchMessage('Searching members...');

        try {
            const response = await fetch(
                memberSearchUrl +
                '?q=' +
                encodeURIComponent(searchText),
                {
                    method: 'GET',

                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },

                    signal: searchController.signal
                }
            );

            if (!response.ok) {
                throw new Error(
                    'The member search request failed.'
                );
            }

            const members = await response.json();

            renderMemberResults(
                Array.isArray(members)
                    ? members
                    : []
            );
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error(error);

            showSearchMessage(
                'Unable to search members. Please try again.'
            );
        }
    }

    memberSearchInput.addEventListener(
        'input',
        function () {
            const searchText = this.value.trim();

            /*
             * Changing the displayed value invalidates the
             * previously selected member ID.
             */
            memberIdInput.value = '';
            memberDisplayInput.value = searchText;

            memberSearchInput.classList.remove(
                'member-search-selected'
            );

            clearMemberButton.classList.toggle(
                'd-none',
                searchText === ''
            );

            memberSearchInput.setCustomValidity('');

            clearTimeout(searchTimer);

            if (searchText.length < 2) {
                hideMemberResults();
                return;
            }

            searchTimer = setTimeout(function () {
                searchMembers(searchText);
            }, 250);
        }
    );

    memberSearchInput.addEventListener(
        'keydown',
        function (event) {
            if (
                event.key === 'Escape'
            ) {
                hideMemberResults();
                return;
            }

            if (
                memberResults.classList.contains('d-none') ||
                currentResults.length === 0
            ) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();

                activeResultIndex = Math.min(
                    activeResultIndex + 1,
                    currentResults.length - 1
                );

                updateActiveResult();
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();

                activeResultIndex = Math.max(
                    activeResultIndex - 1,
                    0
                );

                updateActiveResult();
            }

            if (
                event.key === 'Enter' &&
                activeResultIndex >= 0
            ) {
                event.preventDefault();

                selectMember(
                    currentResults[activeResultIndex]
                );
            }
        }
    );

    clearMemberButton.addEventListener(
        'click',
        function () {
            clearSelectedMember(true);
            memberSearchInput.focus();
        }
    );

    document.addEventListener(
        'click',
        function (event) {
            if (
                !event.target.closest(
                    '.member-search-wrapper'
                )
            ) {
                hideMemberResults();
            }
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Loan-Type Maximum Calculations
    |--------------------------------------------------------------------------
    */
    const loanTypeSelect = document.getElementById(
        'member_loan_limit_loan_type_id'
    );

    const limitInput = document.getElementById(
        'member_loan_limit_amount'
    );

    const standardMaximumDisplay =
        document.getElementById(
            'standardMaximumDisplay'
        );

    const differenceDisplay =
        document.getElementById(
            'differenceDisplay'
        );

    const limitHelp = document.getElementById(
        'limitHelp'
    );

    function getSelectedMaximum() {
        const selectedOption =
            loanTypeSelect.options[
                loanTypeSelect.selectedIndex
            ];

        if (
            !selectedOption ||
            !selectedOption.dataset.max
        ) {
            return 0;
        }

        return parseFloat(
            selectedOption.dataset.max
        ) || 0;
    }

    function formatAmount(amount) {
        return Number(amount).toLocaleString(
            'en-KE',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );
    }

    function updateDifference() {
        const maximum = getSelectedMaximum();

        const individualLimit =
            parseFloat(limitInput.value) || 0;

        if (
            maximum > 0 &&
            individualLimit > 0
        ) {
            differenceDisplay.value =
                formatAmount(
                    Math.max(
                        0,
                        maximum - individualLimit
                    )
                );
        } else {
            differenceDisplay.value = '';
        }
    }

    function updateMaximum() {
        const maximum = getSelectedMaximum();

        if (maximum > 0) {
            standardMaximumDisplay.value =
                formatAmount(maximum);

            const highestAllowed =
                Math.max(
                    0.01,
                    maximum - 0.01
                );

            limitInput.max =
                highestAllowed.toFixed(2);

            limitHelp.textContent =
                'The highest permitted individual limit is KES ' +
                formatAmount(highestAllowed) +
                '.';
        } else {
            standardMaximumDisplay.value = '';

            limitInput.removeAttribute('max');

            limitHelp.textContent =
                'The amount must be lower than the selected loan-type maximum.';
        }

        updateDifference();
    }

    loanTypeSelect.addEventListener(
        'change',
        updateMaximum
    );

    limitInput.addEventListener(
        'input',
        updateDifference
    );

    /*
    |--------------------------------------------------------------------------
    | Form Validation
    |--------------------------------------------------------------------------
    */
    memberLoanLimitForm.addEventListener(
        'submit',
        function (event) {
            if (!memberIdInput.value) {
                event.preventDefault();

                memberSearchInput.setCustomValidity(
                    'Search for a member and select one from the results.'
                );

                memberSearchInput.reportValidity();
                memberSearchInput.focus();

                return;
            }

            if (!memberLoanLimitForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();

                memberLoanLimitForm.classList.add(
                    'was-validated'
                );

                return;
            }

            memberSearchInput.setCustomValidity('');

            memberDisplayInput.value =
                memberSearchInput.value;

            const saveButton =
                document.getElementById('saveButton');

            saveButton.disabled = true;

            saveButton.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
                'Saving...';
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Initial Page State
    |--------------------------------------------------------------------------
    */
    if (memberIdInput.value) {
        memberSearchInput.classList.add(
            'member-search-selected'
        );

        clearMemberButton.classList.remove(
            'd-none'
        );
    }

    updateMaximum();
});
</script>

@endsection