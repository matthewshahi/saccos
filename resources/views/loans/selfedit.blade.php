@extends('layouts.app')

@section('content')

<div class="container">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{!! nl2br(e($error)) !!}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">
            {!! nl2br(e(session('success'))) !!}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {!! nl2br(e(session('error'))) !!}
        </div>
    @endif

    <div class="alert alert-warning">
        <strong>Warning:</strong> Editing an already existing loan application will restart the loan application process.
        This includes requesting members who had guaranteed your loan to make fresh approvals.
    </div>

    <div class="row">
        <div class="col-md-12">
            <h3>Edit Loan Application</h3>
        </div>
    </div>

    <div class="card">
        <div class="card-body">

            <form action="{{ route('loans.application.update', ['id' => $loan->batch_trans_id]) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label for="batch_trans_member_name">Member Name*</label>
                        <input
                            class="form-control"
                            id="batch_trans_member_name"
                            name="batch_trans_member_name_display"
                            type="text"
                            value="{{ $loan->member_name }} - ({{ $loan->member_sacco_id }})"
                            readonly
                        >
                    </div>

                    <input type="hidden" name="batch_trans_member_id" value="{{ Auth::user()->member_id }}">
                    <input type="hidden" name="batch_trans_id" value="{{ $loan->batch_trans_id }}">
                    <input type="hidden" name="batch_trans_member_name" value="{{ $loan->member_name }}">

                    <div class="col-md-6 form-group mb-3">
                        <label for="batch_trans_loan_amount">Amount*</label>
                        <input
                            class="form-control"
                            id="batch_trans_loan_amount"
                            name="batch_trans_loan_amount"
                            type="text"
                            value="{{ old('batch_trans_loan_amount', $loan->batch_trans_loan_amount) }}"
                        >
                    </div>

                    <div class="col-md-6 form-group mb-3">
                        <label for="batch_trans_loan_type">Loan Type*</label>
                        <select class="form-control" id="batch_trans_loan_type" name="batch_trans_loan_type">
                            @foreach($loanTypes as $type)
                                <option value="{{ $type->loan_type_id }}"
                                    {{ old('batch_trans_loan_type', $loan->batch_trans_loan_type) == $type->loan_type_id ? 'selected' : '' }}>
                                    {{ $type->loan_type_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 form-group mb-3">
                        <label for="batch_trans_loan_category">Loan Category*</label>
                        <select class="form-control" id="batch_trans_loan_category" name="batch_trans_loan_category">
                            @foreach($loanCategories as $category)
                                <option value="{{ $category->loan_category_id }}"
                                    {{ old('batch_trans_loan_category', $loan->batch_trans_loan_category) == $category->loan_category_id ? 'selected' : '' }}>
                                    {{ $category->loan_category_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 form-group mb-3">
                        <label for="batch_trans_loan_duration">Repayment Period*</label>
                        <select class="form-control" id="batch_trans_loan_duration" name="batch_trans_loan_duration">
                            @for($i = 1; $i <= 100; $i++)
                                <option value="{{ $i }}"
                                    {{ old('batch_trans_loan_duration', $loan->batch_trans_loan_duration) == $i ? 'selected' : '' }}>
                                    {{ $i }} months
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-6 form-group mb-3">
                        <label for="batch_trans_description">Reason(s)* (max 50 characters)</label>
                        <input
                            class="form-control"
                            id="batch_trans_description"
                            name="batch_trans_description"
                            type="text"
                            value="{{ old('batch_trans_description', $loan->batch_trans_description) }}"
                            maxlength="50"
                        >
                    </div>

                    <div class="col-md-6 form-group mb-3">
                        <label for="batch_trans_loan_to_top_up">Loan to Top Up***</label>
                        <select class="form-control" id="batch_trans_loan_to_top_up" name="batch_trans_loan_to_top_up">
                            <option value="">Select Loan</option>
                            @foreach($memberLoans as $memberLoan)
                                <option value="{{ $memberLoan->loan_id }}"
                                    {{ old('batch_trans_loan_to_top_up', $loan->batch_trans_loan_to_top_up) == $memberLoan->loan_id ? 'selected' : '' }}>
                                    {{ $memberLoan->loan_type_name }} - {{ number_format($memberLoan->loan_balance, 2) }} ({{ $memberLoan->loan_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 form-group mb-3">
                        <label for="batch_trans_pay1">Attach your latest 2 payslips (jpg/jpeg/png/gif, max 200KB)</label>
                        <input type="file" class="form-control" id="batch_trans_pay1" name="batch_trans_pay1">
                    </div>

                    <div class="col-md-6 form-group mb-3">
                        <label for="batch_trans_pay2">Attach payslip 2 (jpg/jpeg/png/gif, max 200KB)</label>
                        <input type="file" class="form-control" id="batch_trans_pay2" name="batch_trans_pay2">
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <h4 class="card-title">Add New Guarantors (Max {{ (int) $maximumNoOfGuarantors }})</h4>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Guarantor Name</th>
                                        <th>Amount Guaranteed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @for($i = 0; $i < (int) $maximumNoOfGuarantors; $i++)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                <div class="position-relative">
                                                    <input
                                                        type="text"
                                                        name="guarantors[{{ $i }}][name]"
                                                        class="form-control"
                                                        placeholder="Search guarantor by name"
                                                        onkeyup="showHintMembers(this.value, 'guarantors[{{ $i }}][name]', 'suggestions_{{ $i }}')"
                                                        autocomplete="off"
                                                        value="{{ old('guarantors.'.$i.'.name') }}"
                                                    >
                                                    <div id="suggestions_{{ $i }}" class="suggestions-box"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <input
                                                    type="number"
                                                    name="guarantors[{{ $i }}][amount]"
                                                    class="form-control"
                                                    placeholder="Amount"
                                                    min="1"
                                                    step="0.01"
                                                    value="{{ old('guarantors.'.$i.'.amount') }}"
                                                >
                                            </td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12 form-group text-right">
                        <button class="btn btn-primary" type="submit">Update Loan</button>
                    </div>
                </div>
            </form>

            <div class="card mt-4">
                <div class="card-body">
                    <h4 class="card-title">Guarantors</h4>
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Guarantor Name</th>
                                <th>Amount Guaranteed</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($guarantors as $index => $guarantor)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $guarantor->member_name }}</td>
                                    <td>{{ number_format($guarantor->guarantors_amount_guaranteed, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $guarantor->guarantors_approved == 'Y' ? 'bg-success' : 'bg-warning' }}">
                                            {{ $guarantor->guarantors_approved == 'Y' ? 'Approved' : 'Pending' }}
                                        </span>
                                    </td>
                                    <td>
                                        <form action="{{ route('loans.delete.guarantor', ['id' => $guarantor->guarantors_id]) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No guarantors assigned to this loan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .suggestions-box {
        background-color: #fff;
        border: 1px solid #ccc;
        max-height: 150px;
        overflow-y: auto;
        position: absolute;
        z-index: 1050;
        width: 100%;
        border-radius: 0.25rem;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .suggestion-item {
        padding: 10px;
        cursor: pointer;
    }

    .suggestion-item:hover {
        background-color: #f8f9fa;
    }
</style>

<script>
    function showHintMembers(str, inputName, suggestionsBox) {
        if (str.length === 0) {
            document.getElementById(suggestionsBox).innerHTML = "";
            return;
        }

        let xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
                let response = JSON.parse(xmlhttp.responseText);
                let suggestions = '';

                response.forEach(member => {
                    suggestions += `<div class="suggestion-item" onclick="selectMember('${member.value}', '${inputName}', '${suggestionsBox}')">${member.label}</div>`;
                });

                document.getElementById(suggestionsBox).innerHTML = suggestions;
            }
        };

        const baseURL = "{{ url('/') }}";
        xmlhttp.open("GET", baseURL + "/search/members?query=" + encodeURIComponent(str), true);
        xmlhttp.send();
    }

    function selectMember(value, inputName, suggestionsBox) {
        document.querySelector(`input[name="${inputName}"]`).value = value;
        document.getElementById(suggestionsBox).innerHTML = '';
    }
</script>
@endsection